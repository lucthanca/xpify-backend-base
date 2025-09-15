<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model;

use Magento\Framework\Model\AbstractModel;
use Xpify\AppFeature\Api\Data\FeatureInterface;

class Feature extends AbstractModel implements FeatureInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Feature::class);
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
    public function setFeatureKey(string $featureKey)
    {
        return $this->setData(self::FEATURE_KEY, $featureKey);
    }

    /**
     * @inheritDoc
     */
    public function getFeatureName()
    {
        return (string) $this->getData(self::FEATURE_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setFeatureName(string $featureName)
    {
        return $this->setData(self::FEATURE_NAME, $featureName);
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * @inheritDoc
     */
    public function setDescription(?string $description)
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @inheritDoc
     */
    public function getRequiredVersion()
    {
        return $this->getData(self::REQUIRED_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function setRequiredVersion(?string $requiredVersion)
    {
        return $this->setData(self::REQUIRED_VERSION, $requiredVersion);
    }

    /**
     * @inheritDoc
     */
    public function getIsEnabledByDefault()
    {
        return (bool) $this->getData(self::IS_ENABLED_BY_DEFAULT);
    }

    /**
     * @inheritDoc
     */
    public function setIsEnabledByDefault(bool $isEnabledByDefault)
    {
        return $this->setData(self::IS_ENABLED_BY_DEFAULT, $isEnabledByDefault);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return (string) $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt()
    {
        return (string) $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}