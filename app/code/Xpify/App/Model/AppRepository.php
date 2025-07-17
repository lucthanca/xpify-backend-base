<?php
declare(strict_types=1);

namespace Xpify\App\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Xpify\App\Api\AppRepositoryInterface;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\App\Api\Data\AppSearchResultsInterface as IAppSearchResults;
use Xpify\App\Model\ResourceModel\App;
use Xpify\App\Model\ResourceModel\App\CollectionFactory;
use Xpify\App\Api\Data\AppSearchResultsInterfaceFactory as SearchResultsFactory;

class AppRepository implements AppRepositoryInterface
{
    private $resource;
    private $appFactory;
    private $logger;
    private CollectionFactory $collectionFactory;
    private ?CollectionProcessorInterface $collectionProcessor;
    private SearchResultsFactory $searchResultsFactory;

    /**
     * Runtime cache for apps by field and value
     * @var array
     */
    private array $cache = [];

    /**
     * @param App $resource
     * @param AppFactory $appFactory
     * @param LoggerInterface $logger
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        \Xpify\App\Model\ResourceModel\App $resource,
        \Xpify\App\Model\AppFactory $appFactory,
        \Psr\Log\LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor = null
    ) {
        $this->resource = $resource;
        $this->appFactory = $appFactory;
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritDoc
     */
    public function get($value, $field = 'entity_id')
    {
        $cacheKey = $field . '_' . $value;

        // Check cache first
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $app = $this->newInstance();
        try {
            $this->resource->load($app, $value, $field);
        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            throw new NoSuchEntityException(__('Unable to find app with ID "%1"', $value));
        }
        if (!$app->getId()) {
            throw new NoSuchEntityException(__('Unable to find app with ID "%1"', $value));
        }

        // Store in cache
        $this->cacheApp($app, $field, $value);

        return $app;
    }

    /**
     * @inheritDoc
     */
    public function save(IApp $app)
    {
        try {
            $this->resource->save($app);

            // Cache the updated app
            $this->cacheApp($app);

            return $app;
        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            throw new \Magento\Framework\Exception\CouldNotSaveException(
                __('Could not save the app: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(IApp $app)
    {
        try {
            $this->resource->delete($app);
            // Clear the app from cache
            $this->cacheApp($app);
            return true;
        } catch (\Throwable $e) {
            $this->logger->debug($e->getMessage());
            throw new \Magento\Framework\Exception\CouldNotDeleteException(
                __('Could not delete the app: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(mixed $id)
    {
        try {
            return $this->delete($this->get($id));
        } catch (NoSuchEntityException $e) {
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $criteria): IAppSearchResults
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $items = $collection->getItems();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * Create new app
     *
     * @return IApp
     */
    public function newInstance()
    {
        return $this->appFactory->create();
    }

    /**
     * Cache an app instance by all its field values
     *
     * @param IApp $app
     * @param string $field
     * @param mixed|null $value
     * @return void
     */
    private function cacheApp(IApp $app, string $field = IApp::ID, mixed $value = null): void
    {
        if (!$app->getId()) {
            return;
        }
        if ($value === null) {
            $value = $app->getData($field);
            if (!$value) {
                return;
            }
        }
        $cacheKey = $field . '_' . $value;
        if ($app->isDeleted()) {
            foreach ($this->cache[$cacheKey] as $key => $cachedApp) {
                if ($cachedApp->getId() === $app->getId()) {
                    unset($this->cache[$key]);
                }
            }
            return;
        }
        if ($field !== IApp::ID) {
            $idCacheKey = IApp::ID . '_' . $app->getId();
            // Ensure the app is also cached by its ID
            if (!isset($this->cache[$idCacheKey]) || $this->cache[$idCacheKey] !== $app) {
                $this->cache[$idCacheKey] = $app;
            }
        }
        $currentApp = $this->cache[$cacheKey] ?? null;
        // Only cache if the app is not already cached or if it ref changes
        if (!$currentApp || $currentApp !== $app) {
            $this->cache[$cacheKey] = $app;
        }
    }
}
