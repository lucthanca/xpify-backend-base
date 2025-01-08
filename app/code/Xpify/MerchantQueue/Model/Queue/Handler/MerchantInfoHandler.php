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
use Xpify\MerchantQueue\Model\Queue\Handler\QueueHandlerInterface as IQueueHandler;

class MerchantInfoHandler
{
    private IAppRepository $appRepository;
    private IMerchantRepository $merchantRepository;
    private ShopifyContextInitializer $initializer;
    private Sender $webhookSender;
    private \Magento\Framework\Serialize\Serializer\Json $json;

    /**
     * @var QueueHandlerInterface[]
     */
    private array $handlerPool;

    /**
     * @param IAppRepository $appRepository
     * @param IMerchantRepository $merchantRepository
     * @param ShopifyContextInitializer $initializer
     * @param \Xpify\MerchantQueue\Service\Sender $webhookSender
     * @param Json $json
     * @param IQueueHandler[] $handlerPool
     */
    public function __construct(
        IAppRepository $appRepository,
        IMerchantRepository $merchantRepository,
        ShopifyContextInitializer $initializer,
        Sender $webhookSender,
        \Magento\Framework\Serialize\Serializer\Json $json,
        array $handlerPool = []
    ) {
        $this->appRepository = $appRepository;
        $this->merchantRepository = $merchantRepository;
        $this->initializer = $initializer;
        $this->webhookSender = $webhookSender;
        $this->json = $json;
        $this->handlerPool = $handlerPool;
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
            foreach ($this->handlerPool as $handler) {
                $handler->handle($app, $rqData);
            }
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
