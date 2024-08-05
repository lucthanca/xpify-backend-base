<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Plugin;

use Magento\Framework\App\Request\DataPersistorInterface as IDataPersistor;
use Magento\Framework\Message\ManagerInterface as IManager;
use Magento\Framework\Serialize\Serializer\Json;
use Xpify\App\Controller\Adminhtml\Apps\Save;
use Xpify\MerchantQueue\Config;
use Magento\Framework\App\Config\Storage\WriterInterface as IConfigWriter;
use Magento\Framework\App\Cache\TypeListInterface as ICacheTypeList;

class SaveAppWebhookConfig
{
    const WEBHOOK_FORM_KEY = 'other_configuration';

    private IManager $messageManager;
    private IConfigWriter $configWriter;
    private \Magento\Framework\Serialize\Serializer\Json $json;
    private ICacheTypeList $cache;
    private IDataPersistor $dataPersistor;

    /**
     * @param IManager $messageManager
     * @param IConfigWriter $configWriter
     * @param Json $json
     * @param ICacheTypeList $cache
     * @param IDataPersistor $dataPersistor
     */
    public function __construct(
        IManager $messageManager,
        IConfigWriter $configWriter,
        \Magento\Framework\Serialize\Serializer\Json $json,
        ICacheTypeList $cache,
        IDataPersistor $dataPersistor
    ) {
        $this->messageManager = $messageManager;
        $this->configWriter = $configWriter;
        $this->json = $json;
        $this->cache = $cache;
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * @param Save $subject
     * @param $result
     * @return mixed
     */
    public function afterExecute(Save $subject, $result)
    {
        $postData = $subject->getRequest()->getPost();
        $webhookConfig = $postData->toArray()[static::WEBHOOK_FORM_KEY]['webhook'] ?? [];
        $appId = $postData->toArray()['general']['entity_id'] ?? null;
        if (empty($webhookConfig) || empty($appId)) {
            if (empty($appId)) {
                $this->messageManager->addErrorMessage(__("App ID empty! Debug di"));
            }
            return;
        }

        try {
            $this->configWriter->save(Config::getWebhookConfigPath($appId), $this->json->serialize($webhookConfig));
            $this->cache->cleanType('config');
        } catch (\Throwable $e) {
            $this->messageManager->getMessages(true);
            $this->messageManager->addErrorMessage(__("Failed to save 'Configuration / Data Webhook' config! Debug di. %1", $e->getMessage()));
            $this->dataPersistor->set('xpify_app_config_webhook', $webhookConfig);
        }

        return $result;
    }
}
