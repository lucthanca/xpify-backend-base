<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl\Controller;

use Magento\Framework\App\Area;
use Magento\Framework\App\AreaList;
use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\GraphQl\Exception\ExceptionFormatter;
use Magento\Framework\GraphQl\Query\Fields as QueryFields;
use Magento\Framework\GraphQl\Query\QueryProcessor;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Schema\SchemaGeneratorInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Response;
use Magento\GraphQl\Controller\HttpRequestProcessor;
use Magento\GraphQl\Helper\Query\Logger\LogData;
use Magento\GraphQl\Model\Query\ContextFactoryInterface;
use Magento\GraphQl\Model\Query\Logger\LoggerPool;
use Xpify\AuthGraphQl\Exception\GraphQlShopifyReauthorizeRequiredException;
use Xpify\Core\Model\ConfigProvider;

class GraphQl implements FrontControllerInterface
{
    /**
     * @var \Magento\Framework\Webapi\Response
     * @deprecated 100.3.2
     */
    private $response;

    /**
     * @var SchemaGeneratorInterface
     */
    private $schemaGenerator;

    /**
     * @var SerializerInterface
     */
    private $jsonSerializer;

    /**
     * @var QueryProcessor
     */
    private $queryProcessor;

    /**
     * @var ExceptionFormatter
     */
    private $graphQlError;

    /**
     * @var ContextInterface
     * @deprecated 100.3.3 $contextFactory is used for creating Context object
     */
    private $resolverContext;

    /**
     * @var HttpRequestProcessor
     */
    private $requestProcessor;

    /**
     * @var QueryFields
     */
    private $queryFields;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var HttpResponse
     */
    private $httpResponse;

    /**
     * @var ContextFactoryInterface
     */
    private $contextFactory;

    /**
     * @var LogData
     */
    private $logDataHelper;

    /**
     * @var LoggerPool
     */
    private $loggerPool;

    /**
     * @var AreaList
     */
    private $areaList;
    private \Xpify\Core\Model\ConfigProvider $configProvider;

    /**
     * @param Response $response
     * @param SchemaGeneratorInterface $schemaGenerator
     * @param SerializerInterface $jsonSerializer
     * @param QueryProcessor $queryProcessor
     * @param ExceptionFormatter $graphQlError
     * @param ContextInterface $resolverContext Deprecated. $contextFactory is used for creating Context object.
     * @param HttpRequestProcessor $requestProcessor
     * @param QueryFields $queryFields
     * @param ConfigProvider $configProvider
     * @param JsonFactory|null $jsonFactory
     * @param HttpResponse|null $httpResponse
     * @param ContextFactoryInterface|null $contextFactory
     * @param LogData|null $logDataHelper
     * @param LoggerPool|null $loggerPool
     * @param AreaList|null $areaList
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Response $response,
        SchemaGeneratorInterface $schemaGenerator,
        SerializerInterface $jsonSerializer,
        QueryProcessor $queryProcessor,
        ExceptionFormatter $graphQlError,
        ContextInterface $resolverContext,
        HttpRequestProcessor $requestProcessor,
        QueryFields $queryFields,
        \Xpify\Core\Model\ConfigProvider $configProvider,
        JsonFactory $jsonFactory = null,
        HttpResponse $httpResponse = null,
        ContextFactoryInterface $contextFactory = null,
        LogData $logDataHelper = null,
        LoggerPool $loggerPool = null,
        AreaList $areaList = null
    ) {
        $this->response = $response;
        $this->schemaGenerator = $schemaGenerator;
        $this->jsonSerializer = $jsonSerializer;
        $this->queryProcessor = $queryProcessor;
        $this->graphQlError = $graphQlError;
        $this->resolverContext = $resolverContext;
        $this->requestProcessor = $requestProcessor;
        $this->queryFields = $queryFields;
        $this->jsonFactory = $jsonFactory ?: ObjectManager::getInstance()->get(JsonFactory::class);
        $this->httpResponse = $httpResponse ?: ObjectManager::getInstance()->get(HttpResponse::class);
        $this->contextFactory = $contextFactory ?: ObjectManager::getInstance()->get(ContextFactoryInterface::class);
        $this->logDataHelper = $logDataHelper ?: ObjectManager::getInstance()->get(LogData::class);
        $this->loggerPool = $loggerPool ?: ObjectManager::getInstance()->get(LoggerPool::class);
        $this->areaList = $areaList ?: ObjectManager::getInstance()->get(AreaList::class);
        $this->configProvider = $configProvider;
    }

    /**
     * Handle GraphQL request
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @since 100.3.0
     */
    public function dispatch(RequestInterface $request): ResponseInterface
    {
        $isRequireAuth = !($request->getHeader('x-auth-required') === '0');
        if ($this->configProvider->isWhitelistEnabled() && $isRequireAuth) {
            $remote = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\HTTP\PhpEnvironment\RemoteAddress::class);
            if ($reqAddr = $remote->getRemoteAddress()) {
                $ipList = [$reqAddr];
                $ipList = array_filter(
                    $ipList,
                    function (string $ip) {
                        return filter_var(trim($ip), FILTER_VALIDATE_IP);
                    }
                );
                // check the ipList must include on of ip in whitelist IP
                $whitelistIps = $this->configProvider->getWhitelistIps();
                $isWhitelisted = false;
                foreach ($ipList as $ip) {
                    if (in_array($ip, $whitelistIps, true)) {
                        $isWhitelisted = true;
                        break;
                    }
                }
                if (empty($ipList) || !$isWhitelisted) {
                    $rawResult = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Controller\Result\Raw::class);
                    $rawResult->setHttpResponseCode(403);
                    $rawResult->setContents('Nothing here!');
                    $rawResult->renderResult($this->httpResponse);
                    return $this->httpResponse;
                }
            }
        }


        $this->areaList->getArea(Area::AREA_GRAPHQL)->load(Area::PART_TRANSLATE);

        $statusCode = 200;
        $jsonResult = $this->jsonFactory->create();
        $data = $this->getDataFromRequest($request);
        $result = [];

        $schema = null;
        try {
            /** @var Http $request */
            $this->requestProcessor->validateRequest($request);

            $query = $data['query'] ?? '';
            $variables = $data['variables'] ?? null;

            $context = $this->contextFactory->create();
            \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Registry::class)->register('g_context', $context);
            // We must extract queried field names to avoid instantiation of unnecessary fields in webonyx schema
            // Temporal coupling is required for performance optimization
            $this->queryFields->setQuery($query, $variables);
            $schema = $this->schemaGenerator->generate();

            $result = $this->queryProcessor->process(
                $schema,
                $query,
                $context,
                $data['variables'] ?? []
            );
        } catch (GraphQlShopifyReauthorizeRequiredException $e) {
            $statusCode = $e->getStatusCode() ?: 401;
            $jsonResult->setHeader(GraphQlShopifyReauthorizeRequiredException::EXCEPTION_HEADER, $e->getReauthorizeUrl());
            $jsonResult->setHeader('X-Shopify-API-Request-Failure-Reauthorize', '1');
        } catch (\Exception $error) {
            $result['errors'] = $result['errors'] ?? [];
            $result['errors'][] = $this->graphQlError->create($error);
            $statusCode = ExceptionFormatter::HTTP_GRAPH_QL_SCHEMA_ERROR_STATUS;
        }

        $jsonResult->setHttpResponseCode($statusCode);
        $jsonResult->setData($result);
        $eventManager = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Event\ManagerInterface::class);
        $eventManager->dispatch('xpify_graphql_before_render_response', ['response_result' => $jsonResult, 'result' => $result]);
        $jsonResult->renderResult($this->httpResponse);

        // log information about the query, unless it is an introspection query
        if (!isset($data['query']) || strpos($data['query'], 'IntrospectionQuery') === false) {
            $queryInformation = $this->logDataHelper->getLogData($request, $data, $schema, $this->httpResponse);
            $this->loggerPool->execute($queryInformation);
        }

        return $this->httpResponse;
    }

    /**
     * Get data from request body or query string
     *
     * @param RequestInterface $request
     * @return array
     */
    private function getDataFromRequest(RequestInterface $request): array
    {
        /** @var Http $request */
        if ($request->isPost()) {
            try {
                $data = $this->jsonSerializer->unserialize($request->getContent());
            } catch (\Exception $e) {
                return [];
            }
        } elseif ($request->isGet()) {
            $data = $request->getParams();
            $data['variables'] = isset($data['variables']) ?
                $this->jsonSerializer->unserialize($data['variables']) : null;
            $data['variables'] = is_array($data['variables']) ?
                $data['variables'] : null;
        } else {
            return [];
        }

        return $data;
    }
}
