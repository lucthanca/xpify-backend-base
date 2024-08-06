<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Model\Queue\Handler;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Shopify\Exception\ShopifyException;
use Xpify\App\Api\AppRepositoryInterface as IAppRepository;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;
use Xpify\MerchantQueue\Api\Data\TopicDataInterface as ITopicData;
use Xpify\MerchantQueue\Service\Sender;

class MerchantInfoHandler
{
    private IAppRepository $appRepository;
    private IMerchantRepository $merchantRepository;
    private ShopifyContextInitializer $initializer;
    private Sender $webhookSender;
    private \Magento\Framework\Serialize\Serializer\Json $json;

    /**
     * @param IAppRepository $appRepository
     * @param IMerchantRepository $merchantRepository
     * @param ShopifyContextInitializer $initializer
     * @param \Xpify\MerchantQueue\Service\Sender $webhookSender
     * @param Json $json
     */
    public function __construct(
        IAppRepository $appRepository,
        IMerchantRepository $merchantRepository,
        ShopifyContextInitializer $initializer,
        Sender $webhookSender,
        \Magento\Framework\Serialize\Serializer\Json $json
    ) {
        $this->appRepository = $appRepository;
        $this->merchantRepository = $merchantRepository;
        $this->initializer = $initializer;
        $this->webhookSender = $webhookSender;
        $this->json = $json;
    }

    public function execute(ITopicData $rqData)
    {
        $appId = $rqData->getAppId();
        if (!$appId) {
            $this->getLogger()->debug('App ID is missing in the request data.');
            return false;
        }
        $sessId = $rqData->getSessionId();
        if (empty($sessId)) {
            $this->getLogger()->debug('Session ID is missing in the request data.');
            return false;
        }
        $app = $this->appRepository->get($appId);
        if (!$app) {
            $this->getLogger()->debug('App not found for ID: ' . $appId);
            return false;
        }

        try {
            if ($rqData->getType() === ITopicData::TYPE_MERCHANT_UNINSTALLED) {
                $merchantData = $rqData->getData();
                if (empty($merchantData) || empty($merchantData[0])) {
                    $this->getLogger()->debug(__('TYPE [%1] -- Merchant data is missing in the request data.', $rqData->getTypeAsName())->render());
                    return false;
                }
                $merchantData = reset($merchantData);
                $merchantData = $this->json->unserialize($merchantData);
                $shop = $merchantData['shop'] ?? null;
                if (!$shop) {
                    $this->getLogger()->debug(__('TYPE [%1] -- Merchant data is missing in the request data.', $rqData->getTypeAsName())->render());
                    return false;
                }
                $name = $merchantData['name'] ?? 'N/A';
                $email = $merchantData['email'] ?? 'N/A';
                $this->webhookSender->send($app->getId(), [
                    'myshopify_domain' => $shop,
                    'name' => $name,
                    'email' => $email,
                    'type' => $rqData->getType(),
                ]);
                return true;
            }
            $criteriaBuilder = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $criteriaBuilder->addFilter(IMerchant::APP_ID, $appId);
            $criteriaBuilder->addFilter(IMerchant::SESSION_ID, $sessId);
            $criteriaBuilder->setPageSize(1);
            $searchResult = $this->merchantRepository->getList($criteriaBuilder->create());
            if (!$searchResult->getTotalCount()) {
                $this->getLogger()->debug('Merchant not found for app ID: ' . $appId . ' and session ID: ' . $sessId);
                return false;
            }
            $items = $searchResult->getItems();
            /** @var IMerchant $merchant */
            $merchant = reset($items);
            if ($rqData->getType() === ITopicData::TYPE_MERCHANT_NEW) {
                $shop_info_query = <<<QUERY
query GetShopInfo {
    shop {
		name
		myshopifyDomain
		email
	}
}
QUERY;
                $this->initializer->initialize($app);
                $response = $merchant->getGraphql()->query(data: $shop_info_query);
                if ($response->getStatusCode() !== 200) {
                    $this->getLogger()->debug(__("Failed to fetch Shop Info: %1, app ID: %2", $merchant->getShop(), $merchant->getAppId())->render());
                    throw new ShopifyException('Failed to fetch Shop Info');
                }
                $decodedBody = $response->getDecodedBody();
                $shopInfo = $decodedBody['data']['shop'];
                $merchant->setEmail($shopInfo['email']);
                $merchant->setName($shopInfo['name']);
                $this->merchantRepository->save($merchant);
            }

            $this->webhookSender->send($app->getId(), [
                'myshopify_domain' => $merchant->getShop(),
                'name' => $merchant->getName(),
                'email' => $merchant->getEmail(),
                'type' => $rqData->getType(),
            ]);
        } catch (\Throwable $e) {
            $shouldThrow = ($e instanceof LocalizedException && $e->getCode() === 1400) || $e instanceof ShopifyException;
            if ($shouldThrow) {
                throw $e;
            }
            $merchantIdOrSId = isset($merchant) ? $merchant?->getId() : $sessId;
            $this->getLogger()->debug('Failed to fetch shop info for merchant: ' . $merchantIdOrSId . ' with error: ' . $e->getMessage());
            throw new LocalizedException(__("Failed to fetch shop info for merchant: %1", $merchantIdOrSId));
        }
        return true;
    }

    private function getLogger ()
    {
        return Logger::getLogger('merchant_info_handler.log');
    }
}
