<?php
declare(strict_types=1);

namespace Xpify\AppGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\App\Api\AppRepositoryInterface;
use Xpify\AppGraphQl\Model\AppResultFormatter;
use Xpify\AppGraphQl\Model\AuthorizationTrait;

class AppQuery implements ResolverInterface
{
    private $appRepository;

    private $appResultFormatter;

    /**
     * @param AppRepositoryInterface $appRepository
     * @param AppResultFormatter $appResultFormatter
     */
    public function __construct(
        AppRepositoryInterface $appRepository,
        AppResultFormatter $appResultFormatter
    ) {
        $this->appRepository = $appRepository;
        $this->appResultFormatter = $appResultFormatter;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        try {
            $app = $this->appRepository->get($args['value'], $args['field']);
            if (!$app->getId()) {
                throw new GraphQlNoSuchEntityException(__("App not found!"));
            }
            $output = $this->appResultFormatter->toGraphQlOutput($app);
            $output['model'] = $app;
            return $output;
        } catch (\Exception $e) {
            throw new GraphQlNoSuchEntityException(__("App not found!"));
        }
    }
}
