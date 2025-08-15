<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Api;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Api\Data\SearchResultsInterface as ISearchResults;

interface PricingPlanRepositoryInterface
{
    /**
     * Load by id
     *
     * @param mixed $id
     * @param bool $force
     * @return IPricingPlan
     * @throws NoSuchEntityException
     */
    public function get(mixed $id, bool $force = false): IPricingPlan;

    /**
     * Save
     *
     * @param IPricingPlan $obj
     * @return IPricingPlan
     * @throws \Magento\Framework\Exception\CouldNotSaveException|\Magento\Framework\Exception\AlreadyExistsException
     */
    public function save(IPricingPlan $obj): IPricingPlan;

    /**
     * Delete
     *
     * @param IPricingPlan $obj
     * @return void
     * @throws CouldNotDeleteException
     */
    public function delete(IPricingPlan $obj): void;

    /**
     * Delete by id
     *
     * @param $id
     * @return void
     * @throws CouldNotDeleteException
     */
    public function deleteById($id): void;

    /**
     * Get list
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $criteria
     * @return ISearchResults
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $criteria): ISearchResults;
}
