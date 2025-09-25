<?php
declare(strict_types=1);

namespace Xpify\PricingPlan\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface as IPricingPlanRepository;

class PricingManager
{
    private IPricingPlanRepository $pricingPlanRepository;
    private SearchCriteriaBuilder $criteriaBuilder;
    private \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder;

    /**
     * @param IPricingPlanRepository $pricingPlanRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        IPricingPlanRepository $pricingPlanRepository,
        SearchCriteriaBuilder $criteriaBuilder,
        \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
    ) {
        $this->pricingPlanRepository = $pricingPlanRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    /**
     * Retrieve list of active pricing plans for the given app
     *
     * @param int $appId
     * @return array
     */
    public function getListActive(int $appId): array
    {
        $this->criteriaBuilder->addFilter(IPricingPlan::APP_ID, $appId);
        $this->criteriaBuilder->addFilter(IPricingPlan::STATUS, IPricingPlan::STATUS_ACTIVE);
        $this->sortOrderBuilder->setField(IPricingPlan::SORT_ORDER);
        $this->sortOrderBuilder->setAscendingDirection();
        $this->criteriaBuilder->addSortOrder($this->sortOrderBuilder->create());

        $searchResult = $this->pricingPlanRepository->getList($this->criteriaBuilder->create());
        return $searchResult->getItems();
    }

    /**
     * Retrieve a free pricing plan for the given app
     *
     * @param int $appId
     * @return IPricingPlan
     * @throws NoSuchEntityException
     */
    public function getFreePricingPlan(int $appId): IPricingPlan
    {
        try {
            $pricingPlans = $this->getListActive($appId);
            // loop through items to find the first free plan, free plan is the one with prices has record with price = 0
            foreach ($pricingPlans as $item) {
                $prices = $item->getDataPrices();
                foreach ($prices as $price) {
                    if (isset($price['amount']) && (float) $price['amount'] > 0.0) {
                        return $item;
                    }
                }
            }
        } catch (\Throwable $exception) {
            \Xpify\Core\Model\Logger::getLogger('pricing.manager.log')->debug(
                $exception->getMessage() . ' | ' . $exception->getTraceAsString()
            );
        }
        throw new NoSuchEntityException(__("No free pricing plan found."));
    }
}
