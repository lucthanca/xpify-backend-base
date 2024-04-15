<?php
declare(strict_types=1);

namespace Xpify\PricingPlanGraphQl\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\Core\Helper\Utils;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface;
use Xpify\PricingPlanGraphQl\Model\PricingPlanFormatter;

class PricingPlanQuery implements ResolverInterface
{
    private SearchCriteriaBuilder $criteriaBuilder;
    private PricingPlanRepositoryInterface $pricingPlanRepository;

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param PricingPlanRepositoryInterface $pricingPlanRepository
     */
    public function __construct(
        SearchCriteriaBuilder $criteriaBuilder,
        PricingPlanRepositoryInterface $pricingPlanRepository
    ) {

        $this->criteriaBuilder = $criteriaBuilder;
        $this->pricingPlanRepository = $pricingPlanRepository;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $appId = $context->getExtensionAttributes()->getApp()?->getId();
        $uid = $args['id'];
        if ($appId === null || !$uid) {
            return null;
        }
        try {
            $id = Utils::uidToId($uid);
            if (!$id) {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
        $this->criteriaBuilder->addFilter(IPricingPlan::APP_ID, $appId);
        $this->criteriaBuilder->addFilter(IPricingPlan::ID, $id);
        $searchResult = $this->pricingPlanRepository->getList($this->criteriaBuilder->create());
        if ($searchResult->getTotalCount() === 0) {
            return null;
        }
        $plan = current($searchResult->getItems());
        return PricingPlanFormatter::toGraphQlOutput($plan);
    }
}
