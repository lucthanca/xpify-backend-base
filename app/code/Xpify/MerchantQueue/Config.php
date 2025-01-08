<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue;

final class Config
{
    /**
     * Get webhook config path
     *
     * @param string|int $appId
     * @return string
     */
    public static function getWebhookConfigPath($appId): string
    {
        return "xpify/merchant_queue/webhook:{$appId}";
    }

    /**
     * Get telegram config path
     *
     * @param string|int $appId
     * @return string
     */
    public static function getTelegramConfigPath(string|int $appId): string
    {
        return "xpify/merchant_queue/telegram:{$appId}";
    }
}
