<?php
declare(strict_types=1);

namespace Xpify\MerchantQueue\Webhook;

use Shopify\Webhooks\Handler;
use Xpify\App\Service\GetCurrentApp;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class ShopUpdate implements Handler
{
    private IMerchantRepository $merchantRepository;
    private GetCurrentApp $currentApp;
    private ShopifyContextInitializer $initializer;

    /**
     * @param IMerchantRepository $merchantRepository
     * @param GetCurrentApp $currentApp
     * @param ShopifyContextInitializer $initializer
     */
    public function __construct(
        IMerchantRepository $merchantRepository,
        GetCurrentApp $currentApp,
        ShopifyContextInitializer $initializer
    ) {
        $this->merchantRepository = $merchantRepository;
        $this->currentApp = $currentApp;
        $this->initializer = $initializer;
    }

    /**
     * @inheritDoc
     * @param array $body - the body shape example:
     * <pre>
     * [
     * 'id' => 548380009,
     * 'name' => 'Super Toys',
     * 'email' => 'super@supertoys.com',
     * 'domain' => 'super-toys.myshopify.com',
     * 'province' => 'Ontario',
     * 'country' => 'CA',
     * 'address1' => '123 Oak St',
     * 'zip' => 'K1M 2B9',
     * 'city' => 'Ottawa',
     * 'source' => null,
     * 'phone' => '123-123-1234',
     * 'latitude' => 45.41634,
     * 'longitude' => -75.6868,
     * 'primary_locale' => 'en',
     * 'address2' => null,
     * 'created_at' => '2019-09-06T10:00:00-04:00',
     * 'updated_at' => '2019-09-06T10:00:00-04:00',
     * 'country_code' => 'CA',
     * 'country_name' => 'Canada',
     * 'currency' => 'CAD',
     * 'customer_email' => 'customer@supertoys.com',
     * 'timezone' => '(GMT-05:00) Eastern Time (US & Canada)',
     * 'iana_timezone' => 'America/Toronto',
     * 'shop_owner' => 'John Doe',
     * 'money_format' => '${{amount}}',
     * 'money_with_currency_format' => '${{amount}} CAD',
     * 'weight_unit' => 'kg',
     * 'province_code' => 'ON',
     * 'taxes_included' => false,
     * 'auto_configure_tax_inclusivity' => null,
     * 'tax_shipping' => null,
     * 'county_taxes' => null,
     * 'plan_display_name' => 'Shopify Plus',
     * 'plan_name' => 'enterprise',
     * 'has_discounts' => false,
     * 'has_gift_cards' => true,
     * 'myshopify_domain' => 'super-toys.myshopify.com',
     * 'google_apps_domain' => null,
     * 'google_apps_login_enabled' => null,
     * 'money_in_emails_format' => '${{amount}}',
     * 'money_with_currency_in_emails_format' => '${{amount}} CAD',
     * 'eligible_for_payments' => true,
     * 'requires_extra_payments_agreement' => false,
     * 'password_enabled' => true,
     * 'has_storefront' => true,
     * 'finances' => true,
     * 'primary_location_id' => 655441491,
     * 'checkout_api_supported' => true,
     * 'multi_location_enabled' => true,
     * 'setup_required' => false,
     * 'pre_launch_enabled' => false,
     * 'enabled_presentment_currencies' => ['CAD'],
     * 'transactional_sms_enabled' => false,
     * 'marketing_sms_consent_enabled_at_checkout' => false,
     * ]
     * </pre>
     */
    public function handle(string $topic, string $shop, array $body): void
    {
        $name = $body['name'] ?? null;
        $email = $body['email'] ?? null;
        if (!$name || !$email) {
            return;
        }
        try {
            $currentApp = $this->currentApp->get();
            $searchCriteria = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
            $searchCriteria->addFilter(IMerchant::APP_ID, $currentApp->getId());
            $searchCriteria->addFilter(IMerchant::SHOP, $shop);
            $searchCriteria->setPageSize(1);
            $searchResult = $this->merchantRepository->getList($searchCriteria->create());
            $items = $searchResult->getItems();
            if (count($items) === 0) {
                Logger::getLogger('shop_update_errors.log')->debug('Merchant not found for app ID: ' . $currentApp->getId() . ' and shop: ' . $shop);
                return;
            }
            /** @var IMerchant $merchant */
            $merchant = reset($items);
            $merchant->setEmail($email);
            $merchant->setName($name);
            $this->merchantRepository->save($merchant);
        } catch (\Throwable $e) {
            Logger::getLogger('shop_update_errors.log')->debug('Failed to fetch merchant for app ID: ' . $currentApp->getId() . ' and shop: ' . $shop . ' with error: ' . $e->getMessage());
        }
    }
}
