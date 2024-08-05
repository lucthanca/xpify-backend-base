<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Plugin;

use Magento\Framework\App\Request\DataPersistorInterface as IDataPersistor;
use Magento\Framework\Serialize\Serializer\Json;
use Xpify\App\Ui\Component\Form\AppDataProvider as PluggedAppDataProvider;
use Magento\Framework\App\RequestInterface as IRequest;
use Magento\Framework\App\Config\ScopeConfigInterface as IScopeConfig;
use Xpify\MerchantQueue\Config;

class LoadAppWebhookConfig
{
    private IRequest $request;
    private IScopeConfig $scopeConfig;
    private \Magento\Framework\Serialize\Serializer\Json $json;
    private IDataPersistor $dataPersistor;

    /**
     * @param IRequest $request
     * @param IScopeConfig $scopeConfig
     * @param Json $json
     * @param IDataPersistor $dataPersistor
     */
    public function __construct(
        IRequest $request,
        IScopeConfig $scopeConfig,
        \Magento\Framework\Serialize\Serializer\Json $json,
        IDataPersistor $dataPersistor
    ) {
        $this->request = $request;
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * Load webhook config data
     *
     * @param PluggedAppDataProvider $subject
     * @param array $loadedData
     * @return array
     */
    public function afterGetData(PluggedAppDataProvider $subject, $loadedData)
    {
        $appId = $this->request->getParam('id');
        if (empty($appId)) {
            return $loadedData;
        }
        $appWebhookConfig = $this->dataPersistor->get('xpify_app_config_webhook');
        if (empty($appWebhookConfig)) {
            $appWebhookConfig = $this->scopeConfig->getValue(Config::getWebhookConfigPath($appId));
            if (!empty($appWebhookConfig)) {
                $appWebhookConfig = $this->json->unserialize($appWebhookConfig);
            }
        } else {
            $this->dataPersistor->clear('xpify_app_config_webhook');
        }

        if (!empty($appWebhookConfig)) {
            $loadedData[$appId][SaveAppWebhookConfig::WEBHOOK_FORM_KEY]['webhook'] = $appWebhookConfig;
        }
        return $loadedData;
    }
}
