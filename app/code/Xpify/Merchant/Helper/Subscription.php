<?php
declare(strict_types=1);

namespace Xpify\Merchant\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;
use Xpify\Merchant\Api\MerchantSubscriptionRepositoryInterface as ISubscriptionRepository;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Exception\AlreadySubscribedException;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;

final class Subscription
{
    private static array $subscriptions = [];

    /**
     * Store list existed subscriptions of each merchant
     *
     * @var ISubscription[]
     */
    private static array $subscriptionList = [];
    private static ?ISubscriptionRepository $subscriptionRepository = null;

    /**
     * Assign free subscription to the given merchant
     *
     * @param IMerchant $merchant
     * @return void
     * @throws AlreadySubscribedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \RuntimeException
     */
    public static function assignFreeSubscription(IMerchant $merchant): void
    {
        $freePlan = \Magento\Framework\App\ObjectManager::getInstance()->get(\Xpify\PricingPlan\Service\PricingManager::class)->getFreePricingPlan((int) $merchant->getAppId());
        // Get current active plan
        $activeSubscription = self::getSubscription($merchant);
        if ($activeSubscription?->getId()) {
            throw new AlreadySubscribedException(__("Merchant already has an active subscription"));
        }

        try {
            $subscription = self::newSubscription();
            $subscription->setMerchantId($merchant->getId());
            $subscription->setAppId($merchant->getAppId());
            $subscription->setPlanId($freePlan->getId());
            $subscription->setName($freePlan->getName());
            $subscription->setPrice(0);
            $subscription->setStatus(ISubscription::STATUS_ACTIVE);
            $subscription->setInterval("ONE_TIME");
            $subscription->save();
        } catch (\Throwable $e) {
            Logger::getLogger('merchant.subscription.log')->debug(
                $e->getMessage() . "\n" . $e->getTraceAsString()
            );
            throw new \RuntimeException("Cannot assign free subscription to merchant: " . $e->getMessage());
        }
    }

    /**
     * Check if merchant has subscription
     *
     * @param IMerchant $merchant
     * @return array [bool, ISubscription|null]
     */
    public static function hasSubscription(IMerchant $merchant, IPricingPlan $plan, string $interval): array
    {
        $subscription = self::getSubscription($merchant);
        if ($subscription?->getPlanId() . "" === $plan->getId() . "" && $subscription->getInterval() === $interval) {
            return [true, $subscription];
        }
        return [false, null];
    }

    /**
     * Create new subscription
     *
     * @return ISubscription
     */
    public static function newSubscription(): ISubscription
    {
        return self::getSubscriptionRepository()->create();
    }

    /**
     * Get activated subscription of the given merchant
     *
     * @param IMerchant $merchant
     * @return ISubscription|null
     */
    public static function getSubscription(IMerchant $merchant): ?ISubscription
    {
        if (!isset(self::$subscriptions[$merchant->getId()])) {
            $criteriaBuilder = self::getSearchCriteriaBuilder();
            $criteriaBuilder->addFilter(ISubscription::MERCHANT_ID, $merchant->getId());
            $criteriaBuilder->addFilter(ISubscription::APP_ID, $merchant->getAppId());
            $criteriaBuilder->addFilter(ISubscription::STATUS, ISubscription::STATUS_ACTIVE);
            $criteriaBuilder->setPageSize(1);
            $searchResults = self::getSubscriptionRepository()->getList($criteriaBuilder->create());
            if ($searchResults->getTotalCount() > 0) {
                $subs = $searchResults->getItems();
                self::$subscriptions[$merchant->getId()] = reset($subs);
            }
        }
        return self::$subscriptions[$merchant->getId()] ?? null;
    }

    /**
     * Get list existed subscriptions of the given merchant
     *
     * @param IMerchant $merchant
     * @return ISubscription[]
     */
    public static function getSubscriptions(IMerchant $merchant): array
    {
        if (!isset(self::$subscriptionList[$merchant->getId()])) {
            $criteriaBuilder = self::getSearchCriteriaBuilder();
            $criteriaBuilder->addFilter(ISubscription::MERCHANT_ID, $merchant->getId());
            $criteriaBuilder->addFilter(ISubscription::APP_ID, $merchant->getAppId());
            $searchResults = self::getSubscriptionRepository()->getList($criteriaBuilder->create());
            if ($searchResults->getTotalCount() > 0) {
                self::$subscriptionList[$merchant->getId()] = $searchResults->getItems();
            }
        }
        return self::$subscriptionList[$merchant->getId()] ?? [];
    }

    /**
     * @return ISubscriptionRepository
     */
    private static function getSubscriptionRepository(): ISubscriptionRepository
    {
        if (!self::$subscriptionRepository) {
            self::$subscriptionRepository = \Magento\Framework\App\ObjectManager::getInstance()->get(ISubscriptionRepository::class);
        }
        return self::$subscriptionRepository;
    }

    /**
     * @return SearchCriteriaBuilder
     */
    private static function getSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return \Magento\Framework\App\ObjectManager::getInstance()->create(SearchCriteriaBuilder::class);
    }
}
