<?php
declare(strict_types=1);

namespace Xpify\Merchant\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface as IObserver;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;
use Xpify\Merchant\Exception\AlreadySubscribedException;
use Xpify\Merchant\Helper\Subscription;
use Xpify\Merchant\Model\MerchantSubscriptionFactory;
use Xpify\Merchant\Model\ResourceModel\MerchantSubscription;

class OnboardFreePlan implements IObserver
{
    private MerchantSubscription $subscriptionResource;
    private MerchantSubscriptionFactory $subscriptionFactory;
    private IMerchantRepository $merchantRepository;

    /**
     * @param MerchantSubscription $subscriptionResource
     * @param MerchantSubscriptionFactory $subscriptionFactory
     * @param IMerchantRepository $merchantRepository
     */
    public function __construct(
        \Xpify\Merchant\Model\ResourceModel\MerchantSubscription $subscriptionResource,
        \Xpify\Merchant\Model\MerchantSubscriptionFactory $subscriptionFactory,
        IMerchantRepository $merchantRepository
    ) {
        $this->subscriptionResource = $subscriptionResource;
        $this->subscriptionFactory = $subscriptionFactory;
        $this->merchantRepository = $merchantRepository;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $app = $observer->getData('app');
        $shop = $app->getShop();
        $sessionId = $observer->getData('sess_id');
        try {
            if (!$shop || !$app?->getId()) {
                Logger::getLogger('merchant.onboard.log')->debug(
                    'Missing required data in app_installed_successfully event',
                    ['app' => $app?->getId(), 'shop' => $shop]
                );
                return;
            }
            $merchant = $this->merchantRepository->getBySessId($sessionId, (int)$app->getId());

            Subscription::assignFreeSubscription($merchant);
        } catch (\Throwable $e) {
            if ($e instanceof AlreadySubscribedException || $e instanceof \Magento\Framework\Exception\NoSuchEntityException || $e instanceof \RuntimeException) {
                Logger::getLogger('merchant.onboard.log')->debug("[$shop] {$e->getMessage()}");
                return;
            }
            Logger::getLogger('merchant.onboard.log')->debug("{$e->getMessage()} | {$e->getTraceAsString()}");
        }
    }
}
