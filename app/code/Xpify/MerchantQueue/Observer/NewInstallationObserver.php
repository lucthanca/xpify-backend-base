<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface as IObserver;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\Core\Model\Logger;
use Xpify\MerchantQueue\Model\MerchantInfoPublisher;

class NewInstallationObserver implements IObserver
{
    private MerchantInfoPublisher $publisher;

    /**
     * @param MerchantInfoPublisher $publisher
     */
    public function __construct(MerchantInfoPublisher $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var string $shop */
        $shop = $observer->getData('shop');
        /** @var IApp $app */
        $app = $observer->getData('app');
        $sessId = $observer->getData('sess_id');
        try {
            $this->publisher->publish($sessId, $app);
        } catch (\Throwable $e) {
            Logger::getLogger('new_installation_observer.log')->debug("[{$app->getId()}]" . 'Failed to publish merchant info for ' . $shop . ' with error: ' . $e->getMessage());
        }
    }
}
