<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Serialize\SerializerInterface;
use Xpify\AppFeature\Api\Data\AppMetadataInterface;

class AppMetadata extends AbstractModel implements AppMetadataInterface
{
    private SerializerInterface $serializer;

    /**
     * @param SerializerInterface $serializer
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        SerializerInterface $serializer,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\AppMetadata::class);
    }

    /**
     * @inheritDoc
     */
    public function getMetadataKey(): string
    {
        return (string) $this->getData(self::METADATA_KEY);
    }

    /**
     * @inheritDoc
     */
    public function setMetadataKey(string $metadataKey): AppMetadataInterface
    {
        return $this->setData(self::METADATA_KEY, $metadataKey);
    }

    /**
     * @inheritDoc
     */
    public function getMetadataValue(): ?array
    {
        $value = $this->getData(self::METADATA_VALUE);
        if (is_string($value)) {
            try {
                return $this->serializer->unserialize($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function setMetadataValue(?array $metadataValue): AppMetadataInterface
    {
        if (is_array($metadataValue)) {
            $metadataValue = $this->serializer->serialize($metadataValue);
        }
        return $this->setData(self::METADATA_VALUE, $metadataValue);
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
    public function setCreatedAt(string $createdAt): AppMetadataInterface
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
    public function setUpdatedAt(string $updatedAt): AppMetadataInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}