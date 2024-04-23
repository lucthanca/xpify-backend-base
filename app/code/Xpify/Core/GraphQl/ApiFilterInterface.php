<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl;

use Xpify\App\Api\Data\AppInterface as IApp;

interface ApiFilterInterface
{
    public function isValid(?IApp $authApp): bool;
}
