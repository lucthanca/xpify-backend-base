<?php
declare(strict_types=1);

namespace Xpify\Merchant\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as IMerchantSubscription;
use Xpify\Merchant\Api\Data\MerchantSubscriptionSearchResultsInterface;
use Xpify\Merchant\Api\Data\MerchantSubscriptionSearchResultsInterface as ISubscriptionSearchResults;

interface MerchantSubscriptionRepositoryInterface
{
    /**
     * @param int $id
     * @return IMerchantSubscription
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $id): IMerchantSubscription;

    /**
     * Retrieve subscription by Shopify subscription ID
     *
     * @param string $id
     * @return IMerchantSubscription
     */
    public function getByShopifySubscriptionId(string $id): IMerchantSubscription;

    /**
     * @param IMerchantSubscription $merchantSubscription
     * @return IMerchantSubscription
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(IMerchantSubscription $merchantSubscription): IMerchantSubscription;

    public function delete(IMerchantSubscription $merchantSubscription): bool;

    /**
     * Delete by provided ID
     *
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool;

    /**
     * @param SearchCriteriaInterface $criteria
     * @return MerchantSubscriptionSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $criteria): ISubscriptionSearchResults;
}
