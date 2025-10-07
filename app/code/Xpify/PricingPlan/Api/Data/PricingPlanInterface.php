<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Api\Data;

use Xpify\App\Api\Data\AppInterface;

interface PricingPlanInterface
{
    const BASE_CURRENCY = AppInterface::CURRENCY_CODE;
    const STATUS_ACTIVE = 1;
    const IS_DEFAULT = 1;

    const ID = 'entity_id';
    const STATUS = 'status';
    const NAME = 'name';
    const DESCRIPTION = 'description';
    const PRICE = 'price';
    const APP_ID = 'app_id';
    const SORT_ORDER = 'sort_order';
    const PRICES = 'prices';
    const BADGES = 'badges';
    const CODE = 'code';
    const SHORT_DESCRIPTION = 'short_description';
    const DEFAULT = 'default';

    const FREE_TRIAL_DAYS = 'free_trial_days';

    /**
     * Get default plan
     *
     * @return bool
     */
    public function getDefault(): bool;

    /**
     * Set default plan
     *
     * @param mixed $default
     * @return self
     */
    public function setDefault(string|int|bool $default): PricingPlanInterface;

    /**
     * Get plan code
     *
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * Set unique code
     *
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self;

    /**
     * Get status
     *
     * @return int|null
     */
    public function getStatus(): ?int;

    /**
     * Set status
     *
     * @param int $status
     * @return $this
     */
    public function setStatus(mixed $status): self;

    /**
     * Get name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Set description
     *
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self;

    /**
     * Get short description
     *
     * @return string|null
     */
    public function getShortDescription(): ?string;

    /**
     * Set short description
     *
     * @param string|null $shortDescription
     * @return $this
     */
    public function setShortDescription(?string $shortDescription): self;

    /**
     * Get free trial days
     *
     * @return int|null
     */
    public function getFreeTrialDays(): ?int;

    /**
     * Set free trial days
     *
     * @param int $freeTrialDays
     * @return $this
     */
    public function setFreeTrialDays(int $freeTrialDays): self;

    /**
     * Check if free trial is enabled
     *
     * @return bool
     */
    public function isEnableFreeTrial(): bool;

    /**
     * Get badges raw json string
     *
     * @param bool $parsed
     * @return string|null|array
     */
    public function getBadges(bool $parsed = true): null|string|array;

    /**
     * Set badges
     *
     * @param string|array|null $badges
     * @return $this
     */
    public function setBadges(string|array|null $badges): self;

    /**
     * Get sort order
     *
     * @return int|null
     */
    public function getSortOrder(): ?int;

    /**
     * Set sort order
     *
     * @param int|null $sortOrder
     * @return $this
     */
    public function setSortOrder(mixed $sortOrder): self;

    /**
     * Get app ID
     *
     * @return int|null
     */
    public function getAppId(): ?int;

    /**
     * Set app ID
     *
     * @param string $appId
     * @return $this
     */
    public function setAppId(mixed $appId): self;

    /**
     * Get interval price data
     *
     * @return array<int, array{ interval: string, amount: float, compare_at_price: float|null, final_amount?: float|null }>
     */
    public function getDataPrices(): array;

    /**
     * Check if interval price is exist
     * @param string $intervalKey
     * @return bool
     */
    public function hasIntervalPrice(string $intervalKey): bool;

    /**
     * Retrive interval price data by interval key
     *
     * @param string $key
     * @return array{ interval: string, amount: string|float, compare_at_price: string|float|null, final_amount?: string|float|null }|null
     */
    public function getIntervalPrice(string $key): ?array;

    /**
     * Set interval price data
     * @param array<int, array{ interval: string, amount: float, compare_at_price: float|null, final_amount?: float|null }> $prices
     * @return $this
     */
    public function setDataPrice(array $prices): self;

    /**
     * Get related app object
     *
     * @return AppInterface|null
     */
    public function getApp(): ?AppInterface;
}
