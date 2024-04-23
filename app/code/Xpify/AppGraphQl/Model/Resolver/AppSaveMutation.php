<?php
declare(strict_types=1);

namespace Xpify\AppGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Psr\Log\LoggerInterface;
use Xpify\App\Api\AppRepositoryInterface;
use Xpify\AppGraphQl\Model\AppResultFormatter;
use Xpify\AppGraphQl\Model\AuthorizationTrait;
use Xpify\Core\Exception\GraphQlException;

class AppSaveMutation implements ResolverInterface
{
    private AppRepositoryInterface $appRepository;

    private Uid $uidEncoder;

    private AppResultFormatter $appResultFormatter;
    private \Psr\Log\LoggerInterface $logger;

    /**
     * @param AppRepositoryInterface $appRepository
     * @param Uid $uidEncoder
     * @param AppResultFormatter $appResultFormatter
     * @param LoggerInterface $logger
     */
    public function __construct(
        AppRepositoryInterface $appRepository,
        Uid $uidEncoder,
        AppResultFormatter $appResultFormatter,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->appRepository = $appRepository;
        $this->uidEncoder = $uidEncoder;
        $this->appResultFormatter = $appResultFormatter;
        $this->logger = $logger;
    }

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        try {
            $this->validateArgs($args);
            if (!empty($args['input']['id'])) {
                $app = $this->appRepository->get($this->uidEncoder->decode((string) $args['input']['id']));
                if (!$app->getToken()) {
                    $app->setDataChanges(true);
                }
            } else {
                $app = $this->appRepository->newInstance();
            }
            $filteredChanges = array_filter($args['input'], function ($key) {
                return $key !== 'id';
            }, ARRAY_FILTER_USE_KEY);
            if (!empty($filteredChanges)) {
                foreach ($filteredChanges as $key => $value) {
                    $app->setData($key, $value);
                }

                $this->appRepository->save($app);
            }

            return array_merge(['model' => $app], $this->appResultFormatter->toGraphQlOutput($app));
        } catch (GraphQlInputException $e) {
            throw $e;
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__("App not found!"));
        } catch (\Magento\Framework\Exception\CouldNotSaveException $e) {
            if ($e?->getPrevious() instanceof \Magento\Framework\Exception\AlreadyExistsException) {
                $preOfPre = $e->getPrevious()->getPrevious();
                if ($preOfPre instanceof \Magento\Framework\DB\Adapter\DuplicateException) {
                    if (str_contains($preOfPre->getMessage(), '$XPIFY_APPS_HANDLE')) {
                        throw new GraphQlException(__("App handle already exists!"), $preOfPre, 'x-duplicate-handle');
                    }
                    if (str_contains($preOfPre->getMessage(), '$XPIFY_APPS_REMOTE_ID')) {
                        throw new GraphQlException(__("App remote ID already exists!"), $preOfPre, 'x-duplicate-remoteId');
                    }
                }
            }
            throw new GraphQlNoSuchEntityException(__($e->getMessage()));
        } catch (\Throwable $e) {
            $this->logger->debug($e);
            throw new GraphQlInputException(__("Can not complete the mutation. Please check log!"));
        }
    }

    /**
     * Validate input arguments
     *
     * @param array $args
     * @return void
     * @throws GraphQlInputException
     */
    protected function validateArgs(array $args): void
    {
        if (empty($args['input'])) {
            throw new GraphQlInputException(__("Input argument is required."));
        }
        if (empty($args['input']['id']) && empty($args['input']['name'])) {
            throw new GraphQlInputException(__("Name is required."));
        }
        // validate name length must less or equal 30
        if (isset($args['input']['name']) && strlen($args['input']['name']) > 30) {
            throw new GraphQlInputException(__("Name must less or equal 30 characters."));
        }
    }
}
