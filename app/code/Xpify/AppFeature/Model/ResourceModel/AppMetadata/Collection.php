<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model\ResourceModel\AppMetadata;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Xpify\AppFeature\Model\AppMetadata;
use Xpify\AppFeature\Model\ResourceModel\AppMetadata as AppMetadataResourceModel;

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
        $this->_init(AppMetadata::class, AppMetadataResourceModel::class);
    }
}