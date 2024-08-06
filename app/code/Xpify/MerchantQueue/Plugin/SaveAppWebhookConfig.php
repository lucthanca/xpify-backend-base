<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface as IScopeConfig;
use Magento\Framework\App\Request\DataPersistorInterface as IDataPersistor;
use Magento\Framework\Message\ManagerInterface as IManager;
use Magento\Framework\Serialize\Serializer\Json;
use Xpify\App\Controller\Adminhtml\Apps\Save;
use Xpify\MerchantQueue\Config;
use Magento\Framework\App\Config\Storage\WriterInterface as IConfigWriter;
use Magento\Framework\App\Cache\TypeListInterface as ICacheTypeList;
use Xpify\App\Ui\Component\Form\AppDataProvider;

class SaveAppWebhookConfig
{
    const WEBHOOK_FORM_SCOPE_KEY = 'webhook';
    const WEBHOOK_TELEGRAM_FORM_SCOPE_KEY = 'telegram';
    const TELEGRAM_BOT_TOKEN_PLACEHOLDER = '********';

    private IManager $messageManager;
    private IConfigWriter $configWriter;
    private \Magento\Framework\Serialize\Serializer\Json $json;
    private ICacheTypeList $cache;
    private IDataPersistor $dataPersistor;
    private IScopeConfig $scopeConfig;

    /**
     * @param IManager $messageManager
     * @param IConfigWriter $configWriter
     * @param Json $json
     * @param ICacheTypeList $cache
     * @param IDataPersistor $dataPersistor
     * @param IScopeConfig $scopeConfig
     */
    public function __construct(
        IManager $messageManager,
        IConfigWriter $configWriter,
        \Magento\Framework\Serialize\Serializer\Json $json,
        ICacheTypeList $cache,
        IDataPersistor $dataPersistor,
        IScopeConfig $scopeConfig
    ) {
        $this->messageManager = $messageManager;
        $this->configWriter = $configWriter;
        $this->json = $json;
        $this->cache = $cache;
        $this->dataPersistor = $dataPersistor;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param Save $subject
     * @param $result
     * @return mixed
     */
    public function afterExecute(Save $subject, $result)
    {
        $postData = $subject->getRequest()->getPost();
        $webhookConfig = $postData->toArray()[AppDataProvider::OTHER_CONFIGURATION_FIELDSET_NAME][static::WEBHOOK_FORM_SCOPE_KEY] ?? [];
        $appId = $postData->toArray()[AppDataProvider::GENERAL_FIELDSET_NAME]['entity_id'] ?? null;

        try {
            if (empty($webhookConfig) || empty($appId)) {
                if (empty($appId)) {
                    throw new \Exception('App ID empty!');
                }
                return $result;
            }
            // check telegram bot_token if bot_token field is ********, get current value from config and set it to webhookConfig
            $webhookKey = static::WEBHOOK_TELEGRAM_FORM_SCOPE_KEY;
            $placeholder = static::TELEGRAM_BOT_TOKEN_PLACEHOLDER;

            $botToken = $webhookConfig[$webhookKey]['bot_token'] ?? null;

            if ($botToken === $placeholder) {
                $storedWebhookConfig = $this->scopeConfig->getValue(Config::getWebhookConfigPath($appId));

                if (!empty($storedWebhookConfig)) {
                    $currentWebhookConfig = $this->json->unserialize($storedWebhookConfig);
                    $botToken = $currentWebhookConfig[$webhookKey]['bot_token'] ?? '';
                } else {
                    $botToken = '';
                }

                $webhookConfig[$webhookKey]['bot_token'] = $botToken;
            }
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
