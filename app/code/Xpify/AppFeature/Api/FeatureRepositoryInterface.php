<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\AppFeature\Api\Data\FeatureInterface;
use Xpify\AppFeature\Api\Data\FeatureSearchResultsInterface;

interface FeatureRepositoryInterface
{
    /**
     * Save feature
     *
     * @param FeatureInterface $feature
     * @return FeatureInterface
     * @throws LocalizedException
     */
    public function save(FeatureInterface $feature);

    /**
     * Retrieve feature
     *
     * @param int $featureId
     * @return FeatureInterface
     * @throws LocalizedException
     */
    public function getById($featureId);

    /**
     * Retrieve feature by feature key
     *
     * @param string $featureKey
     * @return FeatureInterface
     * @throws NoSuchEntityException
     */
    public function getByFeatureKey(string $featureKey);

    /**
     * Retrieve features matching the specified criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return FeatureSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete feature
     *
     * @param FeatureInterface $feature
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(FeatureInterface $feature);

    /**
     * Delete feature by ID
     *
     * @param int $featureId
     * @return bool true on success
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById($featureId);
}