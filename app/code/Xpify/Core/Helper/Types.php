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
 *     returnUrl: string,
 *     trialDays: string
 *  }
 * /
 */
class Types {

}
