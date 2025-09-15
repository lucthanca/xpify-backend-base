<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Api\Data;

interface FeatureInterface
{
    const ID = 'entity_id';
    const FEATURE_KEY = 'feature_key';
    const FEATURE_NAME = 'feature_name';
    const DESCRIPTION = 'description';
    const REQUIRED_VERSION = 'required_version';
    const IS_ENABLED_BY_DEFAULT = 'is_enabled_by_default';
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
     * Get feature key
     *
     * @return string
     */
    public function getFeatureKey();

    /**
     * Set feature key
     *
     * @param string $featureKey
     * @return self
     */
    public function setFeatureKey(string $featureKey);

    /**
     * Get feature name
     *
     * @return string
     */
    public function getFeatureName();

    /**
     * Set feature name
     *
     * @param string $featureName
     * @return self
     */
    public function setFeatureName(string $featureName);

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription();

    /**
     * Set description
     *
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description);

    /**
     * Get required version
     *
     * @return string|null
     */
    public function getRequiredVersion();

    /**
     * Set required version
     *
     * @param string|null $requiredVersion
     * @return self
     */
    public function setRequiredVersion(?string $requiredVersion);

    /**
     * Get is enabled by default status
     *
     * @return bool
     */
    public function getIsEnabledByDefault();

    /**
     * Set is enabled by default status
     *
     * @param bool $isEnabledByDefault
     * @return self
     */
    public function setIsEnabledByDefault(bool $isEnabledByDefault);

    /**
     * Get created at
     *
     * @return string
     */
    public function getCreatedAt();

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
    public function getUpdatedAt();

    /**
     * Set updated at
     *
     * @param string $updatedAt
     * @return self
     */
    public function setUpdatedAt(string $updatedAt);
}