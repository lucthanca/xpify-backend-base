<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Model;

use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as IQueue;

interface RecipientResolverInterface
{
    public function get(IQueue $queue): string;

    public function getCC(IQueue $queue): array;
}
