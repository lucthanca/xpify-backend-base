<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Webhook;

use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Xpify\Core\Model\Logger;
use Xpify\MerchantQueue\Model\ConfigProvider;

class Sender
{
    private CurlFactory $curlFactory;
    private ConfigProvider $config;

    /**
     * @param CurlFactory $curlFactory
     * @param ConfigProvider $config
     */
    public function __construct(
        CurlFactory $curlFactory,
        ConfigProvider $config
    ) {
        $this->curlFactory = $curlFactory;
        $this->config = $config;
    }

    /**
     * Send webhook request
     *
     * @param array $data
     * @return bool
     */
    public function send(array $data)
    {
        $username = $this->config->getWebhookUsername();
        $password = $this->config->getWebhookPassword();
        $endpoint = $this->config->getWebhookEndpoint();
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
