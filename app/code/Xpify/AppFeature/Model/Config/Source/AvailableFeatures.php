<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Xpify\AppFeature\Model\ResourceModel\Feature\CollectionFactory as FeatureCollectionFactory;

class AvailableFeatures implements OptionSourceInterface
{
    private FeatureCollectionFactory $featureCollectionFactory;

    /**
     * @param FeatureCollectionFactory $featureCollectionFactory
     */
    public function __construct(
        FeatureCollectionFactory $featureCollectionFactory
    ) {
        $this->featureCollectionFactory = $featureCollectionFactory;
    }

    /**
     * Get options for feature selection
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => '',
                'label' => __('-- Please Select --')
            ]
        ];

        $collection = $this->featureCollectionFactory->create();
        foreach ($collection as $feature) {
            $options[] = [
                'value' => $feature->getFeatureKey(),
                'label' => $feature->getFeatureName() . ' (' . $feature->getFeatureKey() . ')'
            ];
        }

        return $options;
    }
}