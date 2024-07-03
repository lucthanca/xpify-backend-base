<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Observer;

class UninstallNotifyObserver extends InstallNotifyObserver
{
    const TYPE = 'uninstall';
    const TEMPLATE_ID = 'uninstall_notify_email';
}
