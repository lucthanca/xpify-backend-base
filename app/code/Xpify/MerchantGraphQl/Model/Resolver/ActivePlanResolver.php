<?php
declare(strict_types=1);

namespace Xpify\MerchantGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface as IResolver;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\Core\Helper\Utils;
use Xpify\Core\Model\Constants;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;

class ActivePlanResolver extends AuthSessionAbstractResolver implements IResolver
{
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

        return [
            'id' => Utils::idToUid($subscription->getPlanId() . ""),
            'status' => $subscription->getStatus(),
            'name' => $subscription->getName(),
            'code' => $subscription->getCode(),
            'interval' => $subscription->getInterval(),
            'price' => $subscription->getFinalPrice(),
            'created_at' => $subscription->getCreatedAt(),
        ];
    }
}
