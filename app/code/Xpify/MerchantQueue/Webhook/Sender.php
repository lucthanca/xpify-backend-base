<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Webhook;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Xpify\Core\Model\Logger;

class Sender
{
    private CurlFactory $curlFactory;
    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;
    private \Magento\Framework\Serialize\Serializer\Json $json;

    /**
     * @param CurlFactory $curlFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     */
    public function __construct(
        CurlFactory $curlFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Serialize\Serializer\Json $json
    ) {
        $this->curlFactory = $curlFactory;
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
    }

    /**
     * Send webhook request
     *
     * @param string|int $appId
     * @param array $data
     * @return bool
     */
    public function send($appId, array $data)
    {
        $webhookConfig = $this->scopeConfig->getValue(\Xpify\MerchantQueue\Config::getWebhookConfigPath($appId));
        if (empty($webhookConfig)) {
            return true;
        }
        $webhookConfig = $this->json->unserialize($webhookConfig);
        $enabled = $webhookConfig['enable'] ?? false;
        // If webhook is disabled, skip
        if (!$enabled) {
            return true;
        }
        // if credentials or endpoint is missing, mark as failed.
        $username = $webhookConfig['username'] ?? null;
        $password = $webhookConfig['password'] ?? null;
        $endpoint = $webhookConfig['endpoint'] ?? null;
        if (!$username || !$password || !$endpoint) {
            return false;
        }
        /** @var Curl $curl */
        $curl = $this->curlFactory->create();
        // set basic auth
        $curl->addOption(CURLOPT_USERPWD, $username . ':' . $password);
        $curl->addOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $headers = [
            'Content-Type: application/json'
        ];

        $curl->write('POST', $endpoint, '1.1', $headers, json_encode($data));
        $responseBody = $curl->read();
        $httpCode = \Zend_Http_Response::extractCode($responseBody);
        if ($httpCode != 200) {
            Logger::getLogger('webhook_sender_failure.log')->debug(sprintf('Failed to send webhook request. HTTP code: %s, body: %s', $httpCode, $responseBody));
            return false;
        }
        return true;
    }
}
