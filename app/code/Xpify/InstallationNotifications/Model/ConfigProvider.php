<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigProvider
{
    const XML_PATH_CONFIG_ENABLE = 'installation_notify/general/enable';
    const XML_PATH_CONFIG_SENDER_NAME = 'installation_notify/general/sender_name';
    const XML_PATH_CONFIG_SENDER_EMAIL = 'installation_notify/general/sender_email';
    const XML_PATH_CONFIG_INSTALL_RECEIVE_EMAIL = 'installation_notify/install_email/receive_email';
    const XML_PATH_CONFIG_INSTALL_CC_EMAILS = 'installation_notify/install_email/cc_email';
    const XML_PATH_CONFIG_UNINSTALL_RECEIVE_EMAIL = 'installation_notify/uninstall_email/receive_email';
    const XML_PATH_CONFIG_UNINSTALL_CC_EMAILS = 'installation_notify/uninstall_email/cc_email';

    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {

        $this->scopeConfig = $scopeConfig;
    }

    protected function getConfigValue(string $path, $storeCode = null)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeCode);
    }

    /**
     * Is module enabled
     *
     * @return bool
     */
    public function getIsEnable(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CONFIG_ENABLE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get sender name
     *
     * @return string
     */
    public function getSenderName(): ?string
    {
        return $this->getConfigValue(self::XML_PATH_CONFIG_SENDER_NAME);
    }

    /**
     * Get sender email
     *
     * @return string
     */
    public function getSenderEmail(): ?string
    {
        return $this->getConfigValue(self::XML_PATH_CONFIG_SENDER_EMAIL);
    }

    /**
     * Get install receive email
     *
     * @return string
     */
    public function getInstallReceiveEmail(): ?string
    {
        return $this->getConfigValue(self::XML_PATH_CONFIG_INSTALL_RECEIVE_EMAIL);
    }

    /**
     * Get install cc emails
     *
     * @return string[]
     */
    public function getInstallCcEmails(): ?array
    {
        try {
            $q = explode(',', $this->getConfigValue(self::XML_PATH_CONFIG_INSTALL_CC_EMAILS));
            if (empty($q)) return null;
            return $q;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get uninstall receive email
     *
     * @return string
     */
    public function getUninstallReceiveEmail(): ?string
    {
        return $this->getConfigValue(self::XML_PATH_CONFIG_UNINSTALL_RECEIVE_EMAIL);
    }

    /**
     * Get uninstall cc emails
     *
     * @return string[]
     */
    public function getUninstallCcEmails(): ?array
    {
        try {
            $q = explode(',', $this->getConfigValue(self::XML_PATH_CONFIG_UNINSTALL_CC_EMAILS));
            if (empty($q)) return null;
            return $q;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
