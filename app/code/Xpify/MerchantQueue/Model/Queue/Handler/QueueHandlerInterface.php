<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Model\Queue\Handler;

use Xpify\MerchantQueue\Api\Data\TopicDataInterface as ITopicData;
use Xpify\App\Api\Data\AppInterface as IApp;

interface QueueHandlerInterface
{
    /**
     * Handle the queue
     *
     * @return void
     */
    public function handle(IApp $app, ITopicData $topicData);
}
