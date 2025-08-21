<?php
declare(strict_types=1);

namespace Xpify\MerchantGraphQl\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Psr\Log\LoggerInterface;
use Xpify\AuthGraphQl\Exception\GraphQlShopifyReauthorizeRequiredException;
use Xpify\AuthGraphQl\Model\Resolver\AuthSessionAbstractResolver;
use Xpify\Core\Helper\Utils;
use Xpify\Core\Model\Constants;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\MerchantSubscriptionRepositoryInterface as ISubscriptionRepository;
use Xpify\Merchant\Exception\AlreadySubscribedException;
use Xpify\Merchant\Exception\ShopifyBillingException;
use Xpify\Merchant\Helper\Subscription;
use Xpify\Merchant\Service\Billing;
use Xpify\MerchantGraphQl\Model\SubscriptionFormatter;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface as IPricingPlanRepository;
use Xpify\PricingPlan\Model\Source\IntervalType;

class SubscribePricingPlanMutation extends AuthSessionAbstractResolver implements ResolverInterface
{
    private IPricingPlanRepository $pricingPlanRepository;
    private ISubscriptionRepository $subscriptionRepository;
    private \Psr\Log\LoggerInterface $logger;
    private \Xpify\MerchantGraphQl\Model\SubscriptionFormatter $subscriptionFormatter;
    private Billing $billing;

    /**
     * @param IPricingPlanRepository $pricingPlanRepository
     * @param ISubscriptionRepository $subscriptionRepository
     * @param LoggerInterface $logger
     * @param SubscriptionFormatter $subscriptionFormatter
     * @param Billing $billing
     */
    public function __construct(
        IPricingPlanRepository $pricingPlanRepository,
        ISubscriptionRepository $subscriptionRepository,
        \Psr\Log\LoggerInterface $logger,
        \Xpify\MerchantGraphQl\Model\SubscriptionFormatter $subscriptionFormatter,
        Billing $billing
    ) {
        $this->pricingPlanRepository = $pricingPlanRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->logger = $logger;
        $this->subscriptionFormatter = $subscriptionFormatter;
        $this->billing = $billing;
    }

    /**
     * Subscribe merchant to a pricing plan
     *
     * @param Field $field
     * @param $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlInputException|GraphQlNoSuchEntityException
     * @throws GraphQlShopifyReauthorizeRequiredException
     * @throws GraphQlAlreadyExistsException
     * @since 1.0.0
     */
    public function execResolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        $this->validateArgs($args);
        $plan = $this->pricingPlanRepository->get($args['input']['plan_id']);
        $merchant = $context->getExtensionAttributes()->getMerchant();

        try {
            $payUrl = $this->billing->subscribePlan($merchant, $plan, $args['input']['interval']);
            if ($payUrl) {
                throw new GraphQlShopifyReauthorizeRequiredException(__("Please make payment."), null, 0, true, $payUrl);
            }
            $subscription = Subscription::getSubscription($merchant);
        } catch (AlreadySubscribedException $e) {
            if ($e->getCode() === Billing::E_ALREADY_SUBSCRIBED) {
                throw new GraphQlAlreadyExistsException(__("You have already subscribed to this plan."));
            }
            throw new GraphQlAlreadyExistsException(__($e->getMessage()));
        } catch (GraphQlShopifyReauthorizeRequiredException $e) {
            throw $e;
        } catch (ShopifyBillingException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()));
        } catch (\Exception $e) {
            Logger::getLogger('subscription.log')->debug($e->getMessage() . ' ' . $e->getTraceAsString());
            throw new GraphQlNoSuchEntityException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
        }
        if (!$subscription?->getId()) {
            throw new GraphQlNoSuchEntityException(__(Constants::INTERNAL_SYSTEM_ERROR_MESS));
        }

        return $this->subscriptionFormatter->toGraphQlOutput($subscription);
    }

    /**
     * Validate input arguments
     *
     * @param array $args
     * @throws GraphQlInputException|GraphQlNoSuchEntityException
     */
    protected function validateArgs(array &$args): void
    {
        $merchant = $this->getMerchantSession()->getMerchant();
        if (!$merchant) {
            throw new GraphQlNoSuchEntityException(__('We can not authorize you! Please try reloading the page. If the problem persists, contact us.'));
        }

        if (!isset($args['input'])) {
            throw new GraphQlNoSuchEntityException(__('Invalid input'));
        }
        $input = &$args['input'];
        $planId = $input['plan_id'] ?? null;
        if (!$planId) {
            throw new GraphQlInputException(__('Invalid plan ID'));
        }
        $input['plan_id'] = (int) Utils::uidToId($planId);
        $interval = $input['interval'] ?? null;
        if (!$interval || !IntervalType::isValidInterval($interval)) {
            throw new GraphQlInputException(__('Invalid interval'));
        }

        try {
            $plan = $this->pricingPlanRepository->get($args['input']['plan_id']);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__('Invalid plan ID'));
        }
        $f = $plan->hasIntervalPrice($args['input']['interval']);
        if (!$f) {
            throw new GraphQlNoSuchEntityException(__('Invalid interval'));
        }
    }
}
