<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

use Magento\Framework\DataObject;

class MetaobjectCapabilityDataInput extends DataObject
{
    const PUBLISHABLE_STATUS_ACTIVE = 'ACTIVE';
    const PUBLISHABLE_STATUS_DRAFT = 'DRAFT';

    public function setOnlineStore(?string $templateSuffix)
    {
        if (!$templateSuffix) return;
        $this->setData('onlineStore', compact('templateSuffix'));
    }

    public function setPublishable(string $status)
    {
        if (!in_array($status, [self::PUBLISHABLE_STATUS_ACTIVE, self::PUBLISHABLE_STATUS_DRAFT])) {
            throw new \InvalidArgumentException('Invalid publishable status');
        }
        $this->setData('publishable', compact('status'));
    }

    // auto return an array when echo $metaobjectCapabilityDataInput
    public function __toArray()
    {
        return $this->getData();
    }
}
