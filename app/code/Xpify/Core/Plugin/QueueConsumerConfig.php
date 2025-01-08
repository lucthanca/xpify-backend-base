<?php
declare(strict_types=1);

namespace Xpify\Core\Plugin;

class QueueConsumerConfig
{
    protected array $UNWANTED_CONSUMERS = [
        'product_action_attribute.update',
        'product_action_attribute.website.update',
        'media.storage.catalog.image.resize',
        'exportProcessor',
        'media.content.synchronization',
        'media.gallery.renditions.update',
        'media.gallery.synchronization',
        'product_alert',
        'codegeneratorProcessor',
        'sales.rule.update.coupon.usage',
        'sales.rule.quote.trigger.recollect',
        'async.operations.all',
    ];
    /**
     * @param $subject
     * @param \Magento\Framework\MessageQueue\Consumer\Config\ConsumerConfigItem\Iterator $consumers
     * @return \Magento\Framework\MessageQueue\Consumer\Config\ConsumerConfigItem\Iterator
     */
    public function afterGetConsumers($subject, $consumers)
    {
        array_walk($this->UNWANTED_CONSUMERS, fn($unwantedConsumer) => $consumers->offsetUnset($unwantedConsumer));
        $consumers->rewind();
        return $consumers;
    }
}
