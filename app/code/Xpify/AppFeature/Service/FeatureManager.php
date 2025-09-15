<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Xpify\AppFeature\Api\Data\FeatureInterface;
use Xpify\AppFeature\Api\Data\MerchantFeatureInterface;
use Xpify\AppFeature\Api\Data\AppMetadataInterface;
use Xpify\AppFeature\Model\ResourceModel\Feature\CollectionFactory as FeatureCollectionFactory;
use Xpify\AppFeature\Model\ResourceModel\MerchantFeature\CollectionFactory as MerchantFeatureCollectionFactory;
use Xpify\AppFeature\Model\ResourceModel\AppMetadata\CollectionFactory as AppMetadataCollectionFactory;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\MerchantRepositoryInterface;

class FeatureManager
{
    private FeatureCollectionFactory $featureCollectionFactory;
    private MerchantFeatureCollectionFactory $merchantFeatureCollectionFactory;
    private AppMetadataCollectionFactory $appMetadataCollectionFactory;
    private MerchantRepositoryInterface $merchantRepository;
    private SerializerInterface $serializer;

    /**
     * @param FeatureCollectionFactory $featureCollectionFactory
     * @param MerchantFeatureCollectionFactory $merchantFeatureCollectionFactory
     * @param AppMetadataCollectionFactory $appMetadataCollectionFactory
     * @param MerchantRepositoryInterface $merchantRepository
     * @param SerializerInterface $serializer
     */
    public function __construct(
        FeatureCollectionFactory $featureCollectionFactory,
        MerchantFeatureCollectionFactory $merchantFeatureCollectionFactory,
        AppMetadataCollectionFactory $appMetadataCollectionFactory,
        MerchantRepositoryInterface $merchantRepository,
        SerializerInterface $serializer
    ) {
        $this->featureCollectionFactory = $featureCollectionFactory;
        $this->merchantFeatureCollectionFactory = $merchantFeatureCollectionFactory;
        $this->appMetadataCollectionFactory = $appMetadataCollectionFactory;
        $this->merchantRepository = $merchantRepository;
        $this->serializer = $serializer;
    }

    /**
     * Check if a feature is enabled for a specific merchant
     *
     * Logic:
     * 1. Check merchant_features table for override
     * 2. If no override, check merchant.cohort_version >= feature.required_version
     * 3. If no feature record found, return false
     *
     * @param int $merchantId
     * @param string $featureKey
     * @return bool
     */
    public function isFeatureEnabled(int $merchantId, string $featureKey): bool
    {
        try {
            // Step 1: Check for merchant-specific override
            $merchantFeatureCollection = $this->merchantFeatureCollectionFactory->create();
            $merchantFeatureCollection->addFieldToFilter(MerchantFeatureInterface::MERCHANT_ID, $merchantId);
            $merchantFeatureCollection->addFieldToFilter(MerchantFeatureInterface::FEATURE_KEY, $featureKey);
            $merchantFeature = $merchantFeatureCollection->getFirstItem();

            if ($merchantFeature->getId()) {
                Logger::getLogger('feature_manager.log')->debug(
                    "Found merchant override for feature {$featureKey}, merchant {$merchantId}: " . 
                    ($merchantFeature->getEnabled() ? 'enabled' : 'disabled')
                );
                return (bool) $merchantFeature->getEnabled();
            }

            // Step 2: Check feature definition and merchant cohort version
            $featureCollection = $this->featureCollectionFactory->create();
            $featureCollection->addFieldToFilter(FeatureInterface::FEATURE_KEY, $featureKey);
            $feature = $featureCollection->getFirstItem();

            if (!$feature->getId()) {
                Logger::getLogger('feature_manager.log')->debug(
                    "Feature {$featureKey} not found in features table"
                );
                return false;
            }

            // Get merchant
            try {
                $merchant = $this->merchantRepository->get($merchantId);
            } catch (NoSuchEntityException $e) {
                Logger::getLogger('feature_manager.log')->debug(
                    "Merchant {$merchantId} not found"
                );
                return false;
            }

            $merchantCohortVersion = $merchant->getCohortVersion();
            $featureRequiredVersion = $feature->getRequiredVersion();

            if (!$merchantCohortVersion) {
                Logger::getLogger('feature_manager.log')->debug(
                    "Merchant {$merchantId} has no cohort_version, using feature default: " . 
                    ($feature->getIsEnabledByDefault() ? 'enabled' : 'disabled')
                );
                return $feature->getIsEnabledByDefault();
            }

            // Compare versions using version_compare
            $versionCheck = version_compare($merchantCohortVersion, $featureRequiredVersion, '>=');
            
            Logger::getLogger('feature_manager.log')->debug(
                "Feature {$featureKey}: merchant cohort={$merchantCohortVersion}, required_version={$featureRequiredVersion}, enabled=" . 
                ($versionCheck ? 'true' : 'false')
            );

            return $versionCheck;

        } catch (\Throwable $e) {
            Logger::getLogger('feature_manager.log')->error(
                "Error checking feature {$featureKey} for merchant {$merchantId}: " . $e->getMessage(),
                ['exception' => $e]
            );
            return false;
        }
    }

    /**
     * Get all features status for a merchant
     *
     * @param int $merchantId
     * @return array Map of featureKey => boolean
     */
    public function getMerchantFeatures(int $merchantId): array
    {
        $features = [];

        try {
            // Get all available features
            $featureCollection = $this->featureCollectionFactory->create();
            
            foreach ($featureCollection as $feature) {
                /** @var FeatureInterface $feature */
                $features[$feature->getFeatureKey()] = $this->isFeatureEnabled(
                    $merchantId, 
                    $feature->getFeatureKey()
                );
            }

        } catch (\Throwable $e) {
            Logger::getLogger('feature_manager.log')->error(
                "Error getting features for merchant {$merchantId}: " . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $features;
    }

    /**
     * Get current app version from metadata
     *
     * @return string
     */
    public function getCurrentAppVersion(): string
    {
        try {
            $metadataCollection = $this->appMetadataCollectionFactory->create();
            $metadataCollection->addFieldToFilter(AppMetadataInterface::METADATA_KEY, 'app_version');
            $metadata = $metadataCollection->getFirstItem();

            if ($metadata->getId()) {
                $value = $metadata->getMetadataValue();
                return $value['current'] ?? '1.0.0';
            }

        } catch (\Throwable $e) {
            Logger::getLogger('feature_manager.log')->error(
                "Error getting current app version: " . $e->getMessage()
            );
        }

        return '1.0.0';
    }

    /**
     * Set current app version
     *
     * @param string $version
     * @return bool
     */
    public function setCurrentAppVersion(string $version): bool
    {
        try {
            $metadataCollection = $this->appMetadataCollectionFactory->create();
            $metadataCollection->addFieldToFilter(AppMetadataInterface::METADATA_KEY, 'app_version');
            $metadata = $metadataCollection->getFirstItem();

            if (!$metadata->getId()) {
                $metadata = $metadataCollection->getNewEmptyItem();
                $metadata->setMetadataKey('app_version');
            }

            $metadata->setMetadataValue(['current' => $version]);
            $metadata->save();

            Logger::getLogger('feature_manager.log')->debug(
                "Set app version to: {$version}"
            );

            return true;

        } catch (\Throwable $e) {
            Logger::getLogger('feature_manager.log')->error(
                "Error setting app version to {$version}: " . $e->getMessage()
            );
            return false;
        }
    }
}