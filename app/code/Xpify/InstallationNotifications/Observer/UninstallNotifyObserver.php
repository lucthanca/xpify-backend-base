<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Observer;

class UninstallNotifyObserver extends InstallNotifyObserver
{
    const TYPE = 'uninstall';

/**
     * @inheritDoc
     */
    protected function getEmailTemplateId(): string
    {
        return 'uninstall_notify_email';
    }

    /**
     * @inheritDoc
     */
    protected function getCCEmails() : ?array
    {
        return $this->configProvider->getUninstallCcEmails();
    }

    /**
     * @inheritDoc
     */
    protected function getReceiverEmail() : ?string
    {
        return $this->configProvider->getUninstallReceiveEmail();
    }
}
