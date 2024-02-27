<?php
declare(strict_types=1);

namespace Xpify\Webhook\Model;

use Shopify\Webhooks\Handler as IHandler;
use Xpify\Webhook\Model\WebhookTopicInterface as IWebhookTopic;

class WebhookTopic implements IWebhookTopic
{
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

    /**
     * WebhookTopic constructor.
     *
     * @param string $topic
     * @param IHandler $handler
     * @param string|null $appName
     * @param array $includeFields
     */
    public function __construct(string $topic, IHandler $handler, ?string $appName = null, array $includeFields = [])
    {
        $this->topic = $topic;
        $this->handler = $handler;
        $this->includeFields = $includeFields;
        $this->appName = $appName;
    }

    /**
     * @inheritDoc
     */
    public function getTopic(): string
    {
        return $this->topic;
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
    public function getAppName(): ?string
    {
        return $this->appName;
    }
}
