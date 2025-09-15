<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\AppFeature\Api\Data\FeatureInterface;
use Xpify\AppFeature\Api\Data\FeatureInterfaceFactory;
use Xpify\AppFeature\Api\Data\FeatureSearchResultsInterface;
use Xpify\AppFeature\Api\Data\FeatureSearchResultsInterfaceFactory;
use Xpify\AppFeature\Api\FeatureRepositoryInterface;
use Xpify\AppFeature\Model\ResourceModel\Feature as FeatureResource;
use Xpify\AppFeature\Model\ResourceModel\Feature\CollectionFactory as FeatureCollectionFactory;

class FeatureRepository implements FeatureRepositoryInterface
{
    /**
     * @var FeatureResource
     */
    protected FeatureResource $resource;

    /**
     * @var FeatureInterfaceFactory
     */
    protected FeatureInterfaceFactory $featureFactory;

    /**
     * @var FeatureCollectionFactory
     */
    protected FeatureCollectionFactory $featureCollectionFactory;

    /**
     * @var FeatureSearchResultsInterfaceFactory
     */
    protected FeatureSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @param FeatureResource $resource
     * @param FeatureInterfaceFactory $featureFactory
     * @param FeatureCollectionFactory $featureCollectionFactory
     * @param FeatureSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        FeatureResource $resource,
        FeatureInterfaceFactory $featureFactory,
        FeatureCollectionFactory $featureCollectionFactory,
        FeatureSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->featureFactory = $featureFactory;
        $this->featureCollectionFactory = $featureCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(FeatureInterface $feature)
    {
        try {
            $this->resource->save($feature);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save feature: %1', $exception->getMessage()),
                $exception
            );
        }
        return $feature;
    }

    /**
     * @inheritDoc
     */
    public function getById($featureId)
    {
        $feature = $this->featureFactory->create();
        $this->resource->load($feature, $featureId);
        if (!$feature->getId()) {
            throw new NoSuchEntityException(__('Feature with id "%1" does not exist.', $featureId));
        }
        return $feature;
    }

    /**
     * @inheritDoc
     */
    public function getByFeatureKey(string $featureKey)
    {
        $feature = $this->featureFactory->create();
        $this->resource->load($feature, $featureKey, FeatureInterface::FEATURE_KEY);
        if (!$feature->getId()) {
            throw new NoSuchEntityException(__('Feature with key "%1" does not exist.', $featureKey));
        }
        return $feature;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->featureCollectionFactory->create();
        
        $this->collectionProcessor->process($criteria, $collection);
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        
        $items = [];
        foreach ($collection as $model) {
            $items[] = $model;
        }
        
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(FeatureInterface $feature)
    {
        try {
            $featureId = $feature->getId();
            $this->resource->delete($feature);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete feature: %1', $exception->getMessage())
            );
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($featureId)
    {
        return $this->delete($this->getById($featureId));
    }
}