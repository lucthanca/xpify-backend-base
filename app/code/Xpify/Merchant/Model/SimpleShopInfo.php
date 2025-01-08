<?php
declare(strict_types=1);

namespace Xpify\Merchant\Model;

use Xpify\Merchant\Api\Data\SimpleShopInfoInterface as ISimpleShopInfo;
use Magento\Framework\DataObject;

class SimpleShopInfo extends DataObject implements ISimpleShopInfo
{

    /**
     * @inheritDoc
     */
    public function getMyshopifyDomain(): ?string
    {
        return $this->getData(ISimpleShopInfo::MYSHOPIFY_DOMAIN);
    }

    /**
     * @inheritDoc
     */
    public function setMyshopifyDomain(?string $myshopifyDomain): ISimpleShopInfo
    {
        return $this->setData(ISimpleShopInfo::MYSHOPIFY_DOMAIN, $myshopifyDomain);
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return $this->getData(ISimpleShopInfo::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(?string $name): ISimpleShopInfo
    {
        return $this->setData(ISimpleShopInfo::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getEmail(): ?string
    {
        return $this->getData(ISimpleShopInfo::EMAIL);
    }

    /**
     * @inheritDoc
     */
    public function setEmail(?string $email): ISimpleShopInfo
    {
        return $this->setData(ISimpleShopInfo::EMAIL, $email);
    }
}
