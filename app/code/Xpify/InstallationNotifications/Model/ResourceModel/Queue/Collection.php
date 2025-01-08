<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Model\ResourceModel\Queue;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Xpify\InstallationNotifications\Model\NotificationQueue as Model;
use Xpify\InstallationNotifications\Model\ResourceModel\NotificationQueue as ResourceModel;
use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as IQueue;

class Collection extends AbstractCollection
{
    private DateTime $date;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param DateTime $date
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->date = $date;
    }

    protected function _construct()
    {
        $this->_init(
            Model::class,
            ResourceModel::class
        );
    }

    /**
     * Add filter by only ready for sending item
     *
     * @return $this
     */
    public function addFilterForSending()
    {
        $this->getSelect()->where(
            sprintf('main_table.%s = ?', IQueue::IS_SENT),
            IQueue::IS_PENDING,
        )->where(
            sprintf('main_table.%s IS NULL', IQueue::START_AT)
        );

        return $this;
    }
}
