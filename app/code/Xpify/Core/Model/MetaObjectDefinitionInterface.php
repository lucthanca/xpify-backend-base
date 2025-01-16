<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

interface MetaObjectDefinitionInterface
{
    const TYPE = 'type';
    const NAME = 'name';
    const FIELDS = 'fields';

    public function getType(): string;
    public function setType(string $type): self;

    public function getName(): string;
    public function setName(string $name): self;

    public function getFields(): array;
    public function setFields(array $fields): self;
}
