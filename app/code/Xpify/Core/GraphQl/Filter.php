<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl;

use Magento\Framework\App\RequestInterface;
use Xpify\Core\Helper\Utils;

class Filter
{
    private $runtimeCached = [];

    /**
     * @var ApiFilterInterface[]
     */
    private array $filterPool;
    private RequestInterface $request;

    /**
     * @param RequestInterface $request
     * @param array $filterPool
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        array $filterPool = []
    ) {
        $this->filterPool = $filterPool;
        $this->request = $request;
    }

    public function isAllowed(string $id)
    {
        if (!isset($this->runtimeCached[$id])) {
            if (!empty($this->filterPool[$id])) {
                $requestAppId = $this->request->getHeader('x-xpify-app');
                if (!$requestAppId) throw new \Exception('App ID not found in request header');
                $appId = Utils::uidToId($requestAppId);
                $this->runtimeCached[$id] = $this->filterPool[$id]->isValid($appId);

            } else {
                $this->runtimeCached[$id] = false;
            }
        }

        return $this->runtimeCached[$id];
    }
}
