<?php
declare(strict_types=1);

namespace Xpify\Webhook\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface as IRequest;
use Shopify\Clients\HttpHeaders;
use Shopify\Exception\InvalidWebhookException;
use Shopify\Exception\ShopifyException;
use Shopify\Exception\WebhookRegistrationException;
use Shopify\Webhooks\Delivery\HttpDelivery;
use Shopify\Webhooks\Registry;
use Xpify\App\Api\AppRepositoryInterface as IAppRepository;
use Xpify\App\Api\Data\AppInterface as IApp;
use Xpify\App\Service\GetCurrentApp;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\Merchant\Api\MerchantRepositoryInterface;
use Xpify\Webhook\Model\WebhookTopicInterface as IWebhookTopic;

class Webhook
{
    const WEBHOOK_PATH = '/api/webhook/index';

    private IRequest $request;
    private IAppRepository $appRepository;
    private SearchCriteriaBuilder $criteriaBuilder;
    private ShopifyContextInitializer $contextInitializer;
    private GetCurrentApp $getCurrentApp;
    private MerchantRepositoryInterface $merchantRepository;
    private WebhookHandlerRegister $webhookHandlerRegister;

    /**
     * @param IRequest $request
     * @param IAppRepository $appRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param ShopifyContextInitializer $contextInitializer
     * @param GetCurrentApp $getCurrentApp
     * @param MerchantRepositoryInterface $merchantRepository
     * @param WebhookHandlerRegister $webhookHandlerRegister
     */
    public function __construct(
        IRequest $request,
        IAppRepository $appRepository,
        SearchCriteriaBuilder $criteriaBuilder,
        ShopifyContextInitializer $contextInitializer,
        GetCurrentApp $getCurrentApp,
        MerchantRepositoryInterface $merchantRepository,
        WebhookHandlerRegister $webhookHandlerRegister
    ) {
        $this->request = $request;
        $this->appRepository = $appRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->contextInitializer = $contextInitializer;
        $this->getCurrentApp = $getCurrentApp;
        $this->merchantRepository = $merchantRepository;
        $this->webhookHandlerRegister = $webhookHandlerRegister;
    }

    /**
     * Register a webhook
     *
     * This method is used to register a webhook for a given topic. It first retrieves the shop and access token from the merchant object.
     * Then it tries to register the webhook using the Registry::register method. If the registration is successful, it logs a success message.
     * If the registration fails, it logs a failure message.
     * If an exception is caught, it logs an error message.
     *
     * @param string $topic The topic to register the webhook for
     * @param string $merchantDomain The merchant domain
     * @param string $accessToken
     * @param IApp $app The app object
     * @return bool
     * @deprecated
     */
    public function registerV1(string $topic, string $merchantDomain, string $accessToken, IApp $app): bool
    {
        $shop = $merchantDomain;

        try {
            $this->contextInitializer->initialize($app);
            $response = Registry::register(static::WEBHOOK_PATH . "/_rid/{$app->getRemoteId()}", $topic, $shop, $accessToken);
            if ($response->isSuccess()) {
                return true;
            }
            $this->getLogger()?->debug(__("Failed to register APP_UNINSTALLED webhook for shop $shop with response body: %1", print_r($response->getBody(), true))->render());
        } catch (\Throwable $e) {
            $this->getLogger()?->debug(__("Failed to register APP_UNINSTALLED webhook for shop $shop with response body: %1", $e)->render());
        }
        return false;
    }

    /**
     * Execute register webhook, it will compare the existing webhooks and update or create new one. The developer should define the webhook topic in di.xml
     *
     * @see Xpify/Auth/etc/frontend/di.xml
     * @param string $merchantId
     * @return void
     * @throws ShopifyException
     * @throws \Shopify\Exception\MissingArgumentException|\Exception
     */
    public function register(string $merchantId): void
    {
        $app = $this->appOrException();
        $criteriaBuilder = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $criteriaBuilder->addFilter('session_id', $merchantId);
        $criteriaBuilder->addFilter('app_id', $app->getId());
        $criteriaBuilder->setPageSize(1);
        $result = $this->merchantRepository->getList($criteriaBuilder->create());
        if (!$result->getTotalCount()) {
            throw new \Exception('Merchant not found');
        }
        $merchant = current($result->getItems());
        $webhookRegistry = $this->webhookHandlerRegister->getWebhookRegistry($app->getName());

        $existingHandlers = $this->getExistingWebhookHandlers($merchant);
        $privacyTopics = [
            'CUSTOMERS_DATA_REQUEST',
            'CUSTOMERS_REDACT',
            'SHOP_REDACT',
        ];
        $updateOrCreate = [];
        foreach ($webhookRegistry as $topic) {
            if (in_array($topic->topicForStorage(), $privacyTopics)) {
                unset($existingHandlers[$topic->topicForStorage()]);
                continue;
            }

            $updateOrCreate[$topic->topicForStorage()] = [
                'topic' => $topic,
            ];
            if (isset($existingHandlers[$topic->topicForStorage()])) {
                $existingHandler = $existingHandlers[$topic->topicForStorage()];
                unset($existingHandlers[$topic->topicForStorage()]);

                // compare registry and existing handlers
                $includeFieldsEqual = $existingHandler['include_fields'] === $topic->getIncludeFields();
                $metafieldNamespacesEqual = $existingHandler['metafield_namespaces'] === $topic->getMetafieldNamespaces();
                if (!$includeFieldsEqual || !$metafieldNamespacesEqual) {
                    $updateOrCreate[$topic->topicForStorage()]['id'] = $existingHandler['id'];
                } else {
                    unset($updateOrCreate[$topic->topicForStorage()]);
                }
            }
        }
        $this->updateOrCreateWebhooks($merchant, $updateOrCreate);

        // remove the rest of existing handlers
        foreach ($existingHandlers as $handler) {
            $this->deleteWebhook($merchant, $handler);
        }
    }

    /**
     * Execute delete webhook
     *
     * @param IMerchant $merchant
     * @param array $handler
     * @return void
     */
    private function deleteWebhook(IMerchant $merchant, array $handler): void
    {
        try {
            $app = $this->appOrException();
            $topic = [
                'operation' => 'delete',
                'id' => $handler['id'],
            ];
            $query = $this->buildWebhookMutation($topic, $app);
            $client = $merchant->getGraphql();
            $isSuccess = function ($body) {
                return !empty($result['data'][$this->getMutationName(null, $topic['operation'])]['deletedWebhookSubscriptionId']);
            };
            $response = $client->query(data: $query);
            $statusCode = $response->getStatusCode();
            $body = $response->getDecodedBody();
            if ($statusCode !== 200) {
                throw new WebhookRegistrationException(
                    __("Failed to delete webhook with Shopify (status code $statusCode): $body")->render()
                );
            }
            if (!$isSuccess($body)) {
                throw new WebhookRegistrationException(
                    __("Failed to delete webhook with Shopify: $body")->render()
                );
            }
        } catch (WebhookRegistrationException $e) {
            $this->getLogger('webhook_register.log')?->debug("[APP: {$app->getName()}][Merchant: {$merchant->getShop()}] - {$e->getMessage()}");
        } catch (\Throwable $e) {
            $this->getLogger('webhook_register.log')?->debug("[APP: {$app->getName()}][Merchant: {$merchant->getShop()}] - Failed to delete webhook with Shopify: {$e->getMessage()}");
        }
    }

    /**
     * Create or update webhooks
     *
     * @param IMerchant $merchant
     * @param array $handlers
     * @return void
     * @throws \Exception
     */
    private function updateOrCreateWebhooks(IMerchant $merchant, array $handlers): void
    {
        $app = $this->appOrException();
        $client = $merchant->getGraphql();
        $isSuccessQuery = function ($body, ?string $webhookId) {
            return !empty($result['data'][$this->getMutationName($webhookId)]['webhookSubscription']);
        };
        foreach ($handlers as $handler) {
            try {
                $query = $this->buildWebhookMutation($handler, $app);
                $response = $client->query(data: $query);
                $statusCode = $response->getStatusCode();
                $body = $response->getDecodedBody();
                if ($statusCode !== 200) {
                    throw new WebhookRegistrationException(
                        <<<ERROR
                    Failed to register webhook with Shopify (status code $statusCode):
                    $body
                    ERROR
                    );
                }
                if (!$isSuccessQuery($body, $handler['id'] ?? null)) {
                    throw new WebhookRegistrationException(
                        <<<ERROR
                    Failed to register webhook with Shopify:
                    $body
                    ERROR
                    );
                }
            } catch (WebhookRegistrationException $e) {
                $this->getLogger('webhook_register.log')?->debug("[APP: {$app->getName()}][Merchant: {$merchant->getShop()}] - {$e->getMessage()}");
            } catch (\Throwable $e) {
                $this->getLogger('webhook_register.log')?->debug("[APP: {$app->getName()}][Merchant: {$merchant->getShop()}] - Failed to register webhook with Shopify: {$e->getMessage()}");
            }
        }
    }

    /**
     * Get existing webhook handlers
     *
     * This method is used to get all existing webhook handlers for a given shop. It first retrieves the GraphQL client from the merchant object.
     * Then it tries to get the existing webhook handlers using the GraphQL client. If the request is successful, it returns an array containing the existing webhook handlers.
     * If the request fails, it logs an error message and throws a ShopifyException.
     *
     * @param IMerchant $merchant The merchant object
     * @param string|null $endcursor
     * @return array An array containing the existing webhook handlers
     * @throws ShopifyException
     */
    private function getExistingWebhookHandlers(IMerchant $merchant, ?string $endcursor = null): array
    {
        $client = $merchant->getGraphql();
        try {
            $response = $client->query(data: $this->buildGetHandlersQuery($endcursor));
            if ($response->getStatusCode() !== 200) {
                throw new ShopifyException(__("Failed to get existing webhook handlers for shop %1", $merchant->getShop())->render());
            }
            $decodedBody = $response->getDecodedBody();

            $hasNextPage = $decodedBody['data']['webhookSubscriptions']['pageInfo']['hasNextPage'];
            $endCursor = $decodedBody['data']['webhookSubscriptions']['pageInfo']['endCursor'];
            if (empty(($decodedBody['data']['webhookSubscriptions']['edges'] ?? []))) {
                return [];
            }
            // reduce the edges to an array of handlers with structure [id => handler]
            $handlers = array_reduce($decodedBody['data']['webhookSubscriptions']['edges'], function ($carry, $edge) {
                $node = $edge['node'];
                $carry[$node['topic']] = $this->buildHandlerFromNode($node);
                return $carry;
            }, []);
            if ($hasNextPage) {
                $handlers = array_merge($handlers, $this->getExistingWebhookHandlers($merchant, $endCursor));
            }
            return $handlers;
        } catch (ShopifyException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->getLogger()?->debug(__("Failed to get existing webhook handlers for shop %1: %2", $merchant->getShop(), $e->getMessage())->render());
            throw new ShopifyException(__("Failed to get existing webhook handlers for shop %1", $merchant->getShop())->render());
        }
    }

    private function buildHandlerFromNode(array $node): array
    {
        $endpoint = $node['endpoint'];
        $handler = [];
        switch ($endpoint['__typename']) {
            case 'WebhookHttpEndpoint':
                $handler = [
                    'delivery_method' => Registry::DELIVERY_METHOD_HTTP,
                    'callback_url' => $endpoint['callbackUrl'],
                    // This is a dummy for now because we don't really care about it
                    'callback' => function () {
                    },
                ];
                break;
            case 'WebhookEventBridgeEndpoint':
                $handler = [
                    'delivery_method' => Registry::DELIVERY_METHOD_EVENT_BRIDGE,
                    'arn' => $endpoint['arn'],
                ];
                break;
            case 'WebhookPubSubEndpoint':
                $handler = [
                    'delivery_method' => Registry::DELIVERY_METHOD_PUB_SUB,
                    'pub_sub_project' => $endpoint['pubSubProject'],
                    'pub_sub_topic' => $endpoint['pubSubTopic'],
                ];
                break;
        }

        // set common fields
        $handler['id'] = $node['id'];
        $handler['include_fields'] = $node['includeFields'];
        $handler['metafield_namespaces'] = $node['metafieldNamespaces'];

        // Sort the array fields to make them cheaper to compare later on
        sort($handler['include_fields']);
        sort($handler['metafield_namespaces']);
        return $handler;
    }

    /**
     * Process the webhook request
     *
     * This method is used to process the incoming webhook request. It first retrieves the topic from the request header.
     * Then it tries to process the request using the Registry::process method. If the processing is successful, it sets the response code to 200 and a success message.
     * If the processing fails, it sets the response code to 500 and a failure message.
     * If an InvalidWebhookException is caught, it sets the response code to 401 and an error message indicating an invalid webhook request.
     * If any other exception is caught, it sets the response code to 500 and an error message indicating an exception occurred while handling the webhook.
     * In all cases, it logs the error message if a logger is available.
     * Finally, it returns an array containing the response code and the error message.
     *
     * @return array An array containing the response code and the error message
     */
    public function process(): array
    {
        $topic = $this->request->getHeader(HttpHeaders::X_SHOPIFY_TOPIC, '');
        try {
            // required load app before processing webhook
            $app = $this->appOrException();
            $this->contextInitializer->initialize($app);
            $response = Registry::process($this->request->getHeaders()->toArray(), $this->request->getContent());
            if (!$response->isSuccess()) {
                $this->getLogger()?->debug(__("Failed to process '$topic' webhook: %1", $response->getErrorMessage())->render());
                $code = 500;
                $errmsg = __("Failed to process '$topic' webhook");
            } else {
                $code = 200;
                $errmsg = __("Processed '$topic' webhook successfully");
            }
        } catch (InvalidWebhookException $e) {
            $this->getLogger()?->debug(__("Got invalid webhook request for topic '$topic': %2", $e->getMessage())->render());
            $code = 401;
            $errmsg = __("Got invalid webhook request for topic '$topic'");
        } catch (\Throwable $e) {
            $this->getLogger()?->debug(__("Got an exception when handling '$topic' webhook: %1", $e->getMessage())->render());
            $code = 500;
            $errmsg = __("Got an exception when handling '$topic' webhook");
        }
        return [$code, $errmsg];
    }

    /**
     * Get the current app, base on request params
     *
     * @throws \Exception nếu không tìm thấy ứng dụng
     * @return IApp ứng dụng tìm thấy
     */
    protected function appOrException(): IApp
    {
        return $this->getCurrentApp->get();
    }

    /**
     * Logger hehe
     *
     * @return \Zend_Log|null
     */
    private function getLogger(?string $fileName = 'webhook_process.log'): ?\Zend_Log
    {
        try {
            $writer = new \Zend_Log_Writer_Stream(BP . "/var/log/$fileName");
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            return $logger;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Builds a GraphQL query to get all webhook handlers for the shop.
     *
     * @param ?string $endcursor
     * @return string
     */
    private function buildGetHandlersQuery(string $endcursor = null): string
    {
        $endcursor = $endcursor ? ", after: \"$endcursor\"" : '';
        return <<<QUERY
        query shopifyApiReadWebhookSubscriptions {
          webhookSubscriptions(first: 250$endcursor) {
            edges {
              node {
                id
                topic
                includeFields
                metafieldNamespaces
                endpoint {
                  __typename
                  ... on WebhookHttpEndpoint {
                    callbackUrl
                  }
                  ... on WebhookEventBridgeEndpoint {
                    arn
                  }
                  ... on WebhookPubSubEndpoint {
                    pubSubProject
                    pubSubTopic
                  }
                }
              }
            }
            pageInfo {
              endCursor
              hasNextPage
            }
          }
        }
        QUERY;
    }

    /**
     * Get mutation name depending on the webhook id.
     *
     * @param string|null $webhookId
     * @param string|null $operationName
     * @return string
     */
    private function getMutationName(?string $webhookId, ?string $operationName = null): string
    {
        if ($operationName === 'delete') {
            return 'webhookSubscriptionDelete';
        }
        return $webhookId ? 'webhookSubscriptionUpdate' : 'webhookSubscriptionCreate';
    }

    /**
     * Build a GraphQL query to register or update a webhook.
     *
     * @param array $topic - structure: ['topic' => IWebhookTopic, 'id' => string|null]
     * @return string
     * @throws \Exception
     */
    private function buildWebhookMutation(array $topic): string
    {
        $app = $this->appOrException();
        $operationName = $topic['operation'] ?? null;
        $mutationName = $this->getMutationName($topic['id'] ?? null, $operationName);
        $identifier = isset($topic['id']) ? "id: \"{$topic['id']}\"" : "topic: {$topic['topic']->topicForStorage()}";
        $mutationParams = '';
        if ($operationName !== 'delete') {
            $method = new HttpDelivery();
            $params = [
                'callbackUrl' => "\"{$method->getCallbackAddress(static::WEBHOOK_PATH . "/_rid/{$app->getRemoteId()}")}\"",
            ];
            /** @var IWebhookTopic $handler */
            $handler = $topic['topic'];
            if (!empty($handler->getIncludeFields())) {
                $params['includeFields'] = json_encode($handler->getIncludeFields());
            }
            if (!empty($handler->getMetafieldNamespaces())) {
                $params['metafieldNamespaces'] = json_encode($handler->getMetafieldNamespaces());
            }
            $paramsString = implode(', ', array_map(function ($key, $value) {
                return "$key: $value";
            }, array_keys($params), array_values($params)));
            $mutationParams = "webhookSubscription: {{$paramsString}}";
        }
        return <<<MUTATION
        mutation ShopifyApiCreateWebhookSubscription {
            $mutationName(
                $identifier,
                $mutationParams
            ) {
                userErrors { field message }
            }
        }
        MUTATION;
    }
}
