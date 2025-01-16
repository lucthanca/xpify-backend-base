<?php
declare(strict_types=1);

namespace Xpify\Core\Test;
use PHPUnit\Framework\TestCase;
use Xpify\App\Model\App;

class TestAbstract extends TestCase
{
    protected const APP_HANDLE = 'xpify-section-builder';
    protected const SETUP_HOST = 'api-xpify.vadu.io.vn';
    protected const APP_DATA = [
        'entity_id' => 2,
        'remote_id' => '93819633665',
        'name' => 'xpify-section-builder',
        'api_key' => '04c480ae1b7a9b83e2fbab2cc8639b01',
        'secret_key' => '0795e31641249d2f82e75b6c515d8351',
        'scopes' => 'read_themes,write_metaobject_definitions,write_metaobjects,write_themes',
        'api_version' => '2024-10',
        'token' => 'xd7ycwy47u4yrg60wvxipe3vfh7im3hc',
        'created_at' => '2024-03-05 16:06:14',
        'handle' => self::APP_HANDLE
    ];
    protected const MERCHANT_DATA = [
        'entity_id' => 27,
        'session_id' => 'offline_vadu-test-store-3.myshopify.com',
        'shop' => 'vadu-test-store-3.myshopify.com',
        'is_online' => 0,
        'state' => 'ffcd78bf-e143-4835-97f0-e8aea9d2b33a',
        'scope' => 'write_metaobject_definitions,write_metaobjects,write_themes',
        'access_token' => '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',
        'app_id' => 2,
        'created_at' => '2024-09-25 19:22:13',
        'email' => 'lucthanca@gmail.com',
        'name' => 'boyvjppro9x Store',
    ];

    protected function getTestAppData(): array
    {
        return static::APP_DATA;
    }
    protected function getTestMerchantData(): array
    {
        return static::MERCHANT_DATA;
    }

    protected function setUp(): void
    {
        $this->app = $this->createPartialMock(App::class, []);
        $this->merchant = $this->createMock(\Xpify\Merchant\Model\Merchant::class);
        $this->getCurrentApp = $this->createMock(\Xpify\App\Service\GetCurrentApp::class);
        $loggerMock = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->scopeConfigMock = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $merchantStorageMock = $this->createMock(\Xpify\Merchant\Service\MerchantStorage::class);
        $this->getCurrentApp->method('get')->willReturn($this->app);
        $this->initializer = new \Xpify\Core\Helper\ShopifyContextInitializer(
            $loggerMock,
            $this->scopeConfigMock,
            $merchantStorageMock
        );
        $this->app->setData($this->getTestAppData());
        $this->scopeConfigMock->method('getValue')->willReturnCallback(function ($path, $scopeType = null, $scopeCode = null) {
            if ($path === 'web/secure/base_url' && $scopeType === \Magento\Store\Model\ScopeInterface::SCOPE_STORE) {
                return static::SETUP_HOST;
            }
            return null;
        });
        $this->initializer->initialize($this->app);
        $this->merchant->setData($this->getTestMerchantData());
    }
}
