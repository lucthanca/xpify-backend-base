<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model\ResourceModel\Feature;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Xpify\AppFeature\Model\Feature;
use Xpify\AppFeature\Model\ResourceModel\Feature as FeatureResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Feature::class, FeatureResourceModel::class);
    }
}