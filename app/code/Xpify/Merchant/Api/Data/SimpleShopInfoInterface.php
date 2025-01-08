<?php
declare(strict_types=1);

namespace Xpify\Merchant\Api\Data;

interface SimpleShopInfoInterface
{
    const MYSHOPIFY_DOMAIN = 'myshopify_domain';
    const NAME = 'name';
    const EMAIL = 'email';

    /**
     * @return string|null
     */
    public function getMyshopifyDomain(): ?string;

    /**
     * @param string|null $myshopifyDomain
     * @return $this
     */
    public function setMyshopifyDomain(?string $myshopifyDomain): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string|null $name
     * @return $this
     */
    public function setName(?string $name): self;

    /**
     * @return string|null
     */
    public function getEmail(): ?string;

    /**
     * @param string|null $email
     * @return $this
     */
    public function setEmail(?string $email): self;
}
