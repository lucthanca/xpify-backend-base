<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Xpify\Core\Model\Logger;
use Xpify\InstallationNotifications\Model\ConfigProvider;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\InstallationNotifications\Model\NotificationQueueFactory as QueueFactory;
use Xpify\InstallationNotifications\Model\ResourceModel\NotificationQueue as QueueResource;
use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as IQueue;

class InstallNotifyObserver implements ObserverInterface
{
    const TYPE = 'install';
    const TEMPLATE_ID = 'install_notify_email';

    protected ConfigProvider $configProvider;
    private QueueFactory $queueFactory;
    private QueueResource $queueResource;

    /**
     * @param ConfigProvider $configProvider
     * @param QueueFactory $queueFactory
     * @param QueueResource $queueResource
     */
    public function __construct(
        ConfigProvider $configProvider,
        QueueFactory $queueFactory,
        QueueResource $queueResource
    ) {
        $this->configProvider = $configProvider;
        $this->queueFactory = $queueFactory;
        $this->queueResource = $queueResource;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        if (!$this->configProvider->getIsEnable()) return;
        /** @var string $shop */
        $shop = $observer->getData('shop');
        /** @var IApp $app */
        $app = $observer->getData('app');
        $sessId = $observer->getData('sess_id');

        try {
            /** @var IQueue $nQueue */
            $nQueue = $this->queueFactory->create();
            $nQueue->setSessionId($sessId);
            $nQueue->setShop($shop);
            $nQueue->setType(static::TYPE);
            $nQueue->setAppId((int) $app->getId());

            $this->queueResource->save($nQueue);
        } catch (\Exception $e) {
            Logger::getLogger('notifier_queue.log')?->debug($e->getMessage());
        }
    }
}
