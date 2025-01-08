<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue;

use Xpify\Core\Model\Logger;

final class Telegram
{
    public static function notify(string $msg, array $telegramConfig)
    {
        $enabled = $telegramConfig['enable'] ?? false;
        $canSendLog = $enabled && !empty($telegramConfig['bot_token']) && !empty($telegramConfig['chat_id']);
        if ($canSendLog) {
            $telegramLogger = new \Xpify\MerchantQueue\Service\Telegram($telegramConfig['bot_token'], $telegramConfig['chat_id']);
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
    }
}
