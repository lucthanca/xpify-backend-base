<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface as IObserver;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class AppUninstallObserver extends NewInstallationObserver implements IObserver
{
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
        /** @var IMerchant $merchant */
        $merchant = $observer->getData('merchant');
        try {
            $this->publisher->publish(
                $sessId,
                $app,
                \Xpify\MerchantQueue\Api\Data\TopicDataInterface::TYPE_MERCHANT_UNINSTALLED,
                ['merchant_data' => \Xpify\MerchantQueue\Model\DataObject::getData($merchant, ['shop', 'name', 'email'])]
            );
        } catch (\Throwable $e) {
            Logger::getLogger('app_installation_observer.log')->debug("[{$app->getId()}]" . 'Failed to publish merchant info for ' . $shop . ' with error: ' . $e->getMessage());
        }
    }
}
