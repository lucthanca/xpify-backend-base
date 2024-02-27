<?php
declare(strict_types=1);

namespace Xpify\Webhook\Model;

use Shopify\Webhooks\Handler as IHandler;

/**
 * Interface WebhookTopicInterface
 *
 * This interface defines the methods that a class must implement to handle webhook topics.
 */
interface WebhookTopicInterface
{
    /**
     * Get the topic
     *
     * This method is used to get the topic of the webhook.
     * The return type is string.
     *
     * @return string The topic of the webhook
     */
    public function getTopic(): string;

    /**
     * Get the handler
     *
     * This method is used to get the handler of the webhook.
     * The return type is an instance of IHandler.
     *
     * @return IHandler The handler of the webhook
     */
    public function getHandler(): IHandler;

    /**
     * Get fields to include in the webhook payload
     */
    public function getIncludeFields(): array;

    /**
     * Get the app name
     * Which app is this webhook should be registered to
     * if null, it will be registered to all apps
     *
     * @return string|null
     */
    public function getAppName(): ?string;
}
