<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

use Magento\Framework\Exception\LocalizedException;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class MetaObjectManager
{
    private string $area;
    public function __construct(
        string $area = null
    ) {
        if ($area === null) {
            throw new \InvalidArgumentException("Missing area");
        }
        $this->area = $area;
    }

    /**
     * Fetch the fields of a metaobject by its type and handle
     *
     * @param IMerchant $merchant
     * @param array $handle
     * @return array|null
     * @throws LocalizedException
     * @throws \JsonException
     * @throws \Shopify\Exception\HttpRequestException
     * @throws \Shopify\Exception\MissingArgumentException
     */
    public function getMetaObjectFieldsByHandle(IMerchant $merchant, array $handle): ?array
    {
        if (empty($handle['type']) || empty($handle['handle'])) {
            throw new LocalizedException(__("Metaobject type and handle is required"));
        }

        $client = $merchant->getGraphql();
        if (!$client) {
            throw new LocalizedException(__("Can not initialize GraphQl Client. Merchant maybe has uninstalled the app."));
        }

        $variables = [
            'handle' => $handle,
        ];

        $query = str_replace('{self::_METAOBJECT_FIELDS_QUERY_KEY}', self::_METAOBJECT_FIELDS_QUERY_KEY, self::_METAOBJECT_FIELDS_QUERY);
        $response = $client->query(compact('query', 'variables'));
        $responseBody = $response->getDecodedBody();
        if (!empty($responseBody["errors"])) {
            throw new LocalizedException(
                __("[%1] Receive response error. %2", $merchant->getShop(), json_encode($responseBody["errors"]))
            );
        }
        return array_column($responseBody["data"][self::_METAOBJECT_FIELDS_QUERY_KEY]["fields"] ?? [], 'value', 'key');
    }

    /**
     * Get the config key for a metaobject
     *
     * @param IMerchant $m
     * @param MetaObject $metaobject
     * @return string
     */
    public function getConfigKey(IMerchant $m, MetaObject $metaobject): string
    {
        return "metaobject_id/{$m->getShop()}/{$this->area}/{$metaobject->getType()}/{$metaobject->getHandle()}";
    }

    /**
     * Retrieves a metaobject by handle, then updates it with the provided input values. If no matching metaobject is found, a new metaobject is created with the provided input values.
     *
     * @param IMerchant $merchant
     * @param MetaObject $metaobject
     * @return string
     * @throws LocalizedException
     * @throws \JsonException
     * @throws \Shopify\Exception\HttpRequestException
     * @throws \Shopify\Exception\MissingArgumentException
     */
    public function upsert(IMerchant $merchant, MetaObject $metaobject): string
    {
        $client = $merchant->getGraphql();
        if (!$client) {
            throw new LocalizedException(__("Can not initialize GraphQl Client. Merchant maybe has uninstalled the app."));
        }

        if (!$metaobject->getType()) {
            throw new LocalizedException(__("Metaobject type is required"));
        }
        if (!$metaobject->getHandle()) {
            throw new LocalizedException(__("Metaobject handle is required"));
        }

        if (empty($metaobject->getFields())) {
            throw new LocalizedException(__("Metaobject fields is required"));
        }

        $metaobjectInput = $metaobject->__toArray();
        // no type when upsert
        unset($metaobjectInput['type']);
        $variables = [
            'handle' => [
                'handle' => $metaobject->getHandle(),
                'type' => $metaobject->getType(),
            ],
            'metaobject' => $metaobjectInput,
        ];
        $query = str_replace('{self::_METAOBJECT_UPSERT_MUTATION_KEY}', self::_METAOBJECT_UPSERT_MUTATION_KEY, self::_METAOBJECT_UPSERT_MUTATION);
        $response = $client->query(compact('query', 'variables'));
        $responseBody = $response->getDecodedBody();
        if (!empty($responseBody["errors"])) {
            throw new LocalizedException(
                __("[%1] Receive response error. %2", $merchant->getShop(), json_encode($responseBody["errors"]))
            );
        }
        if (!empty($responseBody["data"][self::_METAOBJECT_UPSERT_MUTATION_KEY]['userErrors'])) {
            $errors = $responseBody["data"][self::_METAOBJECT_UPSERT_MUTATION_KEY]['userErrors'];
            $message = implode(', ', array_map(fn ($e) => $e['message'], $errors));
            throw new LocalizedException(
                __("[%1] Receive user errors. %2", $merchant->getShop(), $message)
            );
        }
        return $responseBody["data"][self::_METAOBJECT_UPSERT_MUTATION_KEY]["metaobject"]["id"];
    }

    private const _METAOBJECT_FIELDS_QUERY_KEY = 'metaobjectByHandle';
    private const _METAOBJECT_FIELDS_QUERY = <<<'QUERY'
    query GetMetaObjectByHandle($handle: MetaobjectHandleInput!) {
        {self::_METAOBJECT_FIELDS_QUERY_KEY}(handle: $handle) {
            fields {key value}
        }
    }
QUERY;


    private const _METAOBJECT_UPSERT_MUTATION_KEY = 'metaobjectUpsert';
    private const _METAOBJECT_UPSERT_MUTATION = <<<'QUERY'
    mutation UpsertMetaobject($handle: MetaobjectHandleInput!, $metaobject: MetaobjectUpsertInput!) {
        {self::_METAOBJECT_UPSERT_MUTATION_KEY}(handle: $handle, metaobject: $metaobject) {
            metaobject {id}
            userErrors {
              field
              message
              code
            }
        }
    }
QUERY;

}
