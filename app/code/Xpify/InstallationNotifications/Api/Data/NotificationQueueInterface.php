<?php
declare(strict_types=1);

namespace Xpify\InstallationNotifications\Api\Data;

use Xpify\App\Api\Data\AppInterface as IApp;

interface NotificationQueueInterface
{
    const IS_SENT_YES = '1';
    const IS_PENDING = '0';
    const IS_SKIP = '2';

    const ID = 'entity_id';
    const SESSION_ID = 'session_id';
    const SHOP = 'shop';
    const APP_ID = 'app_id';
    const TYPE = 'type';
    const IS_SENT = 'is_sent';
    const CREATED_AT = 'created_at';
    const START_AT = 'start_at';
    const FINISH_AT = 'finish_at';

    public function getId();

    public function getSessionId(): ?string;

    public function setSessionId(string $sessId): self;

    public function getShop(): ?string;

    public function setShop(string $shop): self;

    public function getAppId(): ?int;

    public function setAppId(int $appId): self;

    public function getType(): ?string;

    public function setType(string $type): self;

    public function getIsSent(): ?string;

    public function setIsSent(string $isSent): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(string $createdAt): self;

    public function getStartAt(): ?string;

    public function setStartAt(?string $sentAt): self;

    public function getFinishAt(): ?string;

    public function setFinishAt(string $finishAt): self;

    public function isSent(): bool;

    public function app(): ?IApp;
}
