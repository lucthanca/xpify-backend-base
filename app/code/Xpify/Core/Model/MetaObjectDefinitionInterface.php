<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

interface MetaObjectDefinitionInterface
{
    const TYPE = 'type';
    const NAME = 'name';
    const FIELDS = 'fields';
    const CAPABILITIES = 'capabilities';
    const ACCESS = 'access';

    public function getType(): string;
    public function setType(string $type): self;

    public function getName(): string;
    public function setName(string $name): self;

    public function getFields(): array;
    public function setFields(array $fields): self;

    public function setCapabilities(array $capabilities): self;
    public function getCapabilities(): array;

    public function getAccess(): array;

    public function setAccess(array $access): self;
}
