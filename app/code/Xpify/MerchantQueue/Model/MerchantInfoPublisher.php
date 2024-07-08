<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Model;

use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\MessageQueue\PublisherInterface as IPublisher;
use Magento\Framework\Serialize\SerializerInterface;
use Xpify\App\Api\Data\AppInterface as IApp;

class MerchantInfoPublisher
{
    const TOPIC_NAME = 'xpify.merchant.info';

    private IPublisher $publisher;
    private TopicDataFactory $dataFactory;

    /**
     * @param IPublisher $publisher
     * @param OperationInterfaceFactory $operartionFactory
     * @param IdentityGeneratorInterface $identityService
     * @param SerializerInterface $serializer
     * @param TopicDataFactory $dataFactory
     */
    public function __construct(
        IPublisher $publisher,
        TopicDataFactory $dataFactory
    ) {
        $this->publisher = $publisher;
        $this->dataFactory = $dataFactory;
    }

    /**
     * Publishes a message to the merchant info topic
     *
     * @param string $sessId
     * @param IApp $app
     * @return void
     */
    public function publish(string $sessId, IApp $app): void
    {
        /** @var \Xpify\MerchantQueue\Api\Data\TopicDataInterface $topicData */
        $topicData = $this->dataFactory->create();
        $topicData->setSessionId($sessId);
        $topicData->setAppId($app->getId());
        $this->publisher->publish(self::TOPIC_NAME, $topicData);
    }
}
