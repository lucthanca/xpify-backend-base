<?php
declare(strict_types=1);

namespace Xpify\Merchant\Api\Data;

use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;

/**
 * @phpstan-import-type AppSubscriptionDiscount from \Xpify\Core\Helper\Types
 */
interface MerchantSubscriptionInterface
{
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_EXPIRED = 'EXPIRED';
    const STATUS_DECLINED = 'DECLINED';
    const STATUS_DEACTIVATED = 'DEACTIVATED';
    const STATUS_FROZEN = 'FROZEN';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_PENDING = 'PENDING';

    const ID = 'entity_id';
    const SHOP = 'shop';
    const PLAN_ID = 'plan_id';
    const APP_ID = 'app_id';
    const CODE = 'code';
    const NAME = 'name';
    const TRIAL_DAYS = 'trial_days';
    const DESCRIPTION = 'description';
    const PRICE = 'price';
    const INTERVAL = 'interval';
    const CREATED_AT = 'created_at';
    const STATUS = 'status';
    const SUBSCRIPTION_ID = 'subscription_id';
    const CONFIRMATION_URL = 'confirmation_url';
    const APPLIED_DISCOUNT = 'applied_discount';

    public function getFinalPrice(): float;

    /**
     * @param bool $parse
     * @return string|AppSubscriptionDiscount|null
     */
    public function getAppliedDiscount(bool $parse = true): string|array|null;

    public function setAppliedDiscount(string|array|null $appliedDiscount): self;


    /**
     * Get confirmation URL
     *
     * @return string|null
     */
    public function getConfirmationUrl(): ?string;

    /**
     * Set confirmation URL
     *
     * @param string|null $confirmationUrl
     * @return MerchantSubscriptionInterface
     */
    public function setConfirmationUrl(?string $confirmationUrl): self;

    /**
     * Get trial days
     *
     * @return int
     */
    public function getTrialDays(): int;

    /**
     * Set trial days
     *
     * @param int $trialDays
     * @return MerchantSubscriptionInterface
     */
    public function setTrialDays(int $trialDays): self;

    /**
     * Get transaction status
     *
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * Get subscription id
     *
     * @return string|null
     */
    public function getSubscriptionId(): ?string;

    /**
     * Set transaction status
     *
     * @param string $status
     * @return MerchantSubscriptionInterface
     */
    public function setStatus(string $status): self;

    /**
     * Set subscription id
     *
     * @param string $subscriptionId
     * @return MerchantSubscriptionInterface
     */
    public function setSubscriptionId(string $subscriptionId): self;

    /**
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * @param mixed $value
     * @return MerchantSubscriptionInterface
     */
    public function setId(mixed $value): self;

    /**
     * @return int|null
     */
    public function getShop(): ?int;

    /**
     * @param string $name
     * @return MerchantSubscriptionInterface
     */
    public function setShop(string $name): self;

    /**
     * @return int|null
     */
    public function getPlanId(): ?int;

    /**
     * @param int $planId
     * @return MerchantSubscriptionInterface
     */
    public function setPlanId(int $planId): self;

    /**
     * @return int|null
     */
    public function getAppId(): ?int;

    /**
     * @param int $appId
     * @return MerchantSubscriptionInterface
     */
    public function setAppId(int $appId): self;

    /**
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * @param string $code
     * @return MerchantSubscriptionInterface
     */
    public function setCode(string $code): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string $name
     * @return MerchantSubscriptionInterface
     */
    public function setName(string $name): self;

    /**
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * @param string $description
     * @return MerchantSubscriptionInterface
     */
    public function setDescription(?string $description): self;

    /**
     * @return float
     */
    public function getPrice(): float;

    /**
     * @param float $price
     * @return MerchantSubscriptionInterface
     */
    public function setPrice(float $price): self;

    /**
     * @return string
     */
    public function getInterval(): string;

    /**
     * @param string $interval
     * @return MerchantSubscriptionInterface
     */
    public function setInterval(string $interval): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     * @return MerchantSubscriptionInterface
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * @return IMerchant|null
     * @throws NoSuchEntityException
     */
    public function getMerchant(): ?IMerchant;

    /**
     * Related pricing plan or readonly pricing plan (when current plan id not existed in database anymore)
     * @return IPricingPlan|null
     */
    public function getPlanPricing(): ?IPricingPlan;
}
