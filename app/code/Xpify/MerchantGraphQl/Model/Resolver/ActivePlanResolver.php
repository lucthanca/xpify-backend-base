<?php
declare(strict_types=1);

namespace Xpify\MerchantGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface as IResolver;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\Core\Exception\ShopifyQueryException;
use Xpify\Core\Helper\Utils;
use Xpify\Core\Model\Constants;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;
use Xpify\Merchant\Helper\GraphqlQueryTrait;

class ActivePlanResolver extends AuthSessionAbstractResolver implements IResolver
{
    use GraphqlQueryTrait;

    /**
     * @inheritDoc
     */
    public function execResolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        /** @var IMerchant|null $merchant */
        $merchant = $context->getExtensionAttributes()->getMerchant();
        if (!$merchant?->getId()) {
            throw new GraphQlInputException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
        }
        $subscription = \Xpify\Merchant\Helper\Subscription::getSubscription($merchant);
        if (!$subscription) {
            return [
                'id' =>  Utils::idToUid('old_free'),
                'status' => ISubscription::STATUS_ACTIVE,
                'name' => 'Legacy',
                'code' => 'old_free',
                'interval' => 'ONE_TIME',
                'price' => 0,
            ];
        }

        // Light check from Shopify side
        try {
            $res = self::query($merchant, self::LIGHT_RECURRING_PURCHASES_QUERY);
            /** @var array{ id:string, status: string, name: string }|null $activeSubscription */
            $activeSubscription = $res["data"]["currentAppInstallation"]['activeSubscriptions'][0] ?? null;
            if (!$activeSubscription) {
                $latestRes = self::query($merchant, self::LIGHT_LATEST_SUBSCRIPTION_QUERY);
                /** @var array{ id:string, status: string, name: string }|null $latestSubscription */
                $activeSubscription = $latestRes["data"]["currentAppInstallation"]['allSubscriptions']['nodes'][0] ?? null;
                if (empty($activeSubscription['status']) || $activeSubscription['status'] !== ISubscription::STATUS_FROZEN) {
                    throw new GraphQlInputException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
                }
            }

            if (str_replace(\Xpify\Core\Model\Constants::SHOPIFY_SUBSCRIPTION_GLOBAL_ID_PREFIX, '', $activeSubscription['id']) !== $subscription->getSubscriptionId()) {
                throw new \Magento\Framework\Exception\NotFoundException(
                    __('Subscription ID mismatch: local=' . $subscription->getSubscriptionId() . ', shopify=' . str_replace(\Xpify\Core\Model\Constants::SHOPIFY_SUBSCRIPTION_GLOBAL_ID_PREFIX, '', $activeSubscription['id']))
                );
            }
            $subscription->setStatus(strtoupper($activeSubscription['status']))->save();

            return [
                'id' => Utils::idToUid($subscription->getPlanId() . ""),
                'status' => $subscription->getStatus(),
                'name' => $subscription->getName(),
                'code' => $subscription->getCode(),
                'interval' => $subscription->getInterval(),
                'base_price' => $subscription->getPrice(),
                'final_price' => $subscription->getFinalPrice(),
                'created_at' => $subscription->getCreatedAt(),
            ];
        } catch (\Throwable $e) {
            $msg = '';
            if ($e instanceof ShopifyQueryException) {
                if (!empty($e->getErrors())) {
                    $msg .= json_encode($e->getErrors()) . "\n";
                }
            }
            Logger::getLogger('shopify.recurring.log')->debug($msg . $e->__toString());
            throw new GraphQlInputException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
        }
    }

    private const LIGHT_RECURRING_PURCHASES_QUERY = <<<'QUERY'
query appSubscription {
    currentAppInstallation {
        activeSubscriptions { id status name }
    }
}
QUERY;

    private const LIGHT_LATEST_SUBSCRIPTION_QUERY = <<<'QUERY'
query LatestSubscription {
    currentAppInstallation{
        allSubscriptions(first: 1, sortKey: CREATED_AT, reverse: true){
            nodes{ id status name }
        }
    }
}
QUERY;
}
