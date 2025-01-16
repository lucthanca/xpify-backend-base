<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

interface MetaObjectDefinitionUpdateHandlerInterface
{
    public function handle(array &$metaObject, array $remoteDefinition, array $missingFields): void;
}
