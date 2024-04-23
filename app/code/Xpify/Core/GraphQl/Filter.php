<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl;

use Magento\Framework\Exception\LocalizedException;
use Xpify\Core\Model\Logger;

class Filter
{
    private $runtimeCached = [];

    /**
     * @var ApiFilterInterface[]
     */
    private array $filterPool;

    /**
     * @param array $filterPool
     */
    public function __construct(
        array $filterPool = []
    ) {
        $this->filterPool = $filterPool;
    }

    public function isAllowed(string $id)
    {
        if (!isset($this->runtimeCached[$id])) {
            if (!empty($this->filterPool[$id])) {
                $gContext = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Registry::class)->registry('g_context');
                if (!$gContext) {
                    Logger::getLogger('graphql.log')->debug('Missing g_context registry. Please check the ContextFactory should be create before TypeRegistry execute.');
                    throw new LocalizedException(__('Something went wrong. Please try again later.'));
                }
                $authApp = $gContext->getExtensionAttributes()->getAuthApp();
                $this->runtimeCached[$id] = $this->filterPool[$id]->isValid($authApp ?? null);

            } else {
                $this->runtimeCached[$id] = false;
            }
        }

        return $this->runtimeCached[$id];
    }
}
