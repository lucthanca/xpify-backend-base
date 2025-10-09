<?php
declare(strict_types=1);

namespace Xpify\Merchant\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use SectionBuilder\Discount\Api\Data\CouponInterface as ICoupon;
use SectionBuilder\Discount\Api\Data\CouponUsageInterface as ICouponUsage;
use Shopify\Auth\Session;
use Shopify\Context;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\Core\Exception\ShopifyQueryException;
use Xpify\Core\Helper\Utils;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Exception\AlreadySubscribedException;
use Xpify\Merchant\Exception\ShopifyAppSubscriptionException;
use Xpify\Merchant\Exception\ShopifyBillingException;
use Xpify\Merchant\Helper\GraphqlQueryTrait;
use Xpify\Merchant\Helper\Subscription;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface as IPricingPlanRepository;
use Xpify\PricingPlan\Model\Source\IntervalType;

/**
 * @phpstan-import-type AppSubscription from \Xpify\Core\Helper\Types
 * @phpstan-import-type AppliedDiscount from \Xpify\PricingPlan\Model\PricingPlan
 * @phpstan-type AppPricingInterval 'ANNUAL'|'EVERY_30_DAYS'
 * @phpstan-type RecurringPaymentConfig array{
 *     chargeName: string,
 *     amount: float,
 *     currencyCode: 'USD',
 *     interval: AppPricingInterval,
 *     trialDays: int,
 *     return_url: string,
 *     discount?: array{
 *         durationLimitInIntervals?: int,
 *         value: array{
 *             amount?: float,
 *             percentage?: float
 *         }
 *     }|null
 * }
 */
class Billing
{
    use GraphqlQueryTrait;
    const E_ALREADY_SUBSCRIBED = 1;

    private MerchantStorage $merchantStorage;
    private static $logger = null;

    private static array $RECURRING_INTERVALS = [
        IntervalType::INTERVAL_EVERY_30_DAYS, IntervalType::INTERVAL_ANNUAL
    ];
    private static ?IApp $app = null;
    private IPricingPlanRepository $planRepository;

    /**
     * @param MerchantStorage $merchantStorage
     * @param IPricingPlanRepository $planRepository
     */
    public function __construct(
        MerchantStorage $merchantStorage,
        IPricingPlanRepository $planRepository
    ) {
        $this->merchantStorage = $merchantStorage;
        $this->planRepository = $planRepository;
    }

    /**
     * @param IMerchant $merchant
     * @param PricingPlanInterface $plan
     * @param string $interval
     * @return string|null
     * @throws NoSuchEntityException
     * @throws ShopifyBillingException
     * @throws AlreadySubscribedException
     */
    public function subscribePlan(IMerchant $merchant, PricingPlanInterface $plan, string $interval): ?string
    {
        if ($plan->getIntervalAmount($interval) <= 0.0) {
            throw new AlreadySubscribedException(__("The plan is free"));
        }
        $config = [
            'chargeName' => $plan->getName(),
            'amount' => $plan->getIntervalAmount($interval),
            'currencyCode' => \Xpify\App\Api\Data\AppInterface::CURRENCY_CODE,
            'interval' => $interval,
            'trialDays' => (int) $plan->getFreeTrialDays(),
        ];
        $activeSubscription = [];
        if ($this->hasActivePayment($merchant, $config, $activeSubscription)) {
            try {
                if (!empty($activeSubscription)) {
                    Subscription::ensureSubscription($merchant, $activeSubscription);
                }
            } catch (\Throwable $e) {
                \Xpify\Core\Model\Logger::getLogger('billing.ensure_db_subscription.log')->debug($e->__toString());
            }
            throw new AlreadySubscribedException(__("The merchant already has an active payment"), null, self::E_ALREADY_SUBSCRIBED);
        }
        $appUid = Utils::idToUid($merchant->getAppId() . "");
        $pUid = Utils::idToUid($plan->getId() . "");
        $payload = [
            '_mid' => $merchant->getId(),
            '_i' => $appUid,
            '_pid' => $pUid,
            '_interval' => $interval,
        ];
        $discounts = $plan->getAppliedDiscounts();
        /** @var AppliedDiscount $first */
        $first = $discounts ? reset($discounts) : null;
        if (!empty($first['applied_coupon'])) {
            $payload['_cp'] = $first['applied_coupon'];

            $config['discount'] = [];

            if (!empty($first['duration_limit_in_intervals'])) {
                $config['discount']['durationLimitInIntervals'] = $first['duration_limit_in_intervals'];
            }
            if ($first['discount_type'] === 'UNKNOWN') {
                self::getLogger()->debug(
                    'merchant = ' . $merchant->getShopName() . ', plan = ' . $plan->getName() . ', unknown discount type'
                );
                throw new ShopifyBillingException(__("Error while billing the store. Please contact us!")->render());
            }
            if ($first['discount_type'] === 'FIXED_AMOUNT') {
                $config['discount']['value'] = [
                    'amount' => $plan->getFinalIntervalAmount($interval),
                ];
            } else {
                $config['discount']['value'] = [
                    'percentage' => $first['discount_amount'] / 100.0,
                ];
            }
        }

        // sign
        $payload['_sign'] = \Xpify\Core\Helper\Utils::createHmac([
            'data' => $payload,
            'buildQuery' => true,
            'buildQueryWithJoin' => true,
        ], \Xpify\Core\Model\Constants::SYS_SECRET_KEY);

        // build return url
        $returnUrlBase = Context::$HOST_SCHEME . '://' . Context::$HOST_NAME . '/xpify/billing/success';
        $path = Utils::buildMagentoParamsPath(array_diff_key($payload, ['_interval' => true]));
        $config['return_url'] = $returnUrlBase . $path . '?_interval=' . rawurlencode($interval);

        /** @var AppSubscription $createdRemoteSub */
        [$billingUrl, $createdRemoteSub] = $this->requestPayment($merchant, $config);
        try {
            if ($createdRemoteSub) {
                $subscription = Subscription::newSubscription();
                $subscription->setShop($merchant->getShopName());
                $subscription->setPlanId((int) $plan->getId());
                $subscription->setAppId((int) $merchant->getAppId());
                $subscription->setCode($plan->getCode());
                $subscription->setDescription($plan->getDescription());
                $subscription->setName($plan->getName());
                $subscription->setPrice($plan->getIntervalAmount($interval));
                $subscription->setTrialDays((int) $plan->getFreeTrialDays());
                $subscription->setInterval($interval);
                $subscription->setStatus($createdRemoteSub['status']);
                $subscription->setSubscriptionId(str_replace(\Xpify\Core\Model\Constants::SHOPIFY_SUBSCRIPTION_GLOBAL_ID_PREFIX, '', $createdRemoteSub['id']));
                $subscription->setCreatedAt(date('Y-m-d H:i:s', strtotime($createdRemoteSub['createdAt'])));
                $discount = $createdRemoteSub['lineItems'][0]['plan']['pricingDetails']['discount'] ?? [];
                if (!empty($first['applied_coupon'])) {
                    $discount['coupon'] = $first['applied_coupon'];
                }
                $subscription->setAppliedDiscount($discount);
                $subscription->setConfirmationUrl($billingUrl);
                $subscription->save();

                try {
                    /** @var ICoupon $couponObj */
                    $couponObj = $first['coupon'];
                    if ($appliedCouponId = $couponObj->getId()) {
                        $couponUsage = \Magento\Framework\App\ObjectManager::getInstance()->create(ICouponUsage::class);
                        $couponUsage->loadUnique($appliedCouponId, $merchant->getShop());

                        if ($couponUsage->getId()) {
                            $couponUsage->setTimesUsed($couponUsage->getTimesUsed() + 1);
                        } else {
                            $couponUsage->setCouponId($appliedCouponId);
                            $couponUsage->setTimesUsed(1);
                            $couponUsage->setMerchantDomain($merchant->getShop());
                        }
                        $couponObj->setTimesUsed($couponObj->getTimesUsed() + 1);
                        $couponUsage->save();
                        $couponObj->save();
                    }
                } catch (\Throwable $e) {
                    if ($subscription->getId()) {
                        $subscription->delete();
                    }
                }
            }
        } catch (ShopifyBillingException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::getLogger()->debug($e->__toString());
            throw new ShopifyBillingException(__("Error while billing the store. Please contact us!")->render());
        }
        return $billingUrl;
    }

    /**
     * Check if the given session has an active payment if not request one.
     *
     * @param Session|Imerchant $object The current session or merchant to check
     * @return array Array containing
     * - hasPayment: bool
     * - confirmationUrl: string|null
     * @throws ShopifyBillingException|NoSuchEntityException
     */
    public function check(Session|Imerchant $object): array
    {
        $merchant = $object;
        if ($object instanceof Session) {
            $merchant = $this->merchantStorage->loadMerchantBySessionid($object->getId());
        }
        $subscription = Subscription::getSubscription($merchant);
        if (!$subscription?->getId()) {
            return [false, null];
        }
        $plan = $this->planRepository->get($subscription->getPlanId());
        try {
            $billingUrl = $this->subscribePlan($merchant, $plan, $subscription->getInterval());
            if ($billingUrl) {
                return [true, $billingUrl];
            }
        } catch (AlreadySubscribedException $e) {}

        return [false, null];
    }

    /**
     * Request a payment for the given merchant.
     *
     * @param IMerchant $merchant The current merchant to check
     * @param RecurringPaymentConfig   $config  Associative array that accepts keys:
     *                         - "chargeName": string, the name of the charge
     *                         - "amount": float
     *                         - "currencyCode": string
     *                         - "interval": one of the INTERVAL_* consts
     *
     * @return array - the confirmationUrl and the AppSubscription or AppPurchaseOneTime object
     * @throws ShopifyBillingException|NoSuchEntityException
     */
    public function requestPayment(IMerchant $merchant, array $config): array
    {
        $hostName = Context::$HOST_NAME;
        $shop = $merchant->getShop();
        $host = base64_encode("$shop/admin");
        $returnUrl = $config['return_url'] ?? "https://$hostName?shop={$shop}&host=$host";

        $objectKey = 'appSubscription';
        if (self::isRecurring($config)) {
            $data = self::requestRecurringPayment($merchant, $config, $returnUrl);
            $data = $data["data"]["appSubscriptionCreate"];
        } else {
            $data = self::requestOneTimePayment($merchant, $config, $returnUrl);
            $data = $data["data"]["appPurchaseOneTimeCreate"];
            $objectKey = "appPurchaseOneTime";
        }

        if (!empty($data["userErrors"])) {
            self::getLogger()->debug(__("User response error: %1", json_encode($data["userErrors"]))->render());
            throw new ShopifyBillingException("Error while billing the store. Please contact us!", $data["userErrors"]);
        }

        return [$data["confirmationUrl"], $data[$objectKey] ?? []];
    }

    /**
     * Check if the given merchant has an active payment
     *
     * @param IMerchant $merchant
     * @param RecurringPaymentConfig $config
     * @param array|null $remoteData
     * @return bool
     * @throws ShopifyBillingException
     * @throws NoSuchEntityException
     */
    public function hasActivePayment(IMerchant $merchant, array $config, array &$remoteData = null): bool
    {
        if (self::isRecurring($config)) {
            return self::hasSubscription($merchant, $config, $remoteData);
        } else {
            return self::hasOneTimePayment($merchant, $config);
        }
    }

    /**
     * Request a one time payment for the given merchant
     *
     * @param IMerchant $merchant
     * @param array $config
     * @param string $returnUrl
     * @return array
     * @throws ShopifyBillingException|NoSuchEntityException
     */
    private static function requestOneTimePayment(IMerchant $merchant, array $config, string $returnUrl): array
    {
        return self::queryOrException(
            $merchant,
            [
                "query" => self::ONE_TIME_PURCHASE_MUTATION,
                "variables" => [
                    "name" => $config["chargeName"],
                    "price" => ["amount" => $config["amount"], "currencyCode" => $config["currencyCode"]],
                    "returnUrl" => $returnUrl,
                    "test" => !self::getCurrentApp()->isProd(),
                ],
            ]
        );
    }

    /**
     * Request a recurring payment for the given merchant
     *
     * @param IMerchant $merchant
     * @param RecurringPaymentConfig $config
     * @param string $returnUrl
     * @return array
     * @throws ShopifyBillingException|NoSuchEntityException
     */
    private static function requestRecurringPayment(IMerchant $merchant, array $config, string $returnUrl): array
    {
        $appRecurringPricingDetails = [
            "interval" => $config["interval"],
            "price" => ["amount" => $config["amount"], "currencyCode" => $config["currencyCode"]],
        ];
        if (!empty($config['discount'])) {
            $appRecurringPricingDetails['discount'] = $config['discount'];
        }
        return self::queryOrException(
            $merchant,
            [
                "query" => self::RECURRING_PURCHASE_MUTATION,
                "variables" => [
                    "name" => $config["chargeName"],
                    "lineItems" => [
                        "plan" => [
                            "appRecurringPricingDetails" => $appRecurringPricingDetails,
                        ],
                    ],
                    "returnUrl" => $returnUrl,
                    "test" => !self::getCurrentApp()->isProd(),
                    "trialDays" => $config["trialDays"] ?? 0
                ],
            ]
        );
    }

    /**
     * @param IMerchant $merchant
     * @param string|null $chargeName
     * @return array
     * @throws NoSuchEntityException
     * @throws ShopifyBillingException
     */
    public static function getActiveOneTimePurchases(IMerchant $merchant, string $chargeName = null): array
    {
        $items = [];
        self::getOneTimePurchases($merchant, function (array $node) use (&$items, $chargeName) {
            if (
                ($chargeName === null || $node["name"] === $chargeName) &&
                (!self::getCurrentApp()->isProd() || !$node["test"]) &&
                $node["status"] === "ACTIVE"
            ) {
                $items[] = $node;
            }
            return false;
        });
        return $items;
    }

    /**
     * @param IMerchant $merchant
     * @param callable $handler - A function that accepts a node array and returns a boolean to whether to stop the loop
     * @return void
     * @throws ShopifyBillingException
     */
    public static function getOneTimePurchases(IMerchant $merchant, callable $handler): void
    {
        $endCursor = null;
        do {
            $responseBody = self::queryOrException($merchant, [
                "query" => self::ONE_TIME_PURCHASES_QUERY,
                "variables" => ["endCursor" => $endCursor]
            ]);
            $purchases = $responseBody["data"]["currentAppInstallation"]["oneTimePurchases"];
            foreach ($purchases["edges"] as $purchase) {
                $shouldStop = $handler($purchase["node"]);
                if ($shouldStop) {
                    return;
                }
            }
            $endCursor = $purchases["pageInfo"]["endCursor"];
        } while ($purchases["pageInfo"]["hasNextPage"]);
    }

    /**
     * Check merchant has one time payment
     *
     * @param IMerchant $merchant
     * @param array $config
     * @return bool
     * @throws ShopifyBillingException|NoSuchEntityException
     */
    private static function hasOneTimePayment(IMerchant $merchant, array $config): bool
    {
        $hasOneTimePayment = false;
        self::getOneTimePurchases($merchant, function (array $node) use ($config, &$hasOneTimePayment) {
            if (
                $node["name"] === $config["chargeName"] &&
                (!self::getCurrentApp()->isProd() || !$node["test"]) &&
                $node["status"] === "ACTIVE"
            ) {
                $hasOneTimePayment = true;
                return true;
            }
            return false;
        });
        return $hasOneTimePayment;
    }

    /**
     * Check merchant has subscription
     *
     * @param IMerchant $merchant
     * @param RecurringPaymentConfig $config
     * @param array|null $activeSubscription - This will be filled with the active subscription details if found
     * @return bool
     * @throws ShopifyBillingException|NoSuchEntityException
     */
    public static function hasSubscription(IMerchant $merchant, array $config, array &$activeSubscription = null): bool
    {
        $remoteActiveSub = self::queryOrException($merchant, self::RECURRING_PURCHASES_QUERY)["data"]["currentAppInstallation"]["activeSubscriptions"][0] ?? null;

        /** @var AppSubscription $remoteSub */
        $remoteSub = $remoteActiveSub;
        unset($remoteActiveSub);
        // Check xem co subscription active trên Shopify không
        if (!$remoteSub) {
            // Neu khong co thi check xem co subscription frozen tren shopify khong
            $remoteFrozenSub = self::queryOrException($merchant, self::LATEST_SUBSCRIPTION_QUERY)["data"]["currentAppInstallation"]['allSubscriptions']['nodes'][0] ?? null;
            if (!$remoteFrozenSub || $remoteFrozenSub['status'] !== 'FROZEN') {
                return false;
            }
            $remoteSub = $remoteFrozenSub;
            unset($remoteFrozenSub);
        }

        $returnUrlRequest = \Xpify\Core\Helper\Utils::parseRequest($remoteSub['returnUrl']);
        $intervalEqual = $config["interval"] === $returnUrlRequest->getParam('_interval');

        if ($intervalEqual && $remoteSub["name"] === $config["chargeName"]
//            && (!self::getCurrentApp()->isProd() || !$remoteSub["test"])
        ) {
            if ($activeSubscription !== null) {
                $activeSubscription = $remoteSub;
            }
            return true;
        }

        return false;
    }

    public static function getOneTimePayment(Imerchant $m, string $id): array
    {
        return self::query($m, [
            "query" => self::GET_PURCHASED_ONETIME_QUERY,
            "variables" => ["id" => $id]
        ]);
    }

    /**
     * Query graphql or throw exception
     *
     * @param IMerchant $merchant
     * @param string|array $query
     * @return array
     * @throws ShopifyBillingException
     */
    private static function queryOrException(IMerchant $merchant, string|array $query): array
    {
        try {
            return self::query($merchant, $query);
        } catch (ShopifyQueryException $e) {
            self::getLogger()?->debug(
                __(
                    "Error while billing the store merchant ID %1. The original message: %2. Trace: %3",
                    $merchant->getId(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                )->render()
            );
            throw new ShopifyBillingException("Error while billing the store", [$e->getMessage()]);
        }
    }

    /**
     * Check if the given config is recurring
     *
     * @param array $config
     * @return bool
     */
    private static function isRecurring(array $config): bool
    {
        return in_array($config["interval"], self::$RECURRING_INTERVALS);
    }

    /**
     * Get context app
     *
     * @return IApp
     * @throws NoSuchEntityException
     */
    private static function getCurrentApp(): IApp
    {
        if (!self::$app) {
            self::$app = \Magento\Framework\App\ObjectManager::getInstance()->get(\Xpify\App\Service\GetCurrentApp::class)->get();
        }
        if (self::$app === null) {
            throw new NoSuchEntityException(__("No App defined!"));
        }
        return self::$app;
    }

    /**
     * Custom logger for billing
     *
     * @return \Zend_Log|null
     */
    private static function getLogger(): ?\Zend_Log
    {
        try {
            if (!self::$logger) {
                $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/app_billing.log');
                $logger = new \Zend_Log();
                $logger->addWriter($writer);
                self::$logger = $logger;
            }
            return self::$logger;
        } catch (\Throwable $e) {
            return null;
        }
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

    private const RECURRING_PURCHASES_QUERY = <<<'QUERY'
query appSubscription {
    currentAppInstallation {
        activeSubscriptions { ...AppSubscriptionFragment }
    }
}
QUERY . "\n" .
    self::APP_SUBSCRIPTION_FRAGMENT;

    private const ONE_TIME_PURCHASES_QUERY = <<<'QUERY'
    query appPurchases($endCursor: String) {
        currentAppInstallation {
            oneTimePurchases(first: 250, sortKey: CREATED_AT, after: $endCursor) {
                edges {
                    node {
                        id, name, test, status
                    }
                }
                pageInfo {
                    hasNextPage, endCursor
                }
            }
        }
    }
    QUERY;
    private const RECURRING_PURCHASE_MUTATION = <<<'QUERY'
mutation createPaymentMutation(
    $name: String!
    $lineItems: [AppSubscriptionLineItemInput!]!
    $returnUrl: URL!
    $test: Boolean
    $trialDays: Int
) {
    appSubscriptionCreate(
        name: $name
        lineItems: $lineItems
        returnUrl: $returnUrl
        test: $test
        trialDays: $trialDays
    ) {
        appSubscription { ...AppSubscriptionFragment }
        confirmationUrl
        userErrors { field, message }
    }
}
QUERY . "\n" .
    self::APP_SUBSCRIPTION_FRAGMENT;

    private const ONE_TIME_PURCHASE_MUTATION = <<<'QUERY'
    mutation createPaymentMutation(
        $name: String!
        $price: MoneyInput!
        $returnUrl: URL!
        $test: Boolean
    ) {
        appPurchaseOneTimeCreate(
            name: $name
            price: $price
            returnUrl: $returnUrl
            test: $test
        ) {
            appPurchaseOneTime { id status createdAt }
            confirmationUrl
            userErrors {
                field, message
            }
        }
    }
    QUERY;
    private const GET_PURCHASED_ONETIME_QUERY = <<<'QUERY'
    query QueryOnetimePurchase($id: ID!) {
      node(id: $id) {
        ... on AppPurchaseOneTime {
          price {
            amount
            currencyCode
          }
          createdAt
          id
          name
          status
          test
        }
      }
    }
    QUERY;
}
