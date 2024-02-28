<?php
declare(strict_types=1);

namespace Xpify\Webhook\Model;

use Shopify\Webhooks\Handler as IHandler;
use Xpify\Webhook\Model\WebhookTopicInterface as IWebhookTopic;

class WebhookTopic implements IWebhookTopic
{
    private ?string $topicForStorage = null;
    /**
     * @var string
     */
    protected $topic;

    /**
     * @var IHandler
     */
    protected $handler;

    /**
     * @var array
     */
    protected array $includeFields = [];

    protected ?string $appName;
    protected array $metafieldNamespaces = [];

    /**
     * WebhookTopic constructor.
     *
     * @param string $topic
     * @param IHandler $handler
     * @param string|null $appName
     * @param array $includeFields
     */
    public function __construct(string $topic, IHandler $handler, ?string $appName = null, array $includeFields = [], array $metafieldNamespaces = [])
    {
        $this->topic = $topic;
        $this->handler = $handler;
        sort($includeFields);
        $this->includeFields = $includeFields;
        $this->appName = $appName;
        sort($metafieldNamespaces);
        $this->metafieldNamespaces = $metafieldNamespaces;
    }

    /**
     * @inheritDoc
     */
    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * Replace / or . with _ by using this pattern /\/|\./g and make it uppercase
     *
     * @return string
     */
    public function topicForStorage(): string
    {
        if (!$this->topicForStorage) {
            $this->topicForStorage = strtoupper(preg_replace('/\/|\./', '_', $this->getTopic()));
        }
        return $this->topicForStorage;
    }

    /**
     * @inheritDoc
     */
    public function getHandler(): IHandler
    {
        return $this->handler;
    }

    /**
     * @inheritDoc
     */
    public function getIncludeFields(): array
    {
        return $this->includeFields;
    }

    /**
     * @inheritDoc
     */
    public function getMetafieldNamespaces(): array
    {
        return $this->metafieldNamespaces;
    }

    /**
     * @inheritDoc
     */
    public function getAppName(): ?string
    {
        return $this->appName;
    }
}
