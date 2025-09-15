<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model;

use Magento\Framework\Model\AbstractModel;
use Xpify\AppFeature\Api\Data\MerchantFeatureInterface;

class MerchantFeature extends AbstractModel implements MerchantFeatureInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\MerchantFeature::class);
    }

    /**
     * @inheritDoc
     */
    public function getMerchantId(): int
    {
        return (int) $this->getData(self::MERCHANT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setMerchantId(int $merchantId): MerchantFeatureInterface
    {
        return $this->setData(self::MERCHANT_ID, $merchantId);
    }

    /**
     * @inheritDoc
     */
    public function getFeatureKey(): string
    {
        return (string) $this->getData(self::FEATURE_KEY);
    }

    /**
     * @inheritDoc
     */
    public function setFeatureKey(string $featureKey): MerchantFeatureInterface
    {
        return $this->setData(self::FEATURE_KEY, $featureKey);
    }

    /**
     * @inheritDoc
     */
    public function getEnabled(): bool
    {
        return (bool) $this->getData(self::ENABLED);
    }

    /**
     * @inheritDoc
     */
    public function setEnabled(bool $enabled): MerchantFeatureInterface
    {
        return $this->setData(self::ENABLED, $enabled);
    }

    /**
     * @inheritDoc
     */
    public function getReason(): ?string
    {
        return $this->getData(self::REASON);
    }

    /**
     * @inheritDoc
     */
    public function setReason(?string $reason): MerchantFeatureInterface
    {
        return $this->setData(self::REASON, $reason);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return (string) $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): MerchantFeatureInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return (string) $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): MerchantFeatureInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}