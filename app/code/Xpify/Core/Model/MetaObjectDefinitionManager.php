<?php
declare(strict_types=1);

namespace Xpify\Core\Model;

use Magento\Framework\Exception\LocalizedException;
use Shopify\Clients\Graphql;
use Xpify\Core\Helper\Config as CoreConfig;
use Xpify\Core\Model\MetaObjectDefinitionInterface as IMetaObject;
use Xpify\Core\Model\MetaObjectDefinitionUpdateHandlerInterface as IMetaObjectUpdateHandler;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class MetaObjectDefinitionManager
{
    private string $area;
    private ?IMetaObjectUpdateHandler $updateFieldHandler;
    private CoreConfig $config;

    public function __construct(
        CoreConfig $config,
        string $area = null,
        IMetaObjectUpdateHandler $updateFieldHandler = null
    ) {
        if ($area === null) {
            throw new \InvalidArgumentException("Missing area");
        }
        $this->area = $area;
        $this->updateFieldHandler = $updateFieldHandler;
        $this->config = $config;
    }

    /**
     * @param IMerchant $merchant
     * @param MetaObjectDefinitionInterface $metaObject
     * @return string
     * @throws LocalizedException
     */
    public function install(IMerchant $merchant, IMetaObject $metaObject): string
    {
        $client = $merchant->getGraphql();
        if (!$client) {
            throw new LocalizedException(__("Can not initialize GraphQl Client. Merchant maybe has uninstalled the app."));
        }
        if (empty($metaObject->getFields())) {
            throw new LocalizedException(__("MetaObject definition fields can not be empty."));
        }
        $configKey = $this->getConfigKey($merchant, $metaObject->getType());
        $metaObjectDefinitionId = $this->config->getConfig($configKey);

        try {
            if ($metaObjectDefinitionId) {
                $processedDefinitionId = $this->updateExistingDefinition(
                    $client,
                    $metaObjectDefinitionId,
                    $metaObject
                );
            } else {
                $processedDefinitionId = self::createNewDefinition($client, $metaObject);
            }

            if ($processedDefinitionId !== $metaObjectDefinitionId) {
                $this->config->setConfig($configKey, $processedDefinitionId);
                return $processedDefinitionId;
            }
            return $metaObjectDefinitionId;
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->handleException($e, $metaObject->getType());
        }
    }

    protected function updateExistingDefinition($client, string $definitionId, IMetaObject $metaObject): string
    {
        $definition = $this->fetchExistingDefinition($client, $definitionId, $metaObject->getType());
        if (!$definition) {
            return $this->createNewDefinition($client, $metaObject);
        }
        $updateFields = $this->prepareUpdateFields($definition, $metaObject);
        if (empty($updateFields)) {
            return $definitionId;
        }

        return $this->executeDefinitionUpdate($client, $definitionId, $metaObject->getType(), $updateFields);
    }

    protected function prepareUpdateFields(array $definition, IMetaObject $metaObjectDefinition): array
    {
        $updateFields = [];

        $requiredFields = $metaObjectDefinition->getFields();
        // Kiểm tra quyền truy cập storefront
        if (!empty($metaObjectDefinition->getAccess())) {
            $storeFrontAccess = $metaObjectDefinition->getAccess()['storefront'] ?? null;
            if ($storeFrontAccess !== null && $storeFrontAccess !== $definition['access']['storefront']) {
                $updateFields['access'] = ['storefront' => $storeFrontAccess];
            }
            $adminAccess = $metaObjectDefinition->getAccess()['admin'] ?? null;
            if ($adminAccess !== null && $adminAccess !== $definition['access']['admin']) {
                if (isset($updateFields['access'])) {
                    $updateFields['access']['admin'] = $adminAccess;
                } else {
                    $updateFields['access'] = ['admin' => $adminAccess];
                }
            }
        }

        // Kiểm tra khả năng publishable
        $capabilities = $metaObjectDefinition->getCapabilities();
        if (!empty($capabilities)) {
            $publishableEnabled = $capabilities['publishable']['enabled'] ?? null;
            if ($publishableEnabled !== null && $publishableEnabled !== $definition['capabilities']['publishable']['enabled']) {
                $updateFields['capabilities'] = ['publishable' => ['enabled' => $publishableEnabled]];
            }
        }

        // Xử lý các trường bị thiếu
        $existingFields = array_column($definition['fieldDefinitions'], 'key');
        $missingFields = array_diff(array_keys($requiredFields), $existingFields);

        self::handleMissingFields($updateFields, $missingFields, $requiredFields);
        self::handleFieldValidations($updateFields, $definition, $requiredFields, $missingFields);
        $this->updateFieldHandler?->handle($updateFields, $definition, $missingFields);

        return $updateFields;
    }

    protected static function handleMissingFields(array &$updateFields, array $missingFields, array $requiredFields): void
    {
        foreach ($missingFields as $missingField) {
            $updateFields['fieldDefinitions'] = [
                'create' => $requiredFields[$missingField]
            ];
        }
    }
    protected static function handleFieldValidations(array &$updateFields, array $definition, array $requiredFields, array $missingFields): void
    {
        foreach ($requiredFields as $key => $field) {
            if (in_array($key, $missingFields) || !array_key_exists("validations", $field)) {
                continue;
            }

            $remoteValidationIndex = array_search($key, array_column($definition['fieldDefinitions'], 'key'));

            if ($remoteValidationIndex === false) {
                $updateFields['fieldDefinitions'] = ['create' => $field];
                return;
            }
            $remoteField = $definition['fieldDefinitions'][$remoteValidationIndex];
            $updatedValidations = self::mergeValidations(
                $remoteField['validations'],
                $field['validations']
            );
            if ($updatedValidations !== false) {
                $updateFields['fieldDefinitions'] = [
                    'update' => [
                        'key' => $key,
                        'validations' => $updatedValidations
                    ]
                ];
            }
        }
    }
    protected static function mergeValidations(array $remoteValidations, array $localValidations): array|false
    {
        $needUpdate = false;
        foreach ($localValidations as $localValidation) {
            $remoteValidationIndex = array_search($localValidation['name'], array_column($remoteValidations, 'name'));
            if ($remoteValidationIndex === false) {
                $remoteValidations[] = $localValidation;
                $needUpdate = true;
            } elseif ($remoteValidations[$remoteValidationIndex]['value'] !== $localValidation['value']) {
                $remoteValidations[$remoteValidationIndex]['value'] = $localValidation['value'];
                $needUpdate = true;
            }
        }
        return $needUpdate ? $remoteValidations : false;
    }

    private function executeDefinitionUpdate($client, string $definitionId, string $type, array $updateFields): string
    {
        [$query, $dataKey] = $this->getQuery('UPDATE');

        $variables = [
            'id' => $definitionId,
            'definition' => $updateFields
        ];

        $definition = $this->executeDefinitionMutation($client, $query, $variables, $type, $dataKey);
        return $definition['id'];
    }

    protected function createNewDefinition(Graphql $client, IMetaObject $metaObject): string
    {
        [$query, $dataKey] = $this->getQuery('CREATE');
        $variables = [
            'definition' => [
                'name' => $metaObject->getName(),
                'type' => $metaObject->getType(),
                'fieldDefinitions' => array_values($metaObject->getFields()),
            ],
        ];
        if (!empty($metaObject->getCapabilities())) {
            $variables['definition']['capabilities'] = $metaObject->getCapabilities();
        }
        if (!empty($metaObject->getAccess())) {
            $variables['definition']['access'] = $metaObject->getAccess();
        }
        $definition = $this->executeDefinitionMutation($client, $query, $variables, $metaObject->getType(), $dataKey);

        return $definition['id'];
    }

    /**
     * @throws LocalizedException
     */
    private function executeDefinitionMutation(Graphql $client, string $query, array $variables, string $type, string $dataKey): array
    {
        $response = $client->query(compact('query', 'variables'));
        $responseBody = $response->getDecodedBody();

        if (!empty($responseBody["errors"])) {
            throw new LocalizedException(
                __("[%1] Receive response error. %2", $type, json_encode($responseBody["errors"]))
            );
        }
        if (!empty($responseBody["data"][$dataKey]['userErrors'])) {
            $errors = $responseBody["data"][$dataKey]['userErrors'];
            $message = implode(', ', array_map(fn ($e) => $e['message'], $errors));
            throw new LocalizedException(
                __("[%1] Receive user errors. %2", $type, $message)
            );
        }

        return $responseBody["data"][$dataKey]["metaobjectDefinition"];
    }

    private function fetchExistingDefinition(Graphql $client, string $definitionId, string $type): ?array
    {
        $response = $client->query([
            'query' => $this->getMetaObjectDefinitionQuery(),
            'variables' => ['id' => $definitionId]
        ]);

        $responseBody = $response->getDecodedBody();
        if (!empty($responseBody["errors"])) {
            throw new LocalizedException(
                __("[%1] Receive response error. %2", $type, json_encode($responseBody["errors"]))
            );
        }

        return $responseBody["data"]["metaobjectDefinition"] ?? null;
//        return ($definition && $definition['type'] === $type) ? $definition : null;
    }

    /**
     * Get query for create or update metaobject definition
     *
     * @param string $action
     * @return array
     */
    protected function getQuery(string $action): array
    {
        if ($action === 'CREATE') {
            return [$this->getMetaObjectDefinitionCreateMutation(), 'metaobjectDefinitionCreate'];
        }
        return [$this->getMetaObjectDefinitionUpdateMutation(), 'metaobjectDefinitionUpdate'];
    }

    /**
     * Trả về config path để lưu trữ metaobject_definition_id trong bảng core config data
     *
     * @param IMerchant $m
     * @param string $type
     * @return string
     */
    protected function getConfigKey(IMerchant $m, string $type): string
    {
        return "metaobject_definition_id/{$m->getShop()}/{$this->area}/$type";
    }

    /**
     * Trả về query để lấy metaobject definition từ graphql
     *
     * @return string
     */
    protected function getMetaObjectDefinitionQuery(): string
    {
        return self::_METAOBJECT_DEFINITION_QUERY;
    }

    protected function getMetaObjectDefinitionCreateMutation(): string
    {
        return self::_METAOBJECT_DEFINITION_CREATE_MUTATION;
    }

    protected function getMetaObjectDefinitionUpdateMutation(): string
    {
        return self::_METAOBJECT_DEFINITION_UPDATE_MUTATION;
    }

    /**
     * @throws LocalizedException
     */
    private static function handleException(\Throwable $e, string $type): void
    {
        if (!($e instanceof \Exception)) {
            $e = new \Exception($e->getMessage(), $e->getCode(), $e);
        }
        throw new LocalizedException(__("[%1] %2. %3", $type, 'Failed to process metaObject definition', $e->getMessage()), $e);
    }

    private const _METAOBJECT_DEFINITION_QUERY = <<<'QUERY'
    query GetMetaObjectDefinition($id: ID!) {
        metaobjectDefinition(id: $id) {
            access { storefront admin }
            type
            capabilities { publishable { enabled } }
            fieldDefinitions {
                key name type { name }
                validations { name value }
            }
        }
    }
QUERY;

    private const USER_ERRORS_FRAGMENT = <<<'QUERY'
    fragment UserErrorsFragment on MetaobjectUserError {
        field
        message
        code
    }
QUERY;
    private const _METAOBJECT_DEFINITION_FRAGMENT = <<<'QUERY'
    fragment MetaobjectDefinitionFragment on MetaobjectDefinition {
        id
        name
        type
        fieldDefinitions {
            key
            name
        }
    }
QUERY;

    private const _METAOBJECT_DEFINITION_CREATE_MUTATION = <<<'QUERY'
    mutation CreateMetaobjectDefinition($definition: MetaobjectDefinitionCreateInput!) {
        metaobjectDefinitionCreate(definition: $definition) {
          metaobjectDefinition {
            ...MetaobjectDefinitionFragment
          }
          userErrors {
            ...UserErrorsFragment
          }
        }
    }
QUERY . "\n" .
    self::_METAOBJECT_DEFINITION_FRAGMENT . "\n" .
    self::USER_ERRORS_FRAGMENT;

    private const _METAOBJECT_DEFINITION_UPDATE_MUTATION = <<<'QUERY'
    mutation UpdateMetaobjectDefinition($id: ID!, $definition: MetaobjectDefinitionUpdateInput!) {
        metaobjectDefinitionUpdate(id: $id, definition: $definition) {
          metaobjectDefinition {
            ...MetaobjectDefinitionFragment
          }
          userErrors {
            ...UserErrorsFragment
          }
        }
    }
QUERY . "\n" .
    self::_METAOBJECT_DEFINITION_FRAGMENT . "\n" .
    self::USER_ERRORS_FRAGMENT;
}
