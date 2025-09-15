<?php
declare(strict_types=1);

namespace Xpify\AppFeatureGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\AppFeature\Service\FeatureManager;
use Xpify\AppFeature\Model\ResourceModel\Feature\CollectionFactory as FeatureCollectionFactory;
use Xpify\Core\Model\Logger;

class AppFeaturesQuery extends AuthSessionAbstractResolver
{
    private FeatureManager $featureManager;
    private FeatureCollectionFactory $featureCollectionFactory;

    /**
     * @param FeatureManager $featureManager
     * @param FeatureCollectionFactory $featureCollectionFactory
     */
    public function __construct(
        FeatureManager $featureManager,
        FeatureCollectionFactory $featureCollectionFactory
    ) {
        $this->featureManager = $featureManager;
        $this->featureCollectionFactory = $featureCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function execResolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        try {
            $merchant = $this->getMerchantSession()->getMerchant();
            $merchantId = (int) $merchant->getId();

            // Get all features with their status
            $featuresMap = $this->featureManager->getMerchantFeatures($merchantId);
            
            // Build detailed feature list
            $featuresList = [];
            $featureCollection = $this->featureCollectionFactory->create();
            
            foreach ($featureCollection as $feature) {
                $featureKey = $feature->getFeatureKey();
                $featuresList[] = [
                    'feature_key' => $featureKey,
                    'description' => $feature->getDescription(),
                    'enabled' => $featuresMap[$featureKey] ?? false,
                    'min_version' => $feature->getMinVersion(),
                    'merchant_cohort_version' => $merchant->getCohortVersion()
                ];
            }

            // Convert features map to GraphQL-friendly format
            $featuresMapGraphQL = new \stdClass();
            foreach ($featuresMap as $key => $enabled) {
                // Convert snake_case to camelCase for GraphQL
                $camelKey = $this->convertToCamelCase($key);
                $featuresMapGraphQL->$camelKey = $enabled;
            }

            return [
                'features' => $featuresList,
                'features_map' => $featuresMapGraphQL
            ];

        } catch (\Throwable $e) {
            Logger::getLogger('graphql.log')->error(
                "Error in AppFeaturesQuery: " . $e->getMessage(),
                ['exception' => $e]
            );
            
            return [
                'features' => [],
                'features_map' => new \stdClass()
            ];
        }
    }

    /**
     * Convert snake_case to camelCase
     *
     * @param string $str
     * @return string
     */
    private function convertToCamelCase(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }
}