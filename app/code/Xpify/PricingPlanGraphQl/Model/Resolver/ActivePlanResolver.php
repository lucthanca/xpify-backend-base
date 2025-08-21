<?php
declare(strict_types=1);

namespace Xpify\PricingPlanGraphQl\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface as IResolver;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\Core\Helper\Utils;
use Xpify\Core\Model\Constants;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;
use Xpify\PricingPlan\Api\Data\PricingPlanInterface as IPricingPlan;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface as IPricingPlanRepository;
use Xpify\Merchant\Api\Data\MerchantSubscriptionInterface as ISubscription;

class ActivePlanResolver extends AuthSessionAbstractResolver implements IResolver
{
    private SearchCriteriaBuilder $criteriaBuilder;
    private IPricingPlanRepository $pricingPlanRepository;

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param IPricingPlanRepository $pricingPlanRepository
     */
    public function __construct(
        SearchCriteriaBuilder $criteriaBuilder,
        IPricingPlanRepository $pricingPlanRepository
    ) {
        $this->criteriaBuilder = $criteriaBuilder;
        $this->pricingPlanRepository = $pricingPlanRepository;
    }

    /**
     * @inheritDoc
     */
    public function execResolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!$context->getExtensionAttributes()) {
            return null;
        }
        /** @var IMerchant|null $merchant */
        $merchant = $context->getExtensionAttributes()->getMerchant();
        if (!$merchant?->getId()) {
            return null;
        }
        $subscription = \Xpify\Merchant\Helper\Subscription::getSubscription($merchant);
        if (!$subscription) {
            // Get default active plan
            $id = $context->getExtensionAttributes()->getApp()?->getId();
            if ($id === null) {
                throw new GraphQlInputException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
            }
            $this->criteriaBuilder->addFilter(IPricingPlan::APP_ID, $id);
            $this->criteriaBuilder->addFilter(IPricingPlan::STATUS, IPricingPlan::STATUS_ACTIVE);
            $this->criteriaBuilder->addFilter(IPricingPlan::DEFAULT, IPricingPlan::IS_DEFAULT);
            $this->criteriaBuilder->setPageSize(1);
            $this->criteriaBuilder->setCurrentPage(1);
            $searchResult = $this->pricingPlanRepository->getList($this->criteriaBuilder->create());
            $items = $searchResult->getItems();
            if (empty($items)) {
                return null;
            }
            $subscription = reset($items);
            $createdAt = null;
            // get interval price from default plan, if it has many intervals, should select every 30 days first > annual > one time
            $intervals = $subscription->getDataPrices();
            // loop through intervals to find the first one
            $interval = null;
            $price = null;
            foreach ($intervals as $intervalPrice) {
                $interval = $intervalPrice['interval'] ?? 'ONE_TIME';
                $price = $intervalPrice['amount'] ?? 0.0;
                if ($interval === 'EVERY_30_DAYS') {
                    break;
                }
                if ($interval === 'ANNUAL') {
                    break;
                }
                if ($interval === 'ONE_TIME') {
                    break;
                }
            }
            $id = $subscription->getId();
            $status = 'ACTIVE';
        } else {
            $id = $subscription->getPlanId();
            $status = $subscription->getStatus();
            $createdAt = $subscription->getCreatedAt();
            $interval = $subscription->getInterval();
            $price = $subscription->getPrice();
        }
        return [
            'id' => Utils::idToUid($id . ""),
            'status' => $status,
            'name' => $subscription->getName(),
            'code' => $subscription->getCode(),
            'interval' => $interval,
            'price' => $price,
            'created_at' => $createdAt,
        ];
    }
}
