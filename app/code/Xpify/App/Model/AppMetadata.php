<?php
declare(strict_types=1);

namespace Xpify\App\Model;

use Magento\Framework\Model\AbstractModel;
use Xpify\App\Api\Data\AppMetadataInterface as IAppMetadata;

class AppMetadata extends AbstractModel implements IAppMetadata
{
    protected function _construct(): void
    {
        $this->_init(\Xpify\App\Model\ResourceModel\AppMetadata::class);
    }

    /**
     * @inheritDoc
     */
    public function getMetadataKey(): string
    {
        return $this->getData(self::METADATA_KEY);
    }

    /**
     * @inheritDoc
     */
    public function setMetadataKey(string $metadataKey)
    {
        return $this->setData(self::METADATA_KEY, $metadataKey);
    }

    /**
     * @inheritDoc
     */
    public function getMetadataValue(): ?array
    {
        $val = $this->getData(self::METADATA_VALUE);
        if (!$val) {
            return [];
        }
        if (is_string($val)) {
            return json_decode($val, true);
        }
        return $val;
    }

    /**
     * @inheritDoc
     */
    public function setMetadataValue(null|array|string $metadataValue)
    {
        if (is_array($metadataValue)) {
            $metadataValue = json_encode($metadataValue);
        }
        return $this->setData(self::METADATA_VALUE, $metadataValue);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(self::CREATED_AT);
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
    public function getUpdatedAt(): string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
