<?php
declare(strict_types=1);

namespace Xpify\Merchant\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\App\Api\Data\AppInterface;
use Xpify\AppFeature\Service\FeatureManager;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface;
use Xpify\Merchant\Model\MerchantFactory;
use Xpify\Merchant\Model\ResourceModel\Merchant as MerchantResource;

class AssignCohortVersion implements ObserverInterface
{
    private MerchantFactory $merchantFactory;
    private MerchantResource $merchantResource;
    private FeatureManager $featureManager;

    /**
     * @param MerchantFactory $merchantFactory
     * @param MerchantResource $merchantResource
     * @param FeatureManager $featureManager
     */
    public function __construct(
        MerchantFactory $merchantFactory,
        MerchantResource $merchantResource,
        FeatureManager $featureManager
    ) {
        $this->merchantFactory = $merchantFactory;
        $this->merchantResource = $merchantResource;
        $this->featureManager = $featureManager;
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

            if (!$app || !$shopDomain) {
                Logger::getLogger('cohort_version.log')->warning(
                    'Missing required data in app_installed_successfully event',
                    ['app' => $app?->getId(), 'shop' => $shopDomain]
                );
                return;
            }

            // Load merchant by app_id and shop_domain
            /** @var MerchantInterface $merchant */
            $merchant = $this->merchantFactory->create();
            $this->merchantResource->load($merchant, $shopDomain, 'shop_domain');

            if (!$merchant->getId()) {
                // Try to load by app_id if shop_domain doesn't work
                $merchant = $this->merchantFactory->create();
                $this->merchantResource->load($merchant, $app->getId(), 'app_id');
            }

            if (!$merchant->getId()) {
                Logger::getLogger('cohort_version.log')->warning(
                    'Could not find merchant for cohort version assignment',
                    ['app_id' => $app->getId(), 'shop' => $shopDomain, 'session_id' => $sessionId]
                );
                return;
            }

            // Check if cohort version is already assigned
            if ($merchant->getCohortVersion()) {
                Logger::getLogger('cohort_version.log')->info(
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
            $currentAppVersion = $this->featureManager->getCurrentAppVersion();
            $merchant->setCohortVersion($currentAppVersion);
            
            $this->merchantResource->save($merchant);

            Logger::getLogger('cohort_version.log')->info(
                'Successfully assigned cohort version to merchant',
                [
                    'merchant_id' => $merchant->getId(),
                    'cohort_version' => $currentAppVersion,
                    'shop' => $shopDomain,
                    'app_id' => $app->getId()
                ]
            );

        } catch (AlreadyExistsException $e) {
            Logger::getLogger('cohort_version.log')->error(
                'Failed to save merchant cohort version - already exists',
                ['exception' => $e->getMessage()]
            );
        } catch (\Exception $e) {
            Logger::getLogger('cohort_version.log')->error(
                'Error assigning cohort version to merchant',
                ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
            );
        }
    }
}