<?php
declare(strict_types=1);

namespace Xpify\Core\Helper;

/**
 * @phpstan-type AppSubscriptionStatus 'PENDING'|'DECLINED'|'ACTIVE'|'EXPIRED'|'FROZEN'|'CANCELLED'
 * @phpstan-type AppSubscription array{
 *     id: string,
 *     name: string,
 *     status: AppSubscriptionStatus,
 *     createdAt: string,
 *     test: boolean,
 *     returnUrl: string,
 *     trialDays: string,
 *     lineItems: array<int, array{
 *         plan: array{
 *             pricingDetails: array{
 *                 interval: 'EVERY_30_DAYS' | 'ANNUAL' | 'ONE_TIME',
 *                 price: array{ amount: float },
 *                 discount: array{
 *                     durationLimitInIntervals: int, priceAfterDiscount: array{ amount: float }, value: array{
 *                         amount?: array{ amount: float }, percentage?: float
 *                     }
 *                 }
 *             }
 *         }
 *     }>
 *  }
 * /
 */
class Types {

}
