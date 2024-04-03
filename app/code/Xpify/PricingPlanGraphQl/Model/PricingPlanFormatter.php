<?php
declare(strict_types=1);

namespace Xpify\PricingPlanGraphQl\Model;

use Xpify\Core\Helper\Utils;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;

final class PricingPlanFormatter
{
    /**
     * Convert Pricing Plan to GraphQl output
     *
     * @param IPricingPlan $pricingPlan
     * @return array
     */
    public static function toGraphQlOutput(IPricingPlan $pricingPlan): array
    {
        return [
            'model' => $pricingPlan,
            'id' => Utils::idToUid((string) $pricingPlan->getId()),
            'currency' => IPricingPlan::BASE_CURRENCY,
            IPricingPlan::STATUS => (bool) $pricingPlan->getStatus(),
            IPricingPlan::CODE => $pricingPlan->getCode(),
            IPricingPlan::NAME => $pricingPlan->getName(),
            IPricingPlan::PRICES => $pricingPlan->getDataPrices(),
            IPricingPlan::DESCRIPTION => $pricingPlan->getDescription(),
            IPricingPlan::SORT_ORDER => $pricingPlan->getSortOrder(),
        ];
    }
}
