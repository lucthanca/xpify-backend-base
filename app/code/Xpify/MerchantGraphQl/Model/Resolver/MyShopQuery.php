<?php
declare(strict_types=1);

namespace Xpify\MerchantGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;

class MyShopQuery extends AuthSessionAbstractResolver implements ResolverInterface
{
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
        $merchant = $context->getExtensionAttributes()->getMerchant();
        $apiVersion = \Shopify\Context::$API_VERSION;

        $response = $merchant->getRest()->get(
            "/admin/api/$apiVersion/shop.json"
        );

        $uid = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\GraphQl\Query\Uid::class);
        $shop = $response->getDecodedBody()['shop'] ?? null;
        return is_array($shop) ? array_merge($shop, ['id' => $uid->encode((string) $shop['domain'])]) : null;
    }
}
