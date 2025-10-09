<?php
declare(strict_types=1);

namespace Xpify\Merchant\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Xpify\Core\Exception\ShopifyQueryException;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;
use Xpify\Merchant\Api\MerchantSubscriptionRepositoryInterface as ISubscriptionRepository;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Exception\AlreadySubscribedException;
use Xpify\Merchant\Exception\ShopifyAppSubscriptionException;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface as IPricingPlanRepository;

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
     * Chắc chắn merchant có subscription trong hệ thống
     *
     * @param IMerchant $merchant
     * @param AppSubscription $shopifySubscription
     * @return void
     * @throws ShopifyAppSubscriptionException|\Magento\Framework\GraphQl\Exception\GraphQlInputException
     */
    public static function ensureSubscription(IMerchant $merchant, array $shopifySubscription): void
    {
        $remoteSubId = str_replace(\Xpify\Core\Model\Constants::SHOPIFY_SUBSCRIPTION_GLOBAL_ID_PREFIX, '', $shopifySubscription['id']);
        $localSub = self::getSubscriptionRepository()->getByShopifySubscriptionId($remoteSubId);

        if (!$localSub->getId()) {
            $localSub->setStatus($shopifySubscription['status']);
            $returnUrlRequest = \Xpify\Core\Helper\Utils::parseRequest($shopifySubscription['returnUrl']);
            $planId = $returnUrlRequest->getParam('_pid');
            if (!$planId) {
                throw new ShopifyAppSubscriptionException("Cannot find pricing plan id from return url");
                // keep return true because the merchant has active or frozen subscription on Shopify
            }
            $planId = (int) \Xpify\Core\Helper\Utils::uidToId($planId);
            $planRepository = ObjectManager::getInstance()->get(IPricingPlanRepository::class);
            try {
                $plan = $planRepository->get($planId, true);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                throw new ShopifyAppSubscriptionException("Cannot find pricing plan with id $planId");
            }
            $localSub->setPlanId($planId);
            $localSub->setAppId($merchant->getAppId());
            $localSub->setCode($plan->getCode());
            $localSub->setName($plan->getName());

            $appRecurringPrice = $shopifySubscription['lineItems'][0]['plan']['pricingDetails']['price']['amount'] ?? null;
            $interval = $shopifySubscription['lineItems'][0]['plan']['pricingDetails']['interval'] ?? null;
            if (!$appRecurringPrice || !$interval) {
                throw new ShopifyAppSubscriptionException("Cannot get recurring price from Shopify subscription");
            }
            $localSub->setPrice((float) $appRecurringPrice);
            $localSub->setInterval($interval);

            $localSub->setCreatedAt(date('Y-m-d H:i:s', strtotime($shopifySubscription['createdAt'])));
            $localSub->setSubscriptionId($remoteSubId);
            $localSub->setTrialDays((int) $shopifySubscription['trialDays']);
            $localSub->setShop($merchant->getShopName());
            $discount = $shopifySubscription['lineItems'][0]['plan']['pricingDetails']['discount'] ?? [];
            if ($returnUrlRequest->getParam('_cp')) {
                $discount['coupon'] = $returnUrlRequest->getParam('_cp');
            }
            $localSub->setAppliedDiscount($discount);

            try {
                self::getSubscriptionRepository()->save($localSub);
            } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
                throw new ShopifyAppSubscriptionException("Cannot save subscription.");
            }
        }
    }

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
        $freePlan = ObjectManager::getInstance()->get(\Xpify\PricingPlan\Service\PricingManager::class)->getFreePricingPlan((int) $merchant->getAppId());
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
     * Kiểm tra xem merchant đã đăng ký gói cước với tên và chu kỳ thanh toán tương ứng chưa
     *
     * @param IMerchant $merchant
     * @param IPricingPlan $plan
     * @param string $interval
     * @return bool
     * @throws ShopifyAppSubscriptionException
     */
    public static function hasSubscribed(IMerchant $merchant, IPricingPlan $plan, string $interval): bool
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

            $currentPlanName = $remoteSub['name'];
            $currentInterval = $remoteSub['lineItems'][0]['plan']['pricingDetails']['interval'] ?? null;

            if (!$currentPlanName || !$currentInterval) {
                throw new ShopifyAppSubscriptionException("Cannot get plan name or interval from Shopify subscription");
            }

            return strtolower($currentPlanName) === strtolower($plan->getName()) && $currentInterval === $interval;
        } catch (ShopifyAppSubscriptionException $e) {
            Logger::getLogger('merchant.subscription.log')->debug(
                (isset($remoteSub) ? json_encode($remoteSub) : "") .
                "\n" .
                $e->__toString()
            );
            throw $e;
        } catch (\Throwable $e) {
            Logger::getLogger('merchant.subscription.log')->debug($e->__toString());
            throw new ShopifyAppSubscriptionException("Cannot check subscription.");
        }
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
            self::$subscriptionRepository = ObjectManager::getInstance()->get(ISubscriptionRepository::class);
        }
        return self::$subscriptionRepository;
    }

    /**
     * @return SearchCriteriaBuilder
     */
    private static function getSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return ObjectManager::getInstance()->create(SearchCriteriaBuilder::class);
    }

    private const APP_SUBSCRIPTION_FRAGMENT = <<<'QUERY'
    fragment AppSubscriptionFragment on AppSubscription {
        id name status test returnUrl trialDays createdAt
        lineItems {
            plan {
                pricingDetails {
                    ... on AppRecurringPricing {
                        interval price { amount }
                        discount {
                            durationLimitInIntervals priceAfterDiscount { amount }
                            value {
                                ... on AppSubscriptionDiscountAmount { amount { amount } }
                                ... on AppSubscriptionDiscountPercentage { percentage }
                            }
                        }
                    }
                }
            }
        }
    }
QUERY;


    private const RECURRING_PURCHASES_QUERY = <<<'QUERY'
    query appSubscription {
        currentAppInstallation {
            activeSubscriptions { ...AppSubscriptionFragment }
        }
    }
QUERY . "\n" .
    self::APP_SUBSCRIPTION_FRAGMENT;

    private const LATEST_SUBSCRIPTION_QUERY = <<<'QUERY'
query LatestSubscription {
    currentAppInstallation{
        allSubscriptions(first: 1, sortKey: CREATED_AT, reverse: true){
            nodes{ ...AppSubscriptionFragment }
        }
    }
}
QUERY. "\n" .
    self::APP_SUBSCRIPTION_FRAGMENT;
}
