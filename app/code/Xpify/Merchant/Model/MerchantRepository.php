<?php
declare(strict_types=1);

namespace Xpify\Merchant\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\Data\MerchantSearchResultsInterface as SearchResults;
use Xpify\Merchant\Api\Data\MerchantSearchResultsInterfaceFactory as SearchResultsFactory;
use Xpify\Merchant\Model\ResourceModel\Merchant\CollectionFactory as MerchantCollectionFactory;

class MerchantRepository implements \Xpify\Merchant\Api\MerchantRepositoryInterface
{
    private SearchResultsFactory $searchResultsFactory;
    private ResourceModel\Merchant $resource;
    private MerchantFactory $factory;
    protected \Psr\Log\LoggerInterface $logger;
    private MerchantCollectionFactory $collectionFactory;
    private ?CollectionProcessorInterface $collectionProcessor;

    /**
     * @param ResourceModel\Merchant $resource
     * @param MerchantFactory $factory
     * @param \Psr\Log\LoggerInterface $logger
     * @param MerchantCollectionFactory $collectionFactory
     * @param SearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        \Xpify\Merchant\Model\ResourceModel\Merchant $resource,
        MerchantFactory $factory,
        \Psr\Log\LoggerInterface $logger,
        MerchantCollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory,
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
     * Create new instance of Merchant
     *
     * @return IMerchant
     */
    public function create(): IMerchant
    {
        return $this->factory->create();
    }

    /**
     * @inheritDoc
     */
    public function getById(int $id): IMerchant
    {
        try {
            $merchant = $this->create();
            $this->resource->load($merchant, $id);
            if (!$merchant->getId()) {
                throw new NoSuchEntityException(__('Merchant with id "%1" does not exist.', $id));
            }
            return $merchant;
        } catch (NoSuchEntityException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new NoSuchEntityException(__('Merchant with id "%1" does not exist.', $id));
        }
    }

    /**
     * @inheritDoc
     */
    public function save(IMerchant $merchant): IMerchant
    {
        try {
            $this->resource->save($merchant);
            return $merchant;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotSaveException(__('Could not save Merchant'));
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(IMerchant $merchant): bool
    {
        try {
            $this->resource->delete($merchant);
            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotDeleteException(__('Could not delete Merchant'));
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $id): bool
    {
        try {
            $this->resource->delete($this->getById($id));
            return true;
        } catch (CouldNotDeleteException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new CouldNotDeleteException(__('Could not delete Merchant'));
        }
    }

    /**
     * @inheritDoc
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $criteria): \Xpify\Merchant\Api\Data\MerchantSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);
        /** @var SearchResults $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

        /** @var IMerchant[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function cleanNotCompleted(string $shop): int|string
    {
        return $this->resource->cleanNotCompleted($shop);
    }
}
