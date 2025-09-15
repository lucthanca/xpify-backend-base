<?php
declare(strict_types=1);

namespace Xpify\AppFeature\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class MerchantFeature extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('$xpify_merchant_features', 'entity_id');
    }
}