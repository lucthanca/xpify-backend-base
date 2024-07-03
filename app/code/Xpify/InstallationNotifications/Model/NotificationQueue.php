<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Model;

use Magento\Framework\Model\AbstractModel;
use Xpify\App\Model\AppFactory;
use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as INotificationQueue;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\App\Model\ResourceModel\App as AppResource;

class NotificationQueue extends AbstractModel implements INotificationQueue
{
    private AppFactory $appFactory;
    private AppResource $appResource;

    public function __construct(
        AppFactory $appFactory,
        AppResource $appResource,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->appFactory = $appFactory;
        $this->appResource = $appResource;
    }

    protected function _construct()
    {
        $this->_init(ResourceModel\NotificationQueue::class);
    }

    public function getSessionId(): ?string
    {
        return $this->getData(INotificationQueue::SESSION_ID);
    }

    public function setSessionId(string $sessId): INotificationQueue
    {
        return $this->setData(INotificationQueue::SESSION_ID, $sessId);
    }

    public function getShop(): ?string
    {
        return $this->getData(INotificationQueue::SHOP);
    }

    public function setShop(string $shop): INotificationQueue
    {
        return $this->setData(INotificationQueue::SHOP, $shop);
    }

    public function getAppId(): ?int
    {
        $value = $this->getData(INotificationQueue::APP_ID);
        return $value ? (int) $value : null;
    }

    public function setAppId(int $appId): INotificationQueue
    {
        return $this->setData(INotificationQueue::APP_ID, $appId);
    }

    public function getType(): ?string
    {
        return $this->getData(INotificationQueue::TYPE);
    }

    public function setType(string $type): INotificationQueue
    {
        return $this->setData(INotificationQueue::TYPE, $type);
    }

    public function getIsSent(): ?string
    {
        return $this->getData(INotificationQueue::IS_SENT);
    }

    public function setIsSent(string $isSent): INotificationQueue
    {
        return $this->setData(INotificationQueue::IS_SENT, $isSent);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(INotificationQueue::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): INotificationQueue
    {
        return $this->setData(INotificationQueue::CREATED_AT, $createdAt);
    }

    public function getStartAt(): ?string
    {
        return $this->getData(INotificationQueue::START_AT);
    }

    public function setStartAt(?string $sentAt): INotificationQueue
    {
        return $this->setData(INotificationQueue::START_AT, $sentAt);
    }

    public function getFinishAt(): ?string
    {
        return $this->getData(INotificationQueue::FINISH_AT);
    }

    public function setFinishAt(string $finishAt): INotificationQueue
    {
        return $this->setData(INotificationQueue::FINISH_AT, $finishAt);
    }

    public function isSent(): bool
    {
        return $this->getIsSent() === INotificationQueue::IS_SENT_YES;
    }

    /**
     * Retrieve app model
     *
     * @return IApp|null
     */
    public function app(): ?IApp
    {
        if ($this->getAppId()) {
            $app = $this->appFactory->create();
            $this->appResource->load($app, $this->getAppId());
            return $app->getId() ? $app : null;
        }
        return null;
    }
}
