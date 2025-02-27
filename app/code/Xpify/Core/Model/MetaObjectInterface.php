<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

interface MetaObjectInterface
{
    const TYPE = 'type';
    const HANDLE = 'handle';
    const FIELDS = 'fields';
    const CAPABILITIES = 'capabilities';

    public function getType(): string;

    public function setType(string $type): self;

    public function getHandle(): string;

    public function setHandle(string $handle): self;

    /**
     * @return array - associative array of fields with structure: [['key' => 'The key of the field', 'value' => 'The value of the field.']]
     */
    public function getFields(): array;

    /**
     * @param array $fields - associative array of fields with structure: [['key' => 'The key of the field', 'value' => 'The value of the field.']]
     * @return self
     */
    public function setFields(array $fields): self;

    public function getCapabilities(): MetaobjectCapabilityDataInput;

    public function setCapabilities(MetaobjectCapabilityDataInput $capabilities): self;

    public function __toArray(): array;
}
