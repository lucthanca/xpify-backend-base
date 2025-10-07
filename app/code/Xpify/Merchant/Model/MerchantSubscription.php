<?php
declare(strict_types=1);

namespace Xpify\Merchant\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Model\Source\IntervalType;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface as IPricingPlanRepository;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;

class MerchantSubscription extends AbstractModel implements ISubscription
{
    private ?IMerchant $merchant = null;
    private ?IPricingPlan $planPricing = null;
    private IPricingPlanRepository $pricingPlanRepository;
    private IMerchantRepository $merchantRepository;

    /**
     * @param IPricingPlanRepository $pricingPlanRepository
     * @param IMerchantRepository $merchantRepository
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        IPricingPlanRepository $pricingPlanRepository,
        IMerchantRepository $merchantRepository,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->pricingPlanRepository = $pricingPlanRepository;
        $this->merchantRepository = $merchantRepository;
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\MerchantSubscription::class);
    }
    /**
     * @inheritDoc
     */
    public function getId() : ?int
    {
        $id = $this->getData(self::ID);
        return isset($id) ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setId(mixed $value): ISubscription
    {
        if ($value !== null) {
            $value = (int) $value;
        }
        return $this->setData(self::ID, $value);
    }

    /**
     * @inheritDoc
     */
    public function getMerchantId(): ?int
    {
        return (int) $this->getData(self::MERCHANT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setMerchantId(int $merchantId): ISubscription
    {
        return $this->setData(self::MERCHANT_ID, $merchantId);
    }

    /**
     * @inheritDoc
     */
    public function getPlanId(): ?int
    {
        return (int) $this->getData(self::PLAN_ID);
    }

    /**
     * @inheritDoc
     */
    public function setPlanId(int $planId): ISubscription
    {
        return $this->setData(self::PLAN_ID, $planId);
    }

    /**
     * @inheritDoc
     */
    public function getAppId(): ?int
    {
        return (int) $this->getData(self::APP_ID);
    }

    /**
     * @inheritDoc
     */
    public function setAppId(int $appId): ISubscription
    {
        return $this->setData(self::APP_ID, $appId);
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
    public function setCode(string $code): ISubscription
    {
        return $this->setData(self::CODE, $code);
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(string $name): ISubscription
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * @inheritDoc
     */
    public function setDescription(?string $description): ISubscription
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @inheritDoc
     */
    public function getPrice(): float
    {
        return (float) $this->getData(self::PRICE);
    }

    /**
     * @inheritDoc
     */
    public function setPrice(float $price): ISubscription
    {
        return $this->setData(self::PRICE, $price);
    }

    /**
     * @inheritDoc
     */
    public function getInterval(): string
    {
        return $this->getData(self::INTERVAL);
    }

    /**
     * @inheritDoc
     */
    public function setInterval(string $interval): ISubscription
    {
        if (!IntervalType::isValidInterval($interval)) {
            throw new \InvalidArgumentException('Invalid interval');
        }
        return $this->setData(self::INTERVAL, $interval);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): ISubscription
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getMerchant(): ?IMerchant
    {
        try {
            if (!$this->merchant) {
                $this->merchant = $this->merchantRepository->getById($this->getMerchantId());
            }
        } catch (\Exception $e) {
            $this->_logger->debug($e);
            throw new NoSuchEntityException(__("Can not load merchant information"));
        }
        return $this->merchant;
    }

    /**
     * @inheritDoc
     */
    public function getPlanPricing(): ?IPricingPlan
    {
        try {
            if (!$this->planPricing) {
                $this->planPricing = $this->pricingPlanRepository->get($this->getPlanId());
            }
        } catch (NoSuchEntityException $e) {
            $this->planPricing = $this->pricingPlanRepository->create();
            $this->planPricing->setAppId($this->getAppId());
            $this->planPricing->setCode($this->getCode());
            $this->planPricing->setName($this->getName());
            $this->planPricing->setDescription($this->getDescription());
            $this->planPricing->setStatus(true);
            $this->planPricing->setDataPrice([
                [
                    'interval' => $this->getInterval(),
                    'amount' => $this->getPrice(),
                    'record_id' => 0,
                ]
            ]);
            $this->planPricing->setReadOnly(true);
        } catch (\Exception $e) {
            $this->_logger->debug($e);
            throw new NoSuchEntityException(__("Can not load plan information"));
        }

        return $this->planPricing;
    }

    /**
     * @inheritDoc
     */
    public function getStatus() : ?string
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getSubscriptionId(): ?string
    {
        return $this->getData(self::SUBSCRIPTION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setSubscriptionId(string $subscriptionId): self
    {
        return $this->setData(self::SUBSCRIPTION_ID, $subscriptionId);
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationUrl(): ?string
    {
        return $this->getData(self::CONFIRMATION_URL);
    }

    /**
     * @inheritDoc
     */
    public function setConfirmationUrl(?string $confirmationUrl): ISubscription
    {
        return $this->setData(self::CONFIRMATION_URL, $confirmationUrl);
    }

    /**
     * @inheritDoc
     */
    public function getTrialDays(): int
    {
        $trialDays = $this->getData(self::TRIAL_DAYS);
        return isset($trialDays) ? (int) $trialDays : 0;
    }

    /**
     * @inheritDoc
     */
    public function setTrialDays(int $trialDays): ISubscription
    {
        if ($trialDays < 0) {
            $trialDays = 0;
        }
        return $this->setData(self::TRIAL_DAYS, $trialDays);
    }

    /**
     * Get final price after discount
     *
     * @return float
     */
    public function getFinalPrice(): float
    {
        $appliedDiscount = $this->getAppliedDiscount();
        $price = $this->getPrice();
        if (is_array($appliedDiscount) && isset($appliedDiscount['priceAfterDiscount']['amount'])) {
            $price = (float) $appliedDiscount['priceAfterDiscount']['amount'];
        }
        return $price;
    }

    /**
     * @param bool $parse
     * @return string|array{ durationLimitInIntervals: int, remainingDurationInIntervals: int, priceAfterDiscount: array{ amount: float }, value: array{ amount: array{ amount: float } }|array{ percentage: float } }|null
     */
    public function getAppliedDiscount(bool $parse = true): string|array|null
    {
        $appliedDiscount = $this->getData(self::APPLIED_DISCOUNT);
        if ($parse && is_string($appliedDiscount)) {
            try {
                $appliedDiscount = json_decode($appliedDiscount, true);
            } catch (\Exception $e) {
                $appliedDiscount = null;
            }
        }
        return $appliedDiscount;
    }

    public function setAppliedDiscount(array|string|null $appliedDiscount): ISubscription
    {
        if ($appliedDiscount !== null) {
            if (is_array($appliedDiscount)) {
                $appliedDiscount = json_encode($appliedDiscount);
            }
            if (!is_string($appliedDiscount)) {
                throw new \InvalidArgumentException('Invalid applied discount format');
            }
        }
        return $this->setData(self::APPLIED_DISCOUNT, $appliedDiscount);
    }
}
