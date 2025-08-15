<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Psr\Log\LoggerInterface;
use Xpify\PricingPlan\Api\Data;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface;
use Xpify\PricingPlan\Model\ResourceModel\PricingPlan;
use Xpify\PricingPlan\Model\ResourceModel\PricingPlan\CollectionFactory;
use Xpify\PricingPlan\Api\Data\SearchResultsInterface as ISearchResults;

class PricingPlanRepository implements PricingPlanRepositoryInterface
{
    private array $plans = [];
    private PricingPlan $resource;

    private PricingPlanFactory $factory;

    private \Psr\Log\LoggerInterface $logger;

    private CollectionFactory $collectionFactory;

    private CollectionProcessorInterface $collectionProcessor;

    private Data\SearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @param PricingPlan $resource
     * @param PricingPlanFactory $factory
     * @param LoggerInterface $logger
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        ResourceModel\PricingPlan $resource,
        PricingPlanFactory $factory,
        \Psr\Log\LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        Data\SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor = null
    ) {
        $this->resource = $resource;
        $this->factory = $factory;
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * Create new instance of Pricing Plan
     *
     * @return IPricingPlan
     */
    public function create(): IPricingPlan
    {
        return $this->factory->create();
    }

    /**
     * @inheritDoc
     */
    public function get(mixed $id, bool $force = false): IPricingPlan
    {
        $cId = 'pp_' . $id;
        if ($force || !isset($this->plans[$cId])) {
            $obj = $this->create();
            $this->resource->load($obj, $id);
            if (!$obj->getId()) {
                throw new \Magento\Framework\Exception\NoSuchEntityException(__('Unable to find pricing plan with ID "%1"', $id));
            }
            $this->plans[$cId] = $obj;
        }

        return $this->plans[$cId];
    }

    /**
     * @inheritDoc
     */
    public function save(IPricingPlan $obj): IPricingPlan
    {
        try {
            $this->resource->save($obj);
            return $obj;
        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->debug($e);
            throw new \Magento\Framework\Exception\CouldNotSaveException(__('Unable to save pricing plan.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(IPricingPlan $obj): void
    {
        try {
            $this->resource->delete($obj);
        } catch (\Throwable $e) {
            $this->logger->debug($e);
            throw new \Magento\Framework\Exception\CouldNotDeleteException(__('Unable to remove pricing plan.'));
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById($id): void
    {
        try {
            $this->delete($this->get($id));
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // it ok.
        }
    }

    /**
     * @inheritDoc
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $criteria): ISearchResults
    {
        /** @var \Xpify\PricingPlan\Model\ResourceModel\PricingPlan\Collection $collection */
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);

        /** @var Data\SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

        /** @var IPricingPlan[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }
}
