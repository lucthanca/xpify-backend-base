<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Model;

use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as IQueue;

class EmailRecipientResolver
{
    private array $recipientResolverPool;

    /**
     * @param RecipientResolverInterface[] $recipientResolverPool
     * @param RecipientResolverInterface[] $ccResolverPool
     */
    public function __construct(
        array $recipientResolverPool = []
    ) {
        $this->recipientResolverPool = $recipientResolverPool;
    }

    /**
     * Resolve the recipient email
     *
     * @param IQueue $queue
     * @return string
     */
    public function getRecipientEmail(IQueue $queue): string
    {
        if (!isset($this->recipientResolverPool[$queue->getType()])) {
            throw new \InvalidArgumentException("Recipient email resolver not found for type: {$queue->getType()}." . json_encode($queue->getData()));
        }

        return $this->recipientResolverPool[$queue->getType()]->get($queue);
    }

    /**
     * Resolve the CC email
     *
     * @param IQueue $queue
     * @return array
     */
    public function getCCEmails(IQueue $queue): array
    {
        if (!isset($this->recipientResolverPool[$queue->getType()])) {
            throw new \InvalidArgumentException("CC email resolver not found for type: {$queue->getType()}." . json_encode($queue->getData()));
        }

        return $this->recipientResolverPool[$queue->getType()]->getCC($queue);
    }
}
