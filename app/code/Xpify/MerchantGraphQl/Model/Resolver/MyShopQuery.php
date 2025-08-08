<?php
declare(strict_types=1);

namespace Xpify\MerchantGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class MyShopQuery extends AuthSessionAbstractResolver implements ResolverInterface
{
    private const GET_SHOP_INFO_QUERY = <<<'QUERY'
query GetShopInfo {
    shop {
        id
        email
        shop_owner: shopOwnerName
        name
        primaryDomain { host }
        myshopify_domain: myshopifyDomain
    }
}
QUERY;


    /**
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array|mixed|string|null
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Shopify\Exception\UninitializedContextException
     */
    public function execResolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        /** @var IMerchant $merchant */
        $merchant = $context->getExtensionAttributes()->getMerchant();
        if (!$merchant?->getId()) {
            throw new GraphQlNoSuchEntityException(
                __("Please re-install the app or contact support.")
            );
        }
        $client = $merchant->getGraphql();
        if (!$client) {
            throw new GraphQlNoSuchEntityException(
                __("Please re-install the app or contact support.")
            );
        }

        $createdAtDate = new \DateTime($merchant->getCreatedAt());

        $friendlyErrMsg = __("Something went wrong. Please try again! If the problem persists, contact support.");
        // fetch shop info from db first
        try {
            $shopInfoObj = \Magento\Framework\App\ObjectManager::getInstance()->create(\SectionBuilder\Information\Model\Information::class);
            $shopInfoObj->load($merchant->getShop(), 'merchant_shop');
            if ($shopInfoObj?->getId()) {
                $shopInfo = $shopInfoObj->getShop();
                if ($shopInfo) {
                    $json = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Serialize\Serializer\Json::class);
                    $unserialized = $json->unserialize($shopInfo);
                    $unserializedShop = $unserialized['shop'] ?? [];
                    if (!empty($unserializedShop)) {
                        $shopifyDomain = $unserializedShop['myshopify_domain'] ?? $merchant->getShop();
                        return [
                            'id' => \Xpify\Core\Helper\Utils::idToUid($shopifyDomain),
                            'x_access_token' => $merchant->getXAccessToken(),
                            'domain' => $unserializedShop['domain'],
                            'email' => $unserializedShop['email'],
                            'shop_owner' => $unserializedShop['shop_owner'],
                            'name' => $unserializedShop['name'],
                            'created_at' => $createdAtDate->getTimestamp(),
                            'myshopify_domain' => $shopifyDomain,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::getLogger('fetch_my_shop_info_error.log')->debug(
                "[{$merchant->getShop()}] Error while loading shop info from database: " . $e->getMessage(),
                ['exception' => $e]
            );
            // If we can't load from DB, we will try to fetch from GraphQL
        }
        try {
            $response = $client->query(data: [
                'query' => self::GET_SHOP_INFO_QUERY,
            ],tries: 3);

            if ($response->getStatusCode() !== 200) {
                throw new GraphQlNoSuchEntityException($friendlyErrMsg);
            }
            if ($response->getDecodedBody()['errors'] ?? false) {
                Logger::getLogger('fetch_my_shop_info_error.log')->debug(
                    "[{$merchant->getShop()}] GraphQL error while fetching shop info: " . json_encode($response->getDecodedBody()['errors'])
                );
                throw new GraphQlNoSuchEntityException($friendlyErrMsg);
            }
            $result = $response->getDecodedBody()['data']['shop'] ?? null;
            if (!$result) {
                Logger::getLogger('fetch_my_shop_info_error.log')->debug(
                    "[{$merchant->getShop()}] No shop data found in response: " . json_encode($response->getDecodedBody())
                );
                throw new GraphQlNoSuchEntityException($friendlyErrMsg);
            }
            $result = array_merge($result, [
                'id' => \Xpify\Core\Helper\Utils::idToUid($result['myshopify_domain']),
                'x_access_token' => $merchant->getXAccessToken(),
                'domain' => $result['primaryDomain']['host'] ?? null,
                'created_at' => $createdAtDate->getTimestamp(),
            ]);
            unset($result['primaryDomain']);
            return $result;
        } catch (\Throwable $e) {
            Logger::getLogger('fetch_my_shop_info_error.log')->debug(
                "[{$merchant->getShop()}] Error while fetching shop info: " . $e->getMessage(),
                ['exception' => $e]
            );
            throw new GraphQlNoSuchEntityException($friendlyErrMsg);
        }
    }
}
