<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Ui\Component\Listing\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Xpify\AppFeature\Model\ResourceModel\MerchantFeature\Collection;
use Xpify\AppFeature\Model\ResourceModel\MerchantFeature\CollectionFactory;

class MerchantFeature extends AbstractDataProvider
{
    protected $collection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        return $this->getCollection()->toArray();
    }

    /**
     * Add filter
     *
     * @param Filter $filter
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        if ($filter->getField() === 'merchant_id') {
            $this->getCollection()->addFieldToFilter(
                'merchant_id',
                $filter->getValue()
            );
        } elseif ($filter->getField() !== 'fulltext') {
            $this->getCollection()->addFieldToFilter(
                $filter->getField(),
                [$filter->getConditionType() => $filter->getValue()]
            );
        } else {
            // Full text search
            $this->getCollection()->addFieldToFilter(
                ['feature_key'],
                [
                    ['like' => '%' . $filter->getValue() . '%']
                ]
            );
        }
    }
}