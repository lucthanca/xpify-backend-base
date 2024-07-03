<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as INotificationQueue;

class NotificationQueue extends AbstractDb
{
    const MAIN_TABLE = '$xpify_notification_queue';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(self::MAIN_TABLE, INotificationQueue::ID);
    }
}
