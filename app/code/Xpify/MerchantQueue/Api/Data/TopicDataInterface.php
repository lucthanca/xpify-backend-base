<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Api\Data;

interface TopicDataInterface
{
    const TYPE_MERCHANT_NEW = 1;
    const TYPE_MERCHANT_UPDATE = 2;
    const TYPE_MERCHANT_UNINSTALLED = 3;

    /**
     * Get session id
     *
     * @return string
     */
    public function getSessionId();

    /**
     * Set session id
     *
     * @param string $sessId
     * @return self
     */
    public function setSessionId($sessId);

    /**
     * Get app id
     *
     * @return int
     */
    public function getAppId();

    /**
     * Set app id
     *
     * @param int $appId
     * @return self
     */
    public function setAppId($appId);

    /**
     * Get a queue type
     *
     * @return int
     */
    public function getType(): int;

    /**
     * Set queue type
     *
     * @param int $type
     * @return self
     */
    public function setType($type): self;

    /**
     * Get topic data
     *
     * @return mixed
     */
    public function getData();

    /**
     * Set topic data
     *
     * @param $data
     * @return self
     */
    public function setData($data);
}
