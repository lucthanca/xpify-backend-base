<?php
declare(strict_types=1);

namespace Xpify\Merchant\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Shopify\Exception\ShopifyException;
use Xpify\App\Model\AppFactory;
use Xpify\App\Model\ResourceModel\App as AppResource;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Model\ResourceModel\Merchant\CollectionFactory as MerchantCollectionFactory;
use Xpify\Merchant\Model\ResourceModel\Merchant as MerchantResource;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class FetchMerchantShopInfoPatch implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private MerchantCollectionFactory $collectionFactory;
    private MerchantResource $merchantResource;
    private AppFactory $appFactory;
    private AppResource $appResource;
    private ShopifyContextInitializer $initializer;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param MerchantCollectionFactory $collectionFactory
     * @param MerchantResource $merchantResource
     * @param AppFactory $appFactory
     * @param AppResource $appResource
     * @param ShopifyContextInitializer $initializer
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        MerchantCollectionFactory $collectionFactory,
        MerchantResource $merchantResource,
        AppFactory $appFactory,
        AppResource $appResource,
        ShopifyContextInitializer $initializer
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->collectionFactory = $collectionFactory;
        $this->merchantResource = $merchantResource;
        $this->appFactory = $appFactory;
        $this->appResource = $appResource;
        $this->initializer = $initializer;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        try {
            $this->moduleDataSetup->startSetup();

            $list = $this->collectionFactory->create();
            $list->addFieldToSelect([
                'entity_id',
                'shop',
                'access_token',
                'app_id'
            ])->load();

            $iterator = $list->getIterator();
            while ($iterator->valid()) {
                /** @var IMerchant $merchant */
                $merchant = $iterator->current();
                if (empty($merchant->getAccessToken())) {
                    $iterator->next();
                    continue;
                }
                $app = $this->appFactory->create();
                $this->appResource->load($app, $merchant->getAppId());
                if (!$app || !$app->getId()) {
                    Logger::getLogger('xpify_merchant_data_patch.log')->debug('App not found for merchant: ' . $merchant->getId());$iterator->next();
                    continue;
                }
                $this->initializer->initialize($app);

                $shop_info_query = <<<QUERY
query GetShopInfo {
    shop {
		name
		myshopifyDomain
		email
	}
}
QUERY;
                $response = $merchant->getGraphql()->query(data: $shop_info_query);
                if ($response->getStatusCode() !== 200) {
                    Logger::getLogger('xpify_merchant_data_patch.log')->debug(__("Failed to fetch Shop Info: %1, app ID: %2, decodedBody: %3", $merchant->getShop(), $merchant->getAppId(), json_encode($response->getDecodedBody()))->render());
                    $iterator->next();
                    continue;
                }
                $decodedBody = $response->getDecodedBody();
                $shopInfo = $decodedBody['data']['shop'];
                $merchant->setEmail($shopInfo['email']);
                $merchant->setName($shopInfo['name']);
                $this->merchantResource->save($merchant);
                $iterator->next();
            }

            $this->moduleDataSetup->endSetup();
        } catch (\Throwable $e) {
            if ($e instanceof ShopifyException) {
                throw $e;
            }
            Logger::getLogger('xpify_merchant_data_patch.log')->debug('Failed to fetch shop info for merchant with ID: ' . $merchant->getId() . ' with error: ' . $e->getMessage());
        }
    }
}
