<?php
declare(strict_types=1);

namespace Xpify\PricingPlanGraphQl\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\Core\Model\Constants;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface;
use Xpify\PricingPlanGraphQl\Model\PricingPlanFormatter;

class AppPricingPlanQuery extends AuthSessionAbstractResolver implements \Magento\Framework\GraphQl\Query\ResolverInterface
{
    private PricingPlanRepositoryInterface $pricingPlanRepository;

    private SearchCriteriaBuilder $criteriaBuilder;

    private \Magento\Framework\Api\SortOrderBuilder $sorderBuilder;

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param PricingPlanRepositoryInterface $pricingPlanRepository
     * @param SortOrderBuilder $orderBuilder
     */
    public function __construct(
        SearchCriteriaBuilder $criteriaBuilder,
        PricingPlanRepositoryInterface $pricingPlanRepository,
        \Magento\Framework\Api\SortOrderBuilder $sorderBuilder
    ) {
        $this->pricingPlanRepository = $pricingPlanRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->sorderBuilder = $sorderBuilder;
    }

    /**
     * @inheritDoc
     */
    public function execResolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        try {
            $id = $context->getExtensionAttributes()->getApp()?->getId();
            if ($id === null) {
                throw new GraphQlInputException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
            }
            $this->criteriaBuilder->addFilter(PricingPlanInterface::APP_ID, $id);
            $this->sorderBuilder->setField(PricingPlanInterface::SORT_ORDER);
            $this->sorderBuilder->setAscendingDirection();
            $this->criteriaBuilder->addSortOrder($this->sorderBuilder->create());
            $searchResult = $this->pricingPlanRepository->getList($this->criteriaBuilder->create());
            $items = [];
            foreach ($searchResult->getItems() as $item) {
                $items[] = PricingPlanFormatter::toGraphQlOutput($item);
            }
            return $items;
        } catch (\Exception $e) {
            return [];
        }
    }
}
