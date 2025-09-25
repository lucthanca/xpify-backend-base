<?php
declare(strict_types=1);

namespace Xpify\PricingPlanGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\Core\Model\Constants;
use Xpify\PricingPlan\Service\PricingManager;
use Xpify\PricingPlanGraphQl\Model\PricingPlanFormatter;

class AppPricingPlanQuery extends AuthSessionAbstractResolver implements \Magento\Framework\GraphQl\Query\ResolverInterface
{
    private \Xpify\PricingPlan\Service\PricingManager $pricingManager;

    /**
     * @param PricingManager $pricingManager
     */
    public function __construct(
        \Xpify\PricingPlan\Service\PricingManager $pricingManager
    ) {
        $this->pricingManager = $pricingManager;
    }

    /**
     * @inheritDoc
     */
    public function execResolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        try {
            $id = $context->getExtensionAttributes()->getApp()?->getId();
            if ($id === null) {
                throw new GraphQlInputException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
            }
            $activePlans = $this->pricingManager->getListActive((int) $id);
            $items = [];
            foreach ($activePlans as $item) {
                $items[] = PricingPlanFormatter::toGraphQlOutput($item);
            }
            return $items;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
