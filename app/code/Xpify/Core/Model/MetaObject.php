<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

use Magento\Framework\DataObject;
use Xpify\Core\Model\MetaObjectInterface as IMetaObject;

class MetaObject extends DataObject implements IMetaObject
{
    public function setType(string $type): IMetaObject
    {
        return $this->setData(self::TYPE, $type);
    }

    public function getType(): string
    {
        return $this->getData(self::TYPE);
    }

    public function setHandle(string $handle): IMetaObject
    {
        return $this->setData(self::HANDLE, $handle);
    }

    public function getHandle(): string
    {
        return $this->getData(self::HANDLE);
    }

    public function setFields(array $fields): IMetaObject
    {
        return $this->setData(self::FIELDS, $fields);
    }

    public function getFields(): array
    {
        return $this->getData(self::FIELDS);
    }

    public function setCapabilities(MetaobjectCapabilityDataInput $capabilities): IMetaObject
    {
        return $this->setData(self::CAPABILITIES, $capabilities);
    }

    public function getCapabilities(): MetaobjectCapabilityDataInput
    {
        return $this->getData(self::CAPABILITIES);
    }

    public function __toArray(): array
    {
        return [
            self::TYPE => $this->getType(),
            self::HANDLE => $this->getHandle(),
            self::FIELDS => $this->getFields(),
            self::CAPABILITIES => $this->getCapabilities()->__toArray(),
        ];
    }
}
