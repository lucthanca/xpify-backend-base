<?php
declare(strict_types=1);

namespace Xpify\App\Model\ResourceModel\AppMetadata;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = \Xpify\App\Api\Data\AppMetadataInterface::ID;

    /**
     * Define collection resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Xpify\App\Model\AppMetadata::class,
            \Xpify\App\Model\ResourceModel\AppMetadata::class
        );
    }
}
