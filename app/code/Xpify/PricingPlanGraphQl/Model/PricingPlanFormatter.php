<?php
declare(strict_types=1);

namespace Xpify\PricingPlanGraphQl\Model;

use Xpify\Core\Helper\Utils;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;

final class PricingPlanFormatter
{
    const GRAPHQL_VALID_BADGE_TONES = [
        'INFO',
        'SUCCESS',
        'WARNING',
        'CRITICAL',
        'ATTENTION',
        'NEW',
        'MAGIC',
        'INFO_STRONG',
        'SUCCESS_STRONG',
        'WARNING_STRONG',
        'CRITICAL_STRONG',
        'ATTENTION_STRONG',
        'READ_ONLY',
        'ENABLED',
    ];

    /**
     * Convert Pricing Plan to GraphQl output
     *
     * @param IPricingPlan $pricingPlan
     * @return array
     */
    public static function toGraphQlOutput(IPricingPlan $pricingPlan): array
    {
        $graphQlBadges = array_filter(
            array_map(
                fn ($badge) => [
                    'content' => $badge['content'],
                    'tone' => strtoupper(str_replace('-', '_', $badge['tone'])),
                ],
                $pricingPlan->getBadges() ?? []
            ),
            fn ($badge) => in_array($badge['tone'], self::GRAPHQL_VALID_BADGE_TONES, true)
        );
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
            IPricingPlan::BADGES => $graphQlBadges,
            IPricingPlan::SHORT_DESCRIPTION => $pricingPlan->getShortDescription(),
            IPricingPlan::FREE_TRIAL_DAYS => $pricingPlan->getFreeTrialDays(),
        ];
    }
}
