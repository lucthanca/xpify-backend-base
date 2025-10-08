<?php
declare(strict_types=1);

namespace Xpify\Merchant\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;
use Xpify\Merchant\Api\Data\MerchantSubscriptionSearchResultsInterface as SubscriptionSearchResults;
use Xpify\Merchant\Api\MerchantSubscriptionRepositoryInterface as ISubscriptionRepository;
use Xpify\Merchant\Model\MerchantSubscriptionFactory as SubscriptionFactory;
use Xpify\Merchant\Model\ResourceModel\MerchantSubscription as SubscriptionResource;
use Xpify\Merchant\Model\ResourceModel\Subscription\CollectionFactory as SubscriptionCollectionFactory;
use Xpify\Merchant\Api\Data\MerchantSubscriptionSearchResultsInterfaceFactory as SearchResultsFactory;

class SubscriptionRepository implements ISubscriptionRepository
{
    private MerchantSubscriptionFactory $subscriptionFactory;
    private SubscriptionResource $subscriptionResource;
    private \Psr\Log\LoggerInterface $logger;
    private SubscriptionCollectionFactory $collectionFactory;
    private SearchResultsFactory $searchResultsFactory;
    private ?CollectionProcessorInterface $collectionProcessor;

    /**
     * @param MerchantSubscriptionFactory $subscriptionFactory
     * @param SubscriptionResource $subscriptionResource
     * @param LoggerInterface $logger
     * @param SubscriptionCollectionFactory $collectionFactory
     * @param SearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        SubscriptionFactory $subscriptionFactory,
        SubscriptionResource $subscriptionResource,
        \Psr\Log\LoggerInterface $logger,
        SubscriptionCollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor = null
    ) {
        $this->subscriptionFactory = $subscriptionFactory;
        $this->subscriptionResource = $subscriptionResource;
        $this->logger = $logger;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    public function getById(int $id): ISubscription
    {
        try {
            $obj = $this->subscriptionFactory->create();
            $this->subscriptionResource->load($obj, $id);
            return $obj;
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            throw new NoSuchEntityException(__('Subscription not found!'));
        }
    }

    /**
     * @inheritDoc
     * @throws NoSuchEntityException
     */
    public function getByShopifySubscriptionId(string $id): ISubscription
    {
        try {
            $obj = $this->subscriptionFactory->create();
            $this->subscriptionResource->load($obj, $id, ISubscription::SUBSCRIPTION_ID);
            return $obj;
        } catch (\Throwable $e) {
            $this->logger->debug($e->__toString());
            throw new NoSuchEntityException(__('Subscription not found!'));
        }
    }

    /**
     * @inheritDoc
     */
    public function save(ISubscription $merchantSubscription): ISubscription
    {
        try {
            $this->subscriptionResource->save($merchantSubscription);
            return $merchantSubscription;
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            throw new CouldNotSaveException(__('Could not create subscription'));
        }
    }

    public function delete(ISubscription $merchantSubscription): bool
    {
        try {
            $this->subscriptionResource->delete($merchantSubscription);
            return true;
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $id): bool
    {
        try {
            $obj = $this->getById($id);
            $this->subscriptionResource->delete($obj);
            return true;
        } catch (NoSuchEntityException $e) {
            return true;
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $criteria): SubscriptionSearchResults
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        /** @var ISubscription[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * New instance of subscription
     */
    public function create(): ISubscription
    {
        return $this->subscriptionFactory->create();
    }
}
