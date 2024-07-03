<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Model\Resolver;

use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as IQueue;

class UninstallEmailRecipientResolver extends InstallEmailRecipientResolver
{
    public function get(IQueue $queue): string
    {
        return $this->configProvider->getUninstallReceiveEmail() ?: '';
    }

    public function getCC(IQueue $queue): array
    {
        return $this->configProvider->getUninstallCcEmails() ?: [];
    }
}
