<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl;

interface ApiFilterInterface
{
    public function isValid(?string $currentAppID): bool;
}
