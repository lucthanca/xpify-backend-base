<?php
declare(strict_types=1);

namespace Xpify\Merchant\Helper;

use Xpify\Core\Exception\ShopifyQueryException;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

trait GraphqlQueryTrait
{
    /**
     * Query graphql
     *
     * @param IMerchant $m - the merchant
     * @param string|array $query - the graphql query
     * @return array
     * @throws ShopifyQueryException
     */
    private static function query(Imerchant $m, string|array $query): array
    {
        try {
            $response = $m->getGraphql()->query($query);
            $responseBody = $response->getDecodedBody();
            if (!empty($responseBody["errors"])) {
                throw new ShopifyQueryException("Receive response error.", (array) $responseBody["errors"]);
            }
        } catch (ShopifyQueryException $e) {
            throw $e;
        } catch (\Throwable $e) {
            self::getLogger()?->debug($e->getMessage() . PHP_EOL . $e->getTraceAsString());
            throw new ShopifyQueryException($e->getMessage(), [], $e);
        }

        return $responseBody;
    }
}
