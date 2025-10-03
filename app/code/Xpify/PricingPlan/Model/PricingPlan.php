<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Xpify\App\Api\AppRepositoryInterface;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface;

class PricingPlan extends AbstractModel implements PricingPlanInterface
{
    /**
     * @var array<int, array{ interval: string, amount: float, compare_at_price: float|null }>
     */
    private array $decodedPrices = [];
    private ?IApp $app = null;

    private AppRepositoryInterface $appRepository;

    /**
     * @param AppRepositoryInterface $appRepository
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Xpify\App\Api\AppRepositoryInterface $appRepository,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->appRepository = $appRepository;
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\PricingPlan::class);
    }

    /**
     * @inheritDoc
     */
    public function getDefault(): bool
    {
        $default = $this->getData(self::DEFAULT);
        if (is_string($default)) {
            return filter_var($default, FILTER_VALIDATE_BOOLEAN);
        }
        return (bool) $default;
    }

    /**
     * @inheritDoc
     */
    public function setDefault(bool|int|string $default): PricingPlanInterface
    {
        if (is_bool($default) || is_int($default)) {
            $default = (int) $default;
        } elseif (is_string($default)) {
            $default = filter_var($default, FILTER_VALIDATE_BOOLEAN);
        }
        return $this->setData(self::DEFAULT, $default);
    }

    /**
     * @inheritDoc
     */
    public function getCode(): ?string
    {
        return $this->getData(self::CODE);
    }

    /**
     * @inheritDoc
     */
    public function setCode(string $code): self
    {
        return $this->setData(self::CODE, $code);
    }

    public function getStatus(): ?int
    {
        return (int) $this->getData(self::STATUS);
    }

    public function setStatus(mixed $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    public function setDescription(?string $description): self
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getShortDescription(): ?string
    {
        return $this->getData(self::SHORT_DESCRIPTION);
    }
    public function setShortDescription(?string $shortDescription): self
    {
        return $this->setData(self::SHORT_DESCRIPTION, $shortDescription);
    }

    /**
     * Get interval price as array
     *
     * array structure:
     * [
     *  [ interval: "ONE_TIME|EVERY_30_DAYS|ANNUAL", amount: 0.00 ]
     * ]
     * @return array<int, array{ interval: string, amount: float, compare_at_price: float|null }>
     */
    public function getDataPrices(): array
    {
        if (empty($this->decodedPrices)) {
            try {
                $this->decodedPrices = json_decode($this->getData(self::PRICES), true);
                // mapping for compare_at_price, ensure that value must be float
                $this->decodedPrices = array_map(fn ($price) => [
                    'interval' => $price['interval'],
                    'amount' => (float) $price['amount'],
                    'compare_at_price' => isset($price['compare_at_price']) ? (float) $price['compare_at_price'] : null,
                    'final_amount' => isset($price['final_amount']) ? (float) $price['final_amount'] : null,
                ], $this->decodedPrices);
            } catch (\Exception $e) {
                $this->decodedPrices = [];
            }
        }
        return $this->decodedPrices;
    }

    /**
     * Check if interval price is exist
     *
     * @param string $intervalKey
     * @return bool
     */
    public function hasIntervalPrice(string $intervalKey) : bool
    {
        // check if interval is exist in getDataPrices
        return in_array($intervalKey, array_column($this->getDataPrices(), 'interval'));
    }

    /**
     * @inheritDoc
     */
    public function setDataPrice(array $prices) : PricingPlanInterface
    {
        $this->decodedPrices = $prices;
        return $this->setData(self::PRICES, json_encode($prices));
    }

    /**
     * @inheritDoc
     */
    public function getIntervalPrice(string $key): ?array
    {
        $prices = $this->getDataPrices();
        foreach ($prices as $price) {
            if ($price['interval'] === $key) {
                return $price;
            }
        }
        return null;
    }

    /**
     * Retrive amount by interval
     *
     * @param string $interval
     * @return float
     */
    public function getIntervalAmount(string $interval): float
    {
        return (float) $this->getIntervalPrice($interval)['amount'] ?? 0.0;
    }

    public function getSortOrder(): ?int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(mixed $sortOrder): self
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }

    public function getAppId(): ?int
    {
        return (int) $this->getData(self::APP_ID);
    }

    public function setAppId(mixed $appId): self
    {
        return $this->setData(self::APP_ID, $appId);
    }

    /**
     * @inheritDoc
     */
    public function getApp(): ?IApp
    {
        if ($this->app && $this->app->getId()) {
            return $this->app;
        }
        if ($this->getAppId()) {
            try {
                $this->app = $this->appRepository->get($this->getAppId());
                if (!$this->app->getId()) {
                    return null;
                }
            } catch (NoSuchEntityException $e) {
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getFreeTrialDays(): ?int
    {
        $freeTrialDays = (int) $this->getData(self::FREE_TRIAL_DAYS);
        return $freeTrialDays > 0 ? $freeTrialDays : null;
    }

    /**
     * @inheritDoc
     */
    public function setFreeTrialDays(int $freeTrialDays): PricingPlanInterface
    {
        if ($freeTrialDays > 0) {
            $this->setData(self::FREE_TRIAL_DAYS, $freeTrialDays);
        } else {
            $this->unsetData(self::FREE_TRIAL_DAYS);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isEnableFreeTrial(): bool
    {
        $freeTrialDays = $this->getFreeTrialDays();
        return $freeTrialDays !== null && $freeTrialDays > 0;
    }

    /**
     * @inheritDoc
     */
    public function getBadges(bool $parsed = true): null|string|array
    {
        $badges = $this->getData(self::BADGES);
        if ($parsed && is_string($badges)) {
            try {
                $badges = json_decode($badges, true);
            } catch (\Exception $e) {
                $badges = null;
            }
        }
        return $badges;
    }

    /**
     * @inheritDoc
     */
    public function setBadges(array|string|null $badges): PricingPlanInterface
    {
        if (is_array($badges)) {
            $badges = json_encode($badges);
        } elseif (is_string($badges) && empty($badges)) {
            $badges = null;
        }
        return $this->setData(self::BADGES, $badges);
    }
}
