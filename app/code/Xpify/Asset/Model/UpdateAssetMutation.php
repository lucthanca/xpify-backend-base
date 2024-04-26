<?php
declare(strict_types=1);

namespace Xpify\Asset\Model;

class UpdateAssetMutation
{
    /**
     * Execute.
     *
     * @param \Xpify\Merchant\Api\Data\MerchantInterface $merchant
     * @param array $args
     * @return array|mixed|string|null
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \Shopify\Exception\UninitializedContextException
     */
    public function resolve(
        \Xpify\Merchant\Api\Data\MerchantInterface $merchant,
        array $args
    ) {
        $apiVersion = \Shopify\Context::$API_VERSION;
        $themeId = $args['theme_id'];

        $response = $merchant->getRest()->put(
            "/admin/api/$apiVersion/themes/$themeId/assets.json",
            [
                "asset" => [
                    "key" => $args['asset'],
                    "value" => $args['value'],
                ],
            ]
        );

        return $response->getDecodedBody()['asset'] ?? $response->getDecodedBody();
    }
}
