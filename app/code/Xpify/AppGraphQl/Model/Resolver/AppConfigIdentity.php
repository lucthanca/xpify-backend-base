<?php
declare(strict_types=1);

namespace Xpify\AppGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface as IIdentity;
use Xpify\App\Api\Data\AppInterface as IApp;

class AppConfigIdentity implements IIdentity
{
    public function getIdentities(array $resolvedData): array
    {
        if (!isset($resolvedData['model']) || !$resolvedData['model'] instanceof IApp) {
            return [];
        }
        /** @var IApp $app */
        $app = $resolvedData['model'];
        return $app->getIdentities();
    }
}
