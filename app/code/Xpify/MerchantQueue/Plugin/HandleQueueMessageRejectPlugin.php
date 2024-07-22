<?php

namespace Xpify\MerchantQueue\Plugin;

use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\MessageQueue\QueueInterface;

class HandleQueueMessageRejectPlugin
{
    /**
     */
    public function beforeReject(
        QueueInterface $subject,
        EnvelopeInterface $envelope,
        bool $requeue = true,
        string $rejectionMessage = null
    ) {
        return [$envelope, true, $rejectionMessage];
    }
}
