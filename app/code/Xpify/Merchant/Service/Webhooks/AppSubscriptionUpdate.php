<?php
declare(strict_types=1);

namespace Xpify\Merchant\Service\Webhooks;

use Magento\Framework\Exception\NoSuchEntityException;
use Shopify\Webhooks\Handler;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Model\MerchantSubscriptionFactory;
use Xpify\Merchant\Model\ResourceModel\MerchantSubscription;

class AppSubscriptionUpdate implements Handler
{
    private MerchantSubscription $subscriptionResource;
    private MerchantSubscriptionFactory $subscriptionFactory;

    /**
     * @param MerchantSubscription $subscriptionResource
     * @param MerchantSubscriptionFactory $subscriptionFactory
     */
    public function __construct(
        \Xpify\Merchant\Model\ResourceModel\MerchantSubscription $subscriptionResource,
        \Xpify\Merchant\Model\MerchantSubscriptionFactory $subscriptionFactory,
    ) {
        $this->subscriptionResource = $subscriptionResource;
        $this->subscriptionFactory = $subscriptionFactory;
    }

    /**
     * @inheritDoc
     */
    public function handle(string $topic, string $shop, array $body): void
    {
        $logger = Logger::getLogger('webhooks.app_subscription_update.log');
        $app_subscription = $body['app_subscription'];
        $admin_graphql_api_id = $app_subscription['admin_graphql_api_id'] ?? null;
        if (!$admin_graphql_api_id) {
            $logger->info(json_encode($body));
            return;
        }
        // extract the subscription ID from the admin_graphql_api_id. gid://shopify/AppSubscription/35176841492
        $subscription_id = str_replace(\Xpify\Core\Model\Constants::SHOPIFY_SUBSCRIPTION_GLOBAL_ID_PREFIX, '', $admin_graphql_api_id);
        try {
            $subscription = $this->subscriptionFactory->create();
            $this->subscriptionResource->loadBySubscription($subscription, $subscription_id);
        } catch (NoSuchEntityException $e) {
            $logger->debug($e->__toString());
            return;
        }

        try {
            $status = $app_subscription['status'];
            if (strtolower($subscription->getStatus()) !== strtolower($status)) {
                $subscription->setStatus(strtoupper($status));
                $this->subscriptionResource->save($subscription);
            }
        } catch (\Throwable $e) {
            $logger->debug($e->__toString());
        }
    }
}

