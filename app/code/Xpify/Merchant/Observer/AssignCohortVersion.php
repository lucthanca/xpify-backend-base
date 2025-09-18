<?php
declare(strict_types=1);

namespace Xpify\Merchant\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\App\Api\Data\AppInterface;
use Xpify\App\Service\AppVersionManager;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;

class AssignCohortVersion implements ObserverInterface
{
    private AppVersionManager $appVersionManager;
    private IMerchantRepository $merchantRepository;

    /**
     * @param AppVersionManager $appVersionManager
     * @param IMerchantRepository $merchantRepository
     */
    public function __construct(
        AppVersionManager $appVersionManager,
        IMerchantRepository $merchantRepository
    ) {
        $this->appVersionManager = $appVersionManager;
        $this->merchantRepository = $merchantRepository;
    }

    /**
     * Assign current app version as cohort version to newly installed merchant
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            /** @var AppInterface $app */
            $app = $observer->getData('app');
            $shopDomain = $observer->getData('shop');
            $sessionId = $observer->getData('sess_id');

            if (!$shopDomain || !$app?->getId()) {
                Logger::getLogger('merchant.cohort_version.log')->debug(
                    'Missing required data in app_installed_successfully event',
                    ['app' => $app?->getId(), 'shop' => $shopDomain]
                );
                return;
            }

            // Load merchant by app_id and shop_domain
            $merchant = $this->merchantRepository->getBySessId($sessionId, (int)$app->getId());

            // Check if cohort version is already assigned
            if ($merchant->getCohortVersion()) {
                Logger::getLogger('merchant.cohort_version.log')->info(
                    'Merchant already has cohort version, skipping assignment',
                    [
                        'merchant_id' => $merchant->getId(),
                        'existing_version' => $merchant->getCohortVersion(),
                        'shop' => $shopDomain
                    ]
                );
                return;
            }

            // Get current app version and assign as cohort version
            $currentAppVersion = $this->appVersionManager->getCurrentAppVersion();
            $merchant->setCohortVersion($currentAppVersion);

            $this->merchantRepository->save($merchant);
        } catch (NoSuchEntityException $e) {
            Logger::getLogger('merchant.cohort_version.log')->debug(
                'Failed to save merchant cohort version - already exists',
                ['exception' => $e->getMessage()]
            );
        } catch (\Exception $e) {
            Logger::getLogger('merchant.cohort_version.log')->debug(
                'Error assigning cohort version to merchant',
                ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
        }
    }
}
