<?php
declare(strict_types=1);

namespace Xpify\Auth\Service\Webhooks;

use Magento\Framework\Event\ManagerInterface as IEventManager;
use Shopify\Webhooks\Handler;
use Xpify\App\Service\GetCurrentApp;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;

class AppUninstalled implements Handler
{
    private IMerchantRepository $merchantRepository;
    private GetCurrentApp $currentApp;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private $logger;
    private IEventManager $eventManager;

    /**
     * @param IMerchantRepository $merchantRepository
     * @param GetCurrentApp $currentApp
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param IEventManager $eventManager
     */
    public function __construct(
        IMerchantRepository $merchantRepository,
        GetCurrentApp $currentApp,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        IEventManager $eventManager
    ) {
        $this->merchantRepository = $merchantRepository;
        $this->currentApp = $currentApp;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->eventManager = $eventManager;
    }

    /**
     * @inheritDoc
     */
    public function handle(string $topic, string $shop, array $body): void
    {
        try {
            $this->searchCriteriaBuilder->addFilter(IMerchant::SHOP, $shop);
            $this->searchCriteriaBuilder->addFilter(IMerchant::APP_ID, $this->currentApp->get()->getId());
            $filterBuilder = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Api\FilterBuilder::class);
            $filter = $filterBuilder->setField(IMerchant::ACCESS_TOKEN)
                ->setConditionType('notnull')
                ->create();
            $this->searchCriteriaBuilder->addFilters([$filter]);
            $searchResults = $this->merchantRepository->getList($this->searchCriteriaBuilder->create());
            if ($searchResults->getTotalCount() === 0) {
                return;
            }
            foreach ($searchResults->getItems() as $merchant) {
                $sessId = $merchant->getSessionId();
                $this->merchantRepository->delete($merchant);
                $this->eventManager->dispatch('app_uninstalled_success', ['shop' => $merchant->getShop(), 'app' => $this->currentApp->get(), 'sess_id' => $sessId, 'merchant' => $merchant]);
                $this->getLogger()?->info(__("{$merchant->getShop()} đã gỡ cài đặt app {$this->currentApp->get()->getName()}")->render());
            }
        } catch (\Exception $e) {
            $this->getLogger()?->debug(__("Failed to uninstall app for shop $shop: %1", $e->getMessage())->render());
        }
    }

    /**
     * Logger hehe
     *
     * @return \Zend_Log|null
     */
    private function getLogger(): ?\Zend_Log
    {
        try {
            return Logger::getLogger('app_uninstalled.log');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
