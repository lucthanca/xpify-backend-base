<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model\ResourceModel\MerchantFeature;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Xpify\AppFeature\Model\MerchantFeature;
use Xpify\AppFeature\Model\ResourceModel\MerchantFeature as MerchantFeatureResourceModel;

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
        $this->_init(MerchantFeature::class, MerchantFeatureResourceModel::class);
    }
}