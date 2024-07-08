<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Model\Queue\Handler;

use Magento\Framework\Exception\LocalizedException;
use Shopify\Exception\ShopifyException;
use Xpify\App\Api\AppRepositoryInterface as IAppRepository;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Core\Model\Logger;
use Xpify\MerchantQueue\Api\Data\TopicDataInterface as ITopicData;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class MerchantInfoHandler
{
    private IAppRepository $appRepository;
    private IMerchantRepository $merchantRepository;
    private ShopifyContextInitializer $initializer;

    /**
     * @param IAppRepository $appRepository
     * @param IMerchantRepository $merchantRepository
     * @param ShopifyContextInitializer $initializer
     */
    public function __construct(
        IAppRepository $appRepository,
        IMerchantRepository $merchantRepository,
        ShopifyContextInitializer $initializer
    ) {
        $this->appRepository = $appRepository;
        $this->merchantRepository = $merchantRepository;
        $this->initializer = $initializer;
    }

    public function execute(ITopicData $rqData)
    {
        $appId = $rqData->getAppId();
        if (!$appId) {
            $this->getLogger()->debug('App ID is missing in the request data.');
        }
        $sessId = $rqData->getSessionId();
        if (empty($sessId)) {
            $this->getLogger()->debug('Session ID is missing in the request data.');
        }
        $app = $this->appRepository->get($appId);
        if (!$app) {
            $this->getLogger()->debug('App not found for ID: ' . $appId);
        }
        $criteriaBuilder = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $criteriaBuilder->addFilter(IMerchant::APP_ID, $appId);
        $criteriaBuilder->addFilter(IMerchant::SESSION_ID, $sessId);
        $criteriaBuilder->setPageSize(1);
        $searchResult = $this->merchantRepository->getList($criteriaBuilder->create());
        if (!$searchResult->getTotalCount()) {
            $this->getLogger()->debug('Merchant not found for app ID: ' . $appId . ' and session ID: ' . $sessId);
        }
        $items = $searchResult->getItems();
        /** @var IMerchant $merchant */
        $merchant = reset($items);

        $shop_info_query = <<<QUERY
query GetShopInfo {
    shop {
		name
		myshopifyDomain
		email
	}
}
QUERY;

        try {
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
        } catch (\Throwable $e) {
            if ($e instanceof ShopifyException) {
                throw $e;
            }
            $this->getLogger()->debug('Failed to fetch shop info for merchant with ID: ' . $merchant->getId() . ' with error: ' . $e->getMessage());
            throw new LocalizedException(__("Failed to fetch shop info for merchant with ID: %1", $merchant->getId()));
        }
        return true;
    }

    private function getLogger ()
    {
        return Logger::getLogger('merchant_info_handler.log');
    }
}
