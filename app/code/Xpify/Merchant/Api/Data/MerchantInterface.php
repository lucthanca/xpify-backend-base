<?php
declare(strict_types=1);

namespace Xpify\Merchant\Api\Data;

use Shopify\Clients\Graphql;
use Shopify\Clients\Rest;
use Shopify\Clients\Storefront;

interface MerchantInterface
{
    const ID = 'entity_id';
    const SESSION_ID = 'session_id';
    const APP_ID = 'app_id';
    const SHOP = 'shop';
    const IS_ONLINE = 'is_online';
    const STATE = 'state';
    const SCOPE = 'scope';
    const ACCESS_TOKEN = 'access_token';
    const STOREFRONT_ACCESS_TOKEN = 'storefront_access_token';
    const EXPIRES_AT = 'expires_at';
    const USER_ID = 'user_id';
    const USER_FIRST_NAME = 'user_first_name';
    const USER_LAST_NAME = 'user_last_name';
    const USER_EMAIL = 'user_email';
    const USER_EMAIL_VERIFIED = 'user_email_verified';
    const ACCOUNT_OWNER = 'account_owner';
    const LOCALE = 'locale';
    const COLLABORATOR = 'collaborator';
    const EMAIL = 'email';
    const NAME = 'name';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Get shop email
     *
     * @return string|null
     */
    public function getEmail(): ?string;

    /**
     * @param string|null $email
     * @return self
     */
    public function setEmail(?string $email): self;

    /**
     * Get shop name
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): self;

    /**
     * Get the user id
     *
     * May only be set if the is_online is true
     *
     * @return int|null
     */
    public function getUserId(): ?int;

    /**
     * Set the user id. Should only be set if the is_online is true
     *
     * @param int|null $userId
     * @return self
     */
    public function setUserId(?int $userId): self;

    /**
     * Get the user first name
     *
     * May only be set if the is_online is true
     *
     * @return string|null
     */
    public function getUserFirstName(): ?string;

    /**
     * Set the user first name. Should only be set if the is_online is true
     *
     * @param string|null $userFirstName
     * @return self
     */
    public function setUserFirstName(?string $userFirstName): self;

    /**
     * Get the user last name
     *
     * May only be set if the is_online is true
     *
     * @return string|null
     */
    public function getUserLastName(): ?string;

    /**
     * Set the user last name. Should only be set if the is_online is true
     *
     * @param string|null $userLastName
     * @return self
     */
    public function setUserLastName(?string $userLastName): self;

    /**
     * Get the user email
     *
     * May only be set if the is_online is true
     *
     * @return string|null
     */
    public function getUserEmail(): ?string;

    /**
     * Set the user email. Should only be set if the is_online is true
     *
     * @param string|null $userEmail
     * @return self
     */
    public function setUserEmail(?string $userEmail): self;

    /**
     * Get the user email verified
     *
     * May only be set if the is_online is true
     *
     * @return int|null
     */
    public function getUserEmailVerified(): ?int;

    /**
     * Set the user email verified. Should only be set if the is_online is true
     *
     * @param int|null $userEmailVerified
     * @return self
     */
    public function setUserEmailVerified(?int $userEmailVerified): self;

    /**
     * Get is the account owner
     *
     * May only be set if the is_online is true
     *
     * @return int|null
     */
    public function getAccountOwner(): ?int;

    /**
     * Set is the account owner. Should only be set if the is_online is true
     *
     * @param int|null $accountOwner
     * @return self
     */
    public function setAccountOwner(?int $accountOwner): self;

    /**
     * Get the locale
     *
     * May only be set if the is_online is true
     *
     * @return string|null
     */
    public function getLocale(): ?string;

    /**
     * Set the locale. Should only be set if the is_online is true
     *
     * @param string|null $locale
     * @return self
     */
    public function setLocale(?string $locale): self;

    /**
     * Get is collaborator
     *
     * May only be set if the is_online is true
     *
     * @return int|null
     */
    public function getCollaborator(): ?int;

    /**
     * Set is collaborator. Should only be set if the is_online is true
     *
     * @param int|null $collaborator
     * @return self
     */
    public function setCollaborator(?int $collaborator): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;

    /**
     * Get expires time of the access token
     *
     * @return string|null
     */
    public function getExpiresAt(): ?string;

    /**
     * Set expires time of the access token
     *
     * @param string|null $expiresAt
     * @return self
     */
    public function setExpiresAt(?string $expiresAt): self;

    public function getState(): ?string;

    public function setState(?string $state): self;

    /**
     * Get the allowed scope
     *
     * This method is used to get the allowed scope of the merchant.
     *
     * @return string|null
     */
    public function getScope(): ?string;

    /**
     * Set the allowed scope
     *
     * @param string|null $scope
     * @return self
     */
    public function setScope(?string $scope): self;

    /**
     * Get the Session ID
     *
     * This method is used to get the session ID of the merchant.
     * The return type is nullable string, meaning it can return a string or null.
     *
     * @return string|null The session ID of the merchant or null if not set
     */
    public function getSessionId(): ?string;

    /**
     * Set the Session ID
     *
     * This method is used to set the session ID of the merchant.
     * It accepts a nullable string as an argument, meaning you can pass a string or null.
     *
     * @param string|null $sessionId The session ID of the merchant
     * @return self Returns the current instance of the class to allow method chaining
     */
    public function setSessionId(?string $sessionId): self;

    /**
     * Get the is online
     *
     * This method is used to get the is online of the merchant.
     * The return type is integer
     *
     * @return int The is online of the merchant
     */
    public function getIsOnline(): int;

    /**
     * Set the is online
     *
     * This method is used to set the is online of the merchant.
     * It accepts a nullable integer as an argument, meaning you can pass an integer or null.
     *
     * @param int|null $isOnline The is online of the merchant
     * @return self Returns the current instance of the class to allow method chaining
     */
    public function setIsOnline(?int $isOnline): self;

    /**
     * Get the application ID
     *
     * This method is used to get the application ID of the merchant.
     * The return type is nullable integer, meaning it can return an integer or null.
     *
     * @return int|null The application ID of the merchant or null if not set
     */
    public function getAppId(): ?int;

    /**
     * Set the application ID
     *
     * This method is used to set the application ID of the merchant.
     * It accepts a nullable integer as an argument, meaning you can pass an integer or null.
     *
     * @param int|null $appId The application ID of the merchant
     * @return self Returns the current instance of the class to allow method chaining
     */
    public function setAppId(?int $appId): self;

    /**
     * Get the shop
     *
     * This method is used to get the shop of the merchant.
     * The return type is nullable string, meaning it can return a string or null.
     *
     * @return string|null The shop of the merchant or null if not set
     */
    public function getShop(): ?string;

    /**
     * Set the shop
     *
     * This method is used to set the shop of the merchant.
     * It accepts a nullable string as an argument, meaning you can pass a string or null.
     *
     * @param string|null $shop The shop of the merchant
     * @return self Returns the current instance of the class to allow method chaining
     */
    public function setShop(?string $shop): self;

    /**
     * Get the access token
     *
     * This method is used to get the access token of the merchant.
     * The return type is nullable string, meaning it can return a string or null.
     *
     * @return string|null The access token of the merchant or null if not set
     */
    public function getAccessToken(): ?string;

    /**
     * Set the access token
     *
     * This method is used to set the access token of the merchant.
     * It accepts a nullable string as an argument, meaning you can pass a string or null.
     *
     * @param string|null $accessToken The access token of the merchant
     * @return self Returns the current instance of the class to allow method chaining
     */
    public function setAccessToken(?string $accessToken): self;

    /**
     * Get the storefront access token
     *
     * This method is used to get the storefront access token of the merchant.
     * The return type is nullable string, meaning it can return a string or null.
     *
     * @return string|null The storefront access token of the merchant or null if not set
     */
    public function getStorefrontAccessToken(): ?string;

    /**
     * Set the storefront access token
     *
     * This method is used to set the storefront access token of the merchant.
     * It accepts a nullable string as an argument, meaning you can pass a string or null.
     *
     * @param string|null $storefrontAccessToken The storefront access token of the merchant
     * @return self Returns the current instance of the class to allow method chaining
     */
    public function setStorefrontAccessToken(?string $storefrontAccessToken): self;

    public function getGraphql(): ?Graphql;

    public function getRest(): ?Rest;

    public function getStoreFront(): ?Storefront;
}
