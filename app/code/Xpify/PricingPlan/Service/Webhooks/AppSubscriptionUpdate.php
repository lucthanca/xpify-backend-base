<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Service\Webhooks;

use Shopify\Webhooks\Handler;
use Xpify\Core\Model\Logger;

class AppSubscriptionUpdate implements Handler
{
    /**
     * @inheritDoc
     */
    public function handle(string $topic, string $shop, array $body): void
    {
        $logger = Logger::getLogger('app_subscription_update.log');
        $app_subscription = $body['app_subscription'];
        $admin_graphql_api_id = $app_subscription['admin_graphql_api_id'] ?? null;
        if (!$admin_graphql_api_id) {
            $logger->info(json_encode($body));
            return;
        }
        // extract the subscription ID from the admin_graphql_api_id. gid://shopify/AppSubscription/35176841492
        $subscription_id = (int)str_replace('gid://shopify/AppSubscription/', '', $admin_graphql_api_id);
    }
}

