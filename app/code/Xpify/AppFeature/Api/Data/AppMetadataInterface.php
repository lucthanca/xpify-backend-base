<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Api\Data;

interface AppMetadataInterface
{
    const ID = 'entity_id';
    const METADATA_KEY = 'metadata_key';
    const METADATA_VALUE = 'metadata_value';
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
     * Get metadata key
     *
     * @return string
     */
    public function getMetadataKey(): string;

    /**
     * Set metadata key
     *
     * @param string $metadataKey
     * @return self
     */
    public function setMetadataKey(string $metadataKey);

    /**
     * Get metadata value (JSON)
     *
     * @return array|null
     */
    public function getMetadataValue(): ?array;

    /**
     * Set metadata value (JSON)
     *
     * @param array|null $metadataValue
     * @return self
     */
    public function setMetadataValue(?array $metadataValue);

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
