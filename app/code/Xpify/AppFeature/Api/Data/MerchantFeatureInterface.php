<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Api\Data;

interface MerchantFeatureInterface
{
    const ID = 'entity_id';
    const MERCHANT_ID = 'merchant_id';
    const FEATURE_KEY = 'feature_key';
    const ENABLED = 'enabled';
    const REASON = 'reason';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Get entity ID
     *
     * @return int|null
     */
    public function getId();

    /**
     * Set entity ID
     *
     * @param int $id
     * @return self
     */
    public function setId($id);

    /**
     * Get merchant ID
     *
     * @return int
     */
    public function getMerchantId(): int;

    /**
     * Set merchant ID
     *
     * @param int $merchantId
     * @return self
     */
    public function setMerchantId(int $merchantId);

    /**
     * Get feature key
     *
     * @return string
     */
    public function getFeatureKey(): string;

    /**
     * Set feature key
     *
     * @param string $featureKey
     * @return self
     */
    public function setFeatureKey(string $featureKey);

    /**
     * Get enabled status
     *
     * @return bool
     */
    public function getEnabled(): bool;

    /**
     * Set enabled status
     *
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled);

    /**
     * Get reason
     *
     * @return string|null
     */
    public function getReason(): ?string;

    /**
     * Set reason
     *
     * @param string|null $reason
     * @return self
     */
    public function setReason(?string $reason);

    /**
     * Get created at
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set created at
     *
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt);

    /**
     * Get updated at
     *
     * @return string
     */
    public function getUpdatedAt(): string;

    /**
     * Set updated at
     *
     * @param string $updatedAt
     * @return self
     */
    public function setUpdatedAt(string $updatedAt);
}