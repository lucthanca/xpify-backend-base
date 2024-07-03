<?php

namespace Xpify\InstallationNotifications\Model\Resolver;

use Xpify\InstallationNotifications\Api\Data\NotificationQueueInterface as IQueue;
use Xpify\InstallationNotifications\Model\ConfigProvider;
use Xpify\InstallationNotifications\Model\RecipientResolverInterface as IRecipientResolver;

class InstallEmailRecipientResolver implements IRecipientResolver
{
    protected ConfigProvider $configProvider;

    /**
     * @param ConfigProvider $configProvider
     */
    public function __construct(ConfigProvider $configProvider)
    {
        $this->configProvider = $configProvider;
    }

    public function get(IQueue $queue): string
    {
        return $this->configProvider->getInstallReceiveEmail() ?: '';
    }

    public function getCC(IQueue $queue): array
    {
        return $this->configProvider->getInstallCcEmails() ?: [];
    }
}
