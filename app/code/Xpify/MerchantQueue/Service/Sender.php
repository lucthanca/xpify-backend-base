<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Adapter\Curl;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Xpify\Core\Model\Logger;
use Xpify\MerchantQueue\Plugin\SaveAppWebhookConfig;

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
     * @return void
     * @throws LocalizedException|\InvalidArgumentException
     */
    public function send($appId, array $data): void
    {
        $webhookConfig = $this->scopeConfig->getValue(\Xpify\MerchantQueue\Config::getWebhookConfigPath($appId));
        if (empty($webhookConfig)) {
            return;
        }
        $webhookConfig = $this->json->unserialize($webhookConfig);
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
        $headers = ['Content-Type: application/json'];

        $curl->write('POST', $endpoint, '1.1', $headers, json_encode($data));
        $responseBody = $curl->read();
        $response = \Zend_Http_Response::fromString($responseBody);
        $httpCode = $response->getStatus();
        $telegramConfig = $webhookConfig[SaveAppWebhookConfig::WEBHOOK_TELEGRAM_FORM_SCOPE_KEY];
        $shouldLogToTelegram = $telegramConfig['log_on_success'] ?? false;

        $notifyToTelegram = function (string $msg) use ($telegramConfig) {
            $enabled = $telegramConfig['enable'] ?? false;
            $canSendLog = $enabled && !empty($telegramConfig['bot_token']) && !empty($telegramConfig['chat_id']);
            if ($canSendLog) {
                $telegramLogger = new Telegram($telegramConfig['bot_token'], $telegramConfig['chat_id']);
                try {
                    $telegramLogger->sendMessage($msg);
                } catch (\Throwable $e) {
                    $failedMsg = sprintf(
                        'Failed to send message to telegram channel. Error: %s',
                        $e->getMessage()
                    );
                    if ($e->getCode() === 1400) {
                        $failedMsg = $e->getMessage();
                    }
                    Logger::getLogger('telegram_sender_failure.log')->debug($failedMsg);
                }
            }
        };
        if ($httpCode === 200 && $shouldLogToTelegram) {
            $notifyToTelegram(sprintf("<b>[%s]</b>".chr(10)."Webhook sent ok.".chr(10)."<b>payload</b>: %s", gmdate('c'), json_encode($data)));
        }
        if ($httpCode != 200) {
            Logger::getLogger('webhook_sender_failure.log')->debug(sprintf('Failed to send webhook request. HTTP code: %s, body: %s', $httpCode, $responseBody));
            $notifyToTelegram(sprintf(
                "<b>[%s]</b>" . chr(10) . "Failed to send webhook request." . chr(10).chr(10) . "<b>HTTP code:</b> %s" . chr(10) . "<b>Body:</b> %s",
                gmdate('c'),
                $httpCode,
                htmlspecialchars($response->getBody())
            ));
            throw new LocalizedException(__("Webhook request failed with HTTP code: %1, check log file: var/log/webhook_sender_failure.log", $httpCode), null, 1400);
        }
    }
}
