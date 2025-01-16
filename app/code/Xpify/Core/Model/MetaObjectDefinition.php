<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

use Magento\Framework\DataObject;
use Xpify\Core\Model\MetaObjectDefinitionInterface as IMetaObject;

class MetaObjectDefinition extends DataObject implements IMetaObject
{

    public function getType(): string
    {
        return $this->getData(self::TYPE);
    }

    public function setType(string $type): IMetaObject
    {
        return $this->setData(self::TYPE, $type);
    }

    public function getName(): string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): IMetaObject
    {
        return $this->setData(self::NAME, $name);
    }

    public function getFields(): array
    {
        return $this->getData(self::FIELDS);
    }

    public function setFields(array $fields): IMetaObject
    {
        return $this->setData(self::FIELDS, $fields);
    }
}
