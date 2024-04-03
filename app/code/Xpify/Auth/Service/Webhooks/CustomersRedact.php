<?php
declare(strict_types=1);

namespace Xpify\Auth\Service\Webhooks;

use Shopify\Webhooks\Handler;
use Xpify\App\Service\GetCurrentApp;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;

class CustomersRedact implements Handler
{
    private IMerchantRepository $merchantRepository;
    private GetCurrentApp $currentApp;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private $logger;

    /**
     * @param IMerchantRepository $merchantRepository
     * @param GetCurrentApp $currentApp
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        IMerchantRepository $merchantRepository,
        GetCurrentApp $currentApp,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->merchantRepository = $merchantRepository;
        $this->currentApp = $currentApp;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritDoc
     */
    public function handle(string $topic, string $shop, array $body): void
    {
        $this->getLogger()?->debug(__("Created customer_redact for shop")->render());
//        try {
//            $this->searchCriteriaBuilder->addFilter(IMerchant::SHOP, $shop);
//            $this->searchCriteriaBuilder->addFilter(IMerchant::APP_ID, $this->currentApp->get()->getId());
//            $searchResults = $this->merchantRepository->getList($this->searchCriteriaBuilder->create());
//            if ($searchResults->getTotalCount() === 0) {
//                return;
//            }
//            foreach ($searchResults->getItems() as $merchant) {
//                $this->merchantRepository->delete($merchant);
//            }
//            $this->getLogger()?->info(__("$shop đã gỡ cài đặt app {$this->currentApp->get()->getName()}")->render());
//        } catch (\Exception $e) {
//            $this->getLogger()?->debug(__("Failed to uninstall app for shop $shop: %1", $e->getMessage())->render());
//        }
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
