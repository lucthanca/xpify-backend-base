<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Cron;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Translate\Inline\StateInterface;
use Xpify\Core\Model\Logger;
use Xpify\InstallationNotifications\Model\ConfigProvider;
use Xpify\InstallationNotifications\Model\EmailRecipientResolver;
use Xpify\InstallationNotifications\Model\ResourceModel\Queue\CollectionFactory;
use Xpify\InstallationNotifications\Observer\InstallNotifyObserver;
use Xpify\InstallationNotifications\Observer\UninstallNotifyObserver;
use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as IQueue;
use Xpify\InstallationNotifications\Model\ResourceModel\NotificationQueue as QueueResource;

class Observer
{
    private const TEMPLATE_MAPPING = [
        InstallNotifyObserver::TYPE => InstallNotifyObserver::TEMPLATE_ID,
        UninstallNotifyObserver::TYPE => UninstallNotifyObserver::TEMPLATE_ID,
    ];
    /**
     * Number of queues
     */
    private const COUNT_OF_QUEUE = 3;

    /**
     * Number of notifications
     */
    private const COUNT_OF_NOTIFICATIONS = 20;
    private CollectionFactory $queueCollectionFactory;
    private StateInterface $inlineTranslation;
    private TransportBuilder $transportBuilder;
    private ConfigProvider $configProvider;
    private EmailRecipientResolver $emailRecipientResolver;
    private QueueResource $queueResource;
    private \Magento\Framework\Stdlib\DateTime\DateTime $date;

    /**
     * @param CollectionFactory $queueCollectionFactory
     * @param StateInterface $inlineTranslation
     * @param TransportBuilder $transportBuilder
     * @param ConfigProvider $configProvider
     * @param EmailRecipientResolver $emailRecipientResolver
     * @param QueueResource $queueResource
     * @param DateTime $date
     */
    public function __construct(
        \Xpify\InstallationNotifications\Model\ResourceModel\Queue\CollectionFactory $queueCollectionFactory,
        StateInterface $inlineTranslation,
        TransportBuilder $transportBuilder,
        ConfigProvider $configProvider,
        EmailRecipientResolver $emailRecipientResolver,
        QueueResource $queueResource,
        \Magento\Framework\Stdlib\DateTime\DateTime $date
    ) {
        $this->queueCollectionFactory = $queueCollectionFactory;
        $this->inlineTranslation = $inlineTranslation;
        $this->transportBuilder = $transportBuilder;
        $this->configProvider = $configProvider;
        $this->emailRecipientResolver = $emailRecipientResolver;
        $this->queueResource = $queueResource;
        $this->date = $date;
    }

    public function getEmailTemplateId(IQueue $item) {
        if (empty(static::TEMPLATE_MAPPING[$item->getType()])) {
            $encodedInfo = json_encode($item->getData());
            throw new \Magento\Framework\Exception\LocalizedException(__("Invalid notification type. $encodedInfo"));
        }
        return static::TEMPLATE_MAPPING[$item->getType()];
    }

    public function scheduledSend()
    {
        /** @var \Xpify\InstallationNotifications\Model\ResourceModel\Queue\Collection $collection */
        $collection = $this->queueCollectionFactory->create();
        $collection->setPageSize(self::COUNT_OF_QUEUE)
            ->setCurPage(1)->addFilterForSending()->load();

        $items = $collection->getItems();
        /** @var IQueue $item */
        foreach ($items as $item) {
            try {
                $recipientMail = $this->emailRecipientResolver->getRecipientEmail($item);

                // Skip if the recipient email is empty
                $item->setStartAt($this->date->gmtDate());
                if (empty($recipientMail)) {
                    $item->setIsSent(IQueue::IS_SKIP);
                    $item->setFinishAt($this->date->gmtDate());
                    $this->queueResource->save($item);
                    return;
                }
                $this->queueResource->save($item);

                $app = $item->app();
                if (!$app) {
                    throw new \Magento\Framework\Exception\LocalizedException(__("App not found for notification queue {$item->getId()}"));
                }
                $this->inlineTranslation->suspend();
                $sender = [
                    'name' => $this->configProvider->getSenderName() ?: 'Xpify System',
                    'email' => $this->configProvider->getSenderEmail() ?: 'system@bsscommerce.com',
                ];
                $this->transportBuilder
                    ->setTemplateIdentifier($this->getEmailTemplateId($item))
                    ->setTemplateOptions(
                        [
                            'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                            'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                        ]
                    )
                    ->setTemplateVars([
                        'shop'  => $item->getShop(),
                        'app'   => $app->getName() . " ({$app->getId()})",
                        'queue' => $item,
                    ])
                    ->setFromByScope($sender)
                    ->addTo($recipientMail);
                if ($ccEmails = $this->emailRecipientResolver->getCCEmails($item)) {
                    foreach ($ccEmails as $ccEmail)
                        $this->transportBuilder->addCc(trim($ccEmail));
                }
                $transport = $this->transportBuilder->getTransport();
                $transport->sendMessage();
                $this->inlineTranslation->resume();
                $item->setIsSent(IQueue::IS_SENT_YES);
                $item->setFinishAt($this->date->gmtDate());
            } catch (\Throwable $e) {
                Logger::getLogger('notifier_errors.log')?->debug($e->getMessage());
                $item->setStartAt(null);
            }
            try {
                $this->queueResource->save($item);
            } catch (\Exception $e) {
                Logger::getLogger('notifier_errors.log')?->debug(__("[{$item->getId()}]Failed to save notification queue. %1", $e->getMessage())->render());
            }
        }
    }
}
