<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Service;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use SectionBuilder\Discount\Api\ApplyCouponInterface;
use SectionBuilder\Discount\Exception\CouponNotMetException;
use SectionBuilder\Discount\Exception\InvalidDateRangeException;
use SectionBuilder\Discount\Exception\MerchantConditionNotMetException;
use SectionBuilder\Discount\Exception\PerCouponUsageLimitException;
use SectionBuilder\Discount\Exception\PerStoreUsageLimitException;
use Xpify\Merchant\Api\Data\MerchantInterface;
use Xpify\Merchant\Exception\AlreadySubscribedException;
use Xpify\Merchant\Exception\ShopifyBillingException;
use Xpify\Merchant\Service\Billing;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface;

/**
 * Service for handling subscription logic with coupon support
 *
 * This service acts as an application layer between GraphQL resolvers and domain services,
 * coordinating the discount application and subscription creation process.
 */
class SubscriptionService
{
    private EventManager $eventManager;
    private ApplyCouponInterface $applyCoupon;
    private Billing $billing;
    private \Psr\Log\LoggerInterface $logger;

    /**
     * @param EventManager $eventManager
     * @param ApplyCouponInterface $applyCoupon
     * @param Billing $billing
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        EventManager $eventManager,
        ApplyCouponInterface $applyCoupon,
        Billing $billing,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->eventManager = $eventManager;
        $this->applyCoupon = $applyCoupon;
        $this->billing = $billing;
        $this->logger = $logger;
    }

    /**
     * Subscribe merchant to plan with optional coupon
     *
     * This method orchestrates the subscription flow:
     * 1. Apply coupon if provided (validates and calculates discount)
     * 2. Dispatch event for observers to hook into
     * 3. Create subscription with Shopify (with discount if applicable)
     *
     * @param MerchantInterface $merchant The merchant subscribing to the plan
     * @param PricingPlanInterface $plan The pricing plan to subscribe to
     * @param string $interval Subscription interval (e.g., 'EVERY_30_DAYS', 'ANNUAL')
     * @param string|null $couponCode Optional coupon code to apply
     * @return array Returns ['confirmation_url' => string|null, 'discount_applied' => array|null]
     * @throws NoSuchEntityException
     * @throws ShopifyBillingException
     * @throws AlreadySubscribedException
     */
    public function subscribe(
        MerchantInterface $merchant,
        PricingPlanInterface $plan,
        string $interval,
        ?string $couponCode = null
    ): array {
        $discountConfig = null;

        // Apply coupon if provided
        if ($couponCode && trim($couponCode) !== '') {
            $discountConfig = $this->applyDiscountCode(
                $merchant,
                $plan,
                trim($couponCode)
            );
        }

        // Dispatch event BEFORE subscribing
        // This allows observers to modify discount config or perform other actions
        $this->eventManager->dispatch('xpify_merchant_before_subscribe_plan', [
            'merchant' => $merchant,
            'plan' => $plan,
            'interval' => $interval,
            'coupon' => $couponCode,
            'discount_config' => &$discountConfig, // Pass by reference to allow modification
        ]);

        // Subscribe with discount config
        // NOTE: subscribePlanWithDiscount method will be added to Billing service in next phase
        // For now, this will cause a compile error until that method is implemented
        $confirmationUrl = $this->billing->subscribePlanWithDiscount(
            $merchant,
            $plan,
            $interval,
            $discountConfig
        );

        return [
            'confirmation_url' => $confirmationUrl,
            'discount_applied' => $discountConfig,
        ];
    }

    /**
     * Apply discount code and return Shopify-compatible config
     *
     * This method:
     * 1. Uses the existing ApplyCoupon service to validate and apply the coupon
     * 2. Extracts the discount information from the modified plan
     * 3. Converts it to Shopify's AppSubscriptionDiscountInput format
     *
     * If any error occurs during coupon application, it logs the error but returns null
     * instead of throwing an exception. This ensures that subscription can proceed
     * without discount if the coupon fails to apply.
     *
     * @param MerchantInterface&\Magento\Framework\Model\AbstractModel $merchant
     * @param PricingPlanInterface&\Magento\Framework\Model\AbstractModel $plan
     * @param string $couponCode
     * @return array|null Shopify discount config or null if coupon application failed
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function applyDiscountCode(
        MerchantInterface $merchant,
        PricingPlanInterface $plan,
        string $couponCode
    ): ?array {
        try {
            // Use existing ApplyCoupon service
            // This will validate the coupon and apply discount to the plan
            // Note: ApplyCoupon returns the modified plan with applied_discounts data
            // Both MerchantInterface and PricingPlanInterface extend AbstractModel which has getId()
            /** @var \Magento\Framework\Model\AbstractModel $pricingPlan */
            $pricingPlan = $this->applyCoupon->execute(
                $merchant,
                'pricing_plan', // Subject type for pricing plan strategy
                $plan, // Pass plan object - ApplyCoupon accepts AbstractModel
                $couponCode
            );

            // Extract applied discount from plan (getData is from AbstractModel)
            /** @var array|null $appliedDiscounts */
            $appliedDiscounts = $pricingPlan->getData('applied_discounts');
            if (empty($appliedDiscounts)) {
                $this->logger->warning('Coupon applied but no discount data found', [
                    'merchant_id' => $merchant->getData('entity_id'),
                    'shop' => $merchant->getShop(),
                    'coupon_code' => $couponCode,
                    'plan_id' => $plan->getData('entity_id'),
                ]);
                return null;
            }

            // Get the first discount (assuming one coupon per subscription)
            $discount = is_array($appliedDiscounts) ? $appliedDiscounts[0] : $appliedDiscounts;

            // Convert to Shopify format
            $shopifyConfig = $this->convertToShopifyDiscountConfig($discount);

            // Log successful application
            $this->logger->info('Coupon applied successfully', [
                'merchant_id' => $merchant->getData('entity_id'),
                'shop' => $merchant->getShop(),
                'coupon_code' => $couponCode,
                'plan_id' => $plan->getData('entity_id'),
                'discount_type' => $discount['discount_type'] ?? 'unknown',
                'discount_amount' => $discount['discount_amount'] ?? null,
            ]);

            return $shopifyConfig;

        } catch (CouponNotMetException $e) {
            // Specific coupon validation errors - log as warning
            $this->logger->warning('Coupon validation failed', [
                'merchant_id' => $merchant->getData('entity_id'),
                'shop' => $merchant->getShop(),
                'coupon_code' => $couponCode,
                'reason' => $e->getMessage(),
            ]);
            return null;

        } catch (InvalidDateRangeException|MerchantConditionNotMetException|PerCouponUsageLimitException|PerStoreUsageLimitException $e) {
            // Business rule violations - log as info (expected validation)
            $this->logger->info('Coupon cannot be applied', [
                'merchant_id' => $merchant->getData('entity_id'),
                'shop' => $merchant->getShop(),
                'coupon_code' => $couponCode,
                'reason' => $e->getMessage(),
                'exception_type' => get_class($e),
            ]);
            return null;

        } catch (\Throwable $e) {
            // Unexpected errors - log as error but don't block subscription
            $this->logger->error('Unexpected error applying coupon', [
                'merchant_id' => $merchant->getData('entity_id'),
                'shop' => $merchant->getShop(),
                'coupon_code' => $couponCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Convert internal discount format to Shopify AppSubscriptionDiscountInput
     *
     * Shopify expects discount in this format:
     * {
     *   "value": {
     *     "percentage": 20.0  // for percentage discount
     *     // OR
     *     "amount": 10.00     // for fixed amount discount
     *   },
     *   "durationLimitInIntervals": 12  // optional
     * }
     *
     * @param array $discount Internal discount data from applied_discounts
     * @return array Shopify-compatible discount configuration
     * @throws LocalizedException
     */
    private function convertToShopifyDiscountConfig(array $discount): array
    {
        $config = [];

        // Determine discount type and set value
        $discountType = $discount['discount_type'] ?? null;
        $discountAmount = $discount['discount_amount'] ?? null;

        if (!$discountType || $discountAmount === null) {
            throw new LocalizedException(
                __('Invalid discount data: missing discount_type or discount_amount')
            );
        }

        switch (strtoupper($discountType)) {
            case 'PERCENTAGE':
                $config['value'] = [
                    'percentage' => (float) $discountAmount // e.g. 20.0 for 20%
                ];
                break;

            case 'FIXED_AMOUNT':
            case 'FIXED':
                $config['value'] = [
                    'amount' => (float) $discountAmount // e.g. 10.00
                ];
                break;

            default:
                throw new LocalizedException(
                    __('Unsupported discount type: %1', $discountType)
                );
        }

        // Optional: Set discount duration (how many billing cycles the discount applies)
        if (isset($discount['duration_limit']) && $discount['duration_limit'] > 0) {
            $config['durationLimitInIntervals'] = (int) $discount['duration_limit'];
        }

        // Store the coupon code for tracking purposes
        if (isset($discount['coupon_code'])) {
            $config['applied_coupon'] = $discount['coupon_code'];
        }

        return $config;
    }
}
