<?php
declare(strict_types=1);

namespace Xpify\Webhook\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface as IRequest;
use Shopify\Clients\HttpHeaders;
use Shopify\Exception\InvalidWebhookException;
use Shopify\Exception\ShopifyException;
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
    public function register(string $topic, string $merchantDomain, string $accessToken, IApp $app): bool
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

    public function registerV2(IApp $app, string $merchantId)
    {
        $this->contextInitializer->initialize($app);
        $criteriaBuilder = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $criteriaBuilder->addFilter('session_id', $merchantId);
        $criteriaBuilder->addFilter('app_id', $app->getId());
        $criteriaBuilder->setPageSize(1);
        $result = $this->merchantRepository->getList($criteriaBuilder->create());
        if (!$result->getTotalCount()) {
            throw new \Exception('Merchant not found');
        }
        $merchant = current($result->getItems());
        $predefinedHandlers = $this->webhookHandlerRegister->getWebhookRegistry($app->getName());
        $registerReturn = array_reduce($predefinedHandlers, function ($carry, IWebhookTopic $handler) use ($merchant) {
            $carry[$handler->getTopic()] = [];
            return $carry;
        }, []);

        $existingHandlers = $this->getExistingWebhookHandlers($app, $merchant);
        $privacyTopics = [
            'CUSTOMERS_DATA_REQUEST',
            'CUSTOMERS_REDACT',
            'SHOP_REDACT',
        ];
        foreach ($predefinedHandlers as $topic) {
            if (in_array($topic->getTopic(), $privacyTopics)) {
                continue;
            }

            $registerReturn[$topic->getTopic()] = $this->registerTopic($merchant, $topic, $existingHandlers, $existingHandlers[$topic->getTopic()]);
        }
    }

    private function registerTopic()
    {

    }

    /**
     * Get existing webhook handlers
     *
     * This method is used to get all existing webhook handlers for a given shop. It first retrieves the GraphQL client from the merchant object.
     * Then it tries to get the existing webhook handlers using the GraphQL client. If the request is successful, it returns an array containing the existing webhook handlers.
     * If the request fails, it logs an error message and throws a ShopifyException.
     *
     * @param IApp $app The app object
     * @param IMerchant $merchant The merchant object
     * @param string|null $endcursor
     * @return array An array containing the existing webhook handlers
     * @throws ShopifyException
     */
    private function getExistingWebhookHandlers(IApp $app, IMerchant $merchant, ?string $endcursor = null): array
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
                $handlers = array_merge($handlers, $this->getExistingWebhookHandlers($app, $merchant, $endCursor));
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
    private function getLogger(): ?\Zend_Log
    {
        try {
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/webhook_process.log');
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
}
