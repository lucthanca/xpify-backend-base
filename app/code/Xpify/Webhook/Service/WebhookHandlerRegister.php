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

        // Filter webhooks by app name if it's not null
        // null means it will be registered to all apps
        $webhooksByApp = array_filter($webhookTopics, function ($webhook) use ($appName) {
            return !$webhook->getAppName() || $webhook->getAppName() === $appName;
        });

        // mapping webhook registry by uppercase array key of each registry, eg current $webhooksByApp structure is ['key' => $webhook, ...]
        // the key will be combined by the $webhook->getTopic()
        return array_combine(array_map(function ($webhook) {
            return $webhook->topicForStorage();
        }, $webhooksByApp), $webhooksByApp);
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
