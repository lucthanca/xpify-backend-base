<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface FeatureSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get features list.
     *
     * @return FeatureInterface[]
     */
    public function getItems();

    /**
     * Set features list.
     *
     * @param FeatureInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}