<?php
declare(strict_types=1);

namespace Xpify\Core\DisableSearchEngine\SearchAdapter;

use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\AggregationFactory;
use Magento\Framework\Search\Response\BucketFactory;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Framework\Search\Response\QueryResponseFactory as ResponseFactory;

class Adapter implements AdapterInterface
{

    /**
     * Empty response
     *
     * @var array
     */
    private static $emptyRawResponse = [
        "hits" =>
            [
                "hits" => []
            ],
        "aggregations" =>
            [
                "price_bucket" => [],
                "category_bucket" =>
                    [
                        "buckets" => []
                    ]
            ]
    ];

    /**
     * @var ResponseFactory
     */
    private $responseFactory;

    /**
     * @var AggregationFactory
     */
    private $aggregationFactory;

    /**
     * @var BucketFactory
     */
    private BucketFactory $bucketFactory;

    /**
     * Adapter constructor.
     * @param ResponseFactory $responseFactory
     * @param AggregationFactory $aggregationFactory
     * @param BucketFactory $bucketFactory
     */
    public function __construct(
        ResponseFactory    $responseFactory,
        AggregationFactory $aggregationFactory,
        BucketFactory      $bucketFactory
    )
    {
        $this->responseFactory = $responseFactory;
        $this->aggregationFactory = $aggregationFactory;
        $this->bucketFactory = $bucketFactory;
    }

    /**
     * Empty Query Response
     *
     * @param RequestInterface $request
     * @return QueryResponse
     */
    public function query(RequestInterface $request): QueryResponse
    {
        $values = $buckets = [];
        foreach ($request->getAggregation() as $aggregation) {
            $name = $aggregation->getName();
            $buckets[$name] = $this->bucketFactory->create([
                'name' => $name,
                'values' => $values
            ]);
        }

        return $this->responseFactory->create(
            [
                'documents' => [],
                'aggregations' => $this->aggregationFactory->create(['buckets' => $buckets]),
                'total' => self::$emptyRawResponse['hits']['total']['value'] ?? 0
            ]
        );
    }
}
