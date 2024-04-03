<?php
declare(strict_types=1);

namespace Xpify\Webhook\Model;

use Shopify\Webhooks\Handler as IHandler;
use Xpify\Webhook\Model\WebhookTopicInterface as IWebhookTopic;
use Xpify\Webhook\Model\WebhookAppResolverInterface as IWebhookAppResolver;

class WebhookTopic implements IWebhookTopic
{
    const CUSTOMERS_DATA_REQUEST = "CUSTOMERS_DATA_REQUEST";
    const CUSTOMERS_REDACT = "CUSTOMERS_REDACT";
    const SHOP_REDACT = "SHOP_REDACT";

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

    protected ?IWebhookAppResolver $appIdResolver;
    protected array $metafieldNamespaces = [];

    /**
     * WebhookTopic constructor.
     *
     * @param string $topic
     * @param IHandler $handler
     * @param IWebhookAppResolver|null $appIdResolver
     * @param array $includeFields
     * @param array $metafieldNamespaces
     */
    public function __construct(string $topic, IHandler $handler, ?IWebhookAppResolver $appIdResolver = null, array $includeFields = [], array $metafieldNamespaces = [])
    {
        $this->topic = $topic;
        $this->handler = $handler;
        sort($includeFields);
        $this->includeFields = $includeFields;
        $this->appIdResolver = $appIdResolver;
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
    public function getAppId(): ?int
    {
        return $this->appIdResolver?->getAppId();
    }
}
