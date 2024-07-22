<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Model;

class ConfigProvider
{
    const ENDPOINT_CONFIG_PATH = 'xpify/webhook/endpoint';
    const USERNAME_CONFIG_PATH = 'xpify/webhook/username';
    const PASSWORD_CONFIG_PATH = 'xpify/webhook/password';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get webhook endpoint
     *
     * @return string|null
     */
    public function getWebhookEndpoint(): ?string
    {
        return $this->scopeConfig->getValue(
            self::ENDPOINT_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get basic auth username
     *
     * @return string|null
     */
    public function getWebhookUsername(): ?string
    {
        return $this->scopeConfig->getValue(
            self::USERNAME_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get basic auth password
     *
     * @return string|null
     */
    public function getWebhookPassword(): ?string
    {
        return $this->scopeConfig->getValue(
            self::PASSWORD_CONFIG_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
