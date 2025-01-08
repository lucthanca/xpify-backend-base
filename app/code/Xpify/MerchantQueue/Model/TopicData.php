<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Model;

use Xpify\MerchantQueue\Api\Data\TopicDataInterface;

class TopicData implements TopicDataInterface
{
    protected $session_id;
    protected $app_id;
    protected $data;
    protected int $type;

    public function getSessionId()
    {
        return $this->session_id;
    }

    public function setSessionId($sessId)
    {
        $this->session_id = $sessId;
        return $this;
    }

    public function getAppId()
    {
        return $this->app_id;
    }

    public function setAppId($appId)
    {
        $this->app_id = $appId;
        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function getType(): int
    {
        return (int) $this->type;
    }

    public function setType($type): TopicDataInterface
    {
        $this->type = (int) $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getTypeAsName(): string
    {
        $TYPES = [
            static::TYPE_MERCHANT_NEW => 'New Installation',
            static::TYPE_MERCHANT_UPDATE => 'Merchant Info Update',
            static::TYPE_MERCHANT_UNINSTALLED => 'Merchant Uninstalled',
        ];
        return $TYPES[$this->type] ?? 'Unknown';
    }
}
