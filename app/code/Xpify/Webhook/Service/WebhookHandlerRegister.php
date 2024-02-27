<?php
declare(strict_types=1);

namespace Xpify\Webhook\Service;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface as IObserver;
use Shopify\Webhooks\Registry;
use Xpify\Webhook\Model\WebhookTopicInterface as IWebhookTopic;

class WebhookHandlerRegister implements IObserver
{
    private array $webhookTopics;

    /**
     * @param IWebhookTopic[] $webhookTopics
     */
    public function __construct(array $webhookTopics = [])
    {
        $this->webhookTopics = $webhookTopics;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $this->addWebhookHandlers();
    }

    /**
     * Get webhook registry
     *
     * @param string $appName
     * @return IWebhookTopic[]
     */
    public function getWebhookRegistry(string $appName): array
    {
        $webhookTopics = $this->webhookTopics;

        return array_filter($webhookTopics, function ($webhook) use ($appName) {
            return !$webhook->getAppName() || $webhook->getAppName() === $appName;
        });
    }

    /**
     * Add webhook handlers, using di.xml to inject them
     *
     * List topics @see \Shopify\Webhooks\Topics and https://shopify.dev/docs/api/admin-graphql/latest/enums/webhooksubscriptiontopic
     *
     * @return void
     */
    private function addWebhookHandlers(): void
    {
        foreach ($this->webhookTopics as $topic) {
            Registry::addHandler($topic->getTopic(), $topic->getHandler());
        }
    }
}
