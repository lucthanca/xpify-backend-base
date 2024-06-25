<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Observer;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Xpify\Core\Model\Logger;
use Xpify\InstallationNotifications\Model\ConfigProvider;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;

class InstallNotifyObserver implements ObserverInterface
{
    const TYPE = 'install';
    protected ConfigProvider $configProvider;
    private StateInterface $inlineTranslation;
    private TransportBuilder $transportBuilder;
    private IMerchantRepository $merchantRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param ConfigProvider $configProvider
     * @param StateInterface $inlineTranslation
     * @param Escaper $escaper
     * @param TransportBuilder $transportBuilder
     */
    public function __construct(
        ConfigProvider $configProvider,
        StateInterface $inlineTranslation,
        Escaper $escaper,
        TransportBuilder $transportBuilder,
        IMerchantRepository $merchantRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->configProvider = $configProvider;
        $this->inlineTranslation = $inlineTranslation;
        $this->transportBuilder = $transportBuilder;
        $this->merchantRepository = $merchantRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        if (!$this->configProvider->getIsEnable()) return;
        /** @var string $shop */
        $shop = $observer->getData('shop');
        /** @var IApp $app */
        $app = $observer->getData('app');
        $notiRequired = true;
        if (static::TYPE === 'install') {
            $notiRequired = false;
            $this->searchCriteriaBuilder->addFilter(IMerchant::SHOP, $shop);
            $this->searchCriteriaBuilder->addFilter(IMerchant::APP_ID, $app->getId());
            $searchResults = $this->merchantRepository->getList($this->searchCriteriaBuilder->create());
            if ($searchResults->getTotalCount() === 0) {
                return;
            }
            $items = $searchResults->getItems();
            /** @var IMerchant $merchant */
            $merchant = current($items);
            if (empty($merchant->getData('install_noti_sent'))) {
                $notiRequired = true;
            }
        }
        if (!$notiRequired) return;
        try {
            $this->inlineTranslation->suspend();
            $sender = [
                'name' => $this->configProvider->getSenderName(),
                'email' => $this->configProvider->getSenderEmail(),
            ];
            $this->transportBuilder
                ->setTemplateIdentifier($this->getEmailTemplateId())
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                    ]
                )
                ->setTemplateVars([
                    'shop'  => $shop,
                    'app'   => $app->getName() . " ({$app->getId()})",
                ])
                ->setFromByScope($sender)
                ->addTo($this->getReceiverEmail());
            if ($ccEmails = $this->getCCEmails()) {
                foreach ($ccEmails as $ccEmail)
                    $this->transportBuilder->addCc(trim($ccEmail));
            }
            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
            if (isset($merchant) && $merchant->getId()) {
                $merchant->setData('install_noti_sent', 1);
                $this->merchantRepository->save($merchant);
            }
        } catch (\Exception $e) {
            Logger::getLogger('notifier_errors.log')?->debug($e->getMessage());
        }
    }

    /**
     * Get email template id
     *
     * @return string
     */
    protected function getEmailTemplateId(): string
    {
        return 'install_notify_email';
    }

    /**
     * Get cc emails
     *
     * @return string[]
     */
    protected function getCCEmails(): ?array
    {
        return $this->configProvider->getInstallCcEmails();
    }

    /**
     * Get Receiver Email
     *
     * @return string|null
     */
    protected function getReceiverEmail(): ?string
    {
        return $this->configProvider->getInstallReceiveEmail();
    }
}
