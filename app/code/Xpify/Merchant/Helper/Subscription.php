<?php
declare(strict_types=1);

namespace Xpify\Merchant\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Xpify\Core\Exception\ShopifyQueryException;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;
use Xpify\Merchant\Api\MerchantSubscriptionRepositoryInterface as ISubscriptionRepository;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Exception\AlreadySubscribedException;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;

/**
 * @phpstan-import-type AppSubscription from \Xpify\Core\Helper\Types
 */
final class Subscription
{
    use GraphqlQueryTrait;

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
            $subscription->setShop($merchant->getShopName());
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

    public static function hasActiveOrFrozenSubscription(IMerchant $merchant): bool
    {
        try {
            $remoteActiveSub = self::getRemoteActiveSubscription($merchant);

            $remoteSub = $remoteActiveSub;
            unset($remoteActiveSub);

            // Check xem co subscription active trên Shopify không
            if (!$remoteSub) {
                // Neu khong co thi check xem co subscription frozen tren shopify khong
                $remoteFrozenSub = self::getLatestSubscription($merchant);
                if (!$remoteFrozenSub || $remoteFrozenSub['status'] !== 'FROZEN') {
                    return false;
                }
                $remoteSub = $remoteFrozenSub;
                unset($remoteFrozenSub);
            }

            $remoteSubId = str_replace('gid://shopify/AppSubscription/', '', $remoteSub['id']);

            $localSub = self::getSubscriptionRepository()->getByShopifySubscriptionId($remoteSubId);

            $localSub->setStatus($remoteSub['status']);
            if (!$localSub->getId()) {
                $returnUrl = $remoteSub['returnUrl'];
                // extract plan id from return url by getting the _pid from url path, by parsing the url using magento uri object
                $request = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\RequestInterface::class);
                $clonedRequest = clone $request;
                unset($request);
                $urlParts = parse_url($returnUrl);

                $planUid = null;

            }
            $localSub->save();

        } catch (\Throwable $e) {
            Logger::getLogger('merchant.subscription.log')->debug($e->__toString());
            return false;
        }
    }

    /**
     * Retrieve active subscription from Shopify
     *
     * @param IMerchant $merchant
     * @return AppSubscription|null
     * @throws ShopifyQueryException
     */
    private static function getRemoteActiveSubscription(IMerchant $merchant): ?array
    {
        $responseBody = self::query($merchant, self::RECURRING_PURCHASES_QUERY);
        return $responseBody["data"]["currentAppInstallation"]["activeSubscriptions"][0] ?? null;
    }

    /**
     * Retrieve latest subscription from Shopify
     *
     * @param IMerchant $merchant
     * @return AppSubscription|null
     * @throws ShopifyQueryException
     */
    private static function getLatestSubscription(IMerchant $merchant): ?array
    {
        $res = self::query($merchant, self::LATEST_SUBSCRIPTION_QUERY);
        return $res["data"]["currentAppInstallation"]['allSubscriptions']['nodes'][0] ?? null;
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
     * Get activated or frozen subscription of the given merchant
     *
     * @param IMerchant $merchant
     * @return ISubscription|null
     */
    public static function getSubscription(IMerchant $merchant): ?ISubscription
    {
        if (!isset(self::$subscriptions[$merchant->getId()])) {
            $criteriaBuilder = self::getSearchCriteriaBuilder();
            $criteriaBuilder->addFilter(ISubscription::SHOP, $merchant->getShopName());
            $criteriaBuilder->addFilter(ISubscription::APP_ID, $merchant->getAppId());
            $criteriaBuilder->addFilter(ISubscription::STATUS, [
                ISubscription::STATUS_ACTIVE,
                ISubscription::STATUS_FROZEN,
            ], 'in');
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
            $criteriaBuilder->addFilter(ISubscription::SHOP, $merchant->getShopName());
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

    private const RECURRING_PURCHASES_QUERY = <<<'QUERY'
    query appSubscription {
        currentAppInstallation {
            activeSubscriptions { id name test currentPeriodEnd trialDays }
        }
    }
    QUERY;

    private const LATEST_SUBSCRIPTION_QUERY = <<<'QUERY'
query LatestSubscription {
    currentAppInstallation{
        allSubscriptions(first: 1, sortKey: CREATED_AT, reverse: true){
            nodes{id name status}
        }
    }
}
QUERY;

}
