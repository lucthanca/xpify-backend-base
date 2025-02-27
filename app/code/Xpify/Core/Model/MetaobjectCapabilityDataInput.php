<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

use Magento\Framework\DataObject;

class MetaobjectCapabilityDataInput extends DataObject
{
    const PUBLISHABLE_STATUS_ACTIVE = 'ACTIVE';
    const PUBLISHABLE_STATUS_DRAFT = 'DRAFT';

    /**
     * Set the Online Store capability input.
     *
     * @param string|null $templateSuffix
     * @return self
     */
    public function setOnlineStore(?string $templateSuffix): self
    {
        if (!$templateSuffix) {
            return $this;
        }
        return $this->setData('onlineStore', compact('templateSuffix'));
    }

    /**
     * Set the Publishable capability input.
     *
     * @param string $status
     * @return self
     */
    public function setPublishable(string $status): self
    {
        if (!in_array($status, [self::PUBLISHABLE_STATUS_ACTIVE, self::PUBLISHABLE_STATUS_DRAFT])) {
            throw new \InvalidArgumentException('Invalid publishable status');
        }
        return $this->setData('publishable', compact('status'));
    }
    /**
     * Convert the object to an array.
     *
     * @return array
     */
    public function __toArray(): array
    {
        return $this->getData();
    }
}
