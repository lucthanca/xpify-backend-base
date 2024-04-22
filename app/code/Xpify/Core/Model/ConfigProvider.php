<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigProvider
{
    const XML_CONFIG_PATH_WHITELIST_IPS = 'xpify/general/ip_whitelist';
    const XML_CONFIG_PATH_ENABLE_WHITELIST = 'xpify/general/enable_whitelist';
    protected array $runtimeCached = [];

    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get whitelist IPs
     *
     * @return array
     */
    public function getWhitelistIps(): array
    {
        if (!isset($this->runtimeCached[self::XML_CONFIG_PATH_WHITELIST_IPS])) {
            $value = $this->scopeConfig->getValue(self::XML_CONFIG_PATH_WHITELIST_IPS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if ($value) {
                $exploded = explode(',', $value);
                $filtered = array_map('trim', $exploded);
                $this->runtimeCached[self::XML_CONFIG_PATH_WHITELIST_IPS] = $filtered;
            } else {
                $this->runtimeCached[self::XML_CONFIG_PATH_WHITELIST_IPS] = [];
            }
        }
        return [];
    }

    /**
     * Check if whitelist is enabled
     *
     * @return bool
     */
    public function isWhitelistEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_ENABLE_WHITELIST, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}
