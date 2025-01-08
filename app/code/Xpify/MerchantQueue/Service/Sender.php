<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
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
     * @param array $webhookConfig
     * @return void
     * @throws LocalizedException|\InvalidArgumentException
     */
    public function send($appId, array $data, $webhookConfig): void
    {
        $telegramConfig = $this->scopeConfig->getValue(\Xpify\MerchantQueue\Config::getTelegramConfigPath($appId));
        if (empty($webhookConfig)) {
            return;
        }
        if (!empty($telegramConfig)) {
            $telegramConfig = $this->json->unserialize($telegramConfig);
        } else {
            $telegramConfig = ['log_on_success' => false, 'enable' => false];
        }
        $enabled = $webhookConfig['enable'] ?? false;
        // If webhook is disabled, skip
        if (!$enabled) {
            return;
        }
        // if credentials or endpoint is missing, mark as failed.
        $username = $webhookConfig['username'] ?? null;
        $password = $webhookConfig['password'] ?? null;
        $endpoint = $webhookConfig['endpoint'] ?? null;
        if (!$username || !$password || !$endpoint) {
            throw new \InvalidArgumentException('Webhook configuration is invalid.');
        }
        /** @var Curl $curl */
        $curl = $this->curlFactory->create();
        // set basic auth
        $curl->addOption(CURLOPT_USERPWD, $username . ':' . $password);
        $curl->addOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//        $curl->addOption(CURLOPT_SSL_VERIFYPEER, false);
//        $curl->addOption(CURLOPT_SSL_VERIFYHOST, 0);
//        $curl->addOption(CURLOPT_FOLLOWLOCATION, true);
//        $curl->addOption(CURLOPT_VERBOSE, true);
//        $curl->addOption(CURLOPT_CAINFO, '/home/vadu/.ssl/rootCA.pem');
        $headers = ['Content-Type: application/json'];

//        $streamVerboseHandle = fopen('php://temp', 'w+');
//        $curl->addOption(CURLOPT_STDERR, $streamVerboseHandle);
        $encodedData = json_encode($data);
        $curl->write('POST', $endpoint, '1.1', $headers, $encodedData);
        $responseBody = $curl->read();
//        rewind($streamVerboseHandle);
//        $verboseLog = stream_get_contents($streamVerboseHandle);
//        dd($responseBody);
        $response = \Zend_Http_Response::fromString($responseBody);
        $httpCode = $response->getStatus();
        $curl->close();
        $shouldLogToTelegram = $telegramConfig['log_on_success'] ?? false;

        if ($httpCode === 200 && $shouldLogToTelegram) {
            \Xpify\MerchantQueue\Telegram::notify(sprintf("<b>[%s]</b>".chr(10)."Webhook sent ok.".chr(10)."<b>payload</b>: %s", gmdate('c'), json_encode($data)), $telegramConfig);
        }
        if ($httpCode != 200) {
            Logger::getLogger('webhook_sender_failure.log')->debug(sprintf('Failed to send webhook request. HTTP code: %s, body: %s', $httpCode, $responseBody));
            \Xpify\MerchantQueue\Telegram::notify(sprintf(
                "<b>[%s]</b>" . chr(10) . "Failed to send webhook request." . chr(10).chr(10) . "<b>HTTP code:</b> %s" . chr(10) . "<b>Body:</b> %s" . chr(10) . "<b>Payload:</b> %s",
                gmdate('c'),
                $httpCode,
                htmlspecialchars($response->getBody()),
                $encodedData,
            ), $telegramConfig);
            throw new LocalizedException(__("Webhook request failed with HTTP code: %1, check log file: var/log/webhook_sender_failure.log", $httpCode), null, 1400);
        }
    }
}
