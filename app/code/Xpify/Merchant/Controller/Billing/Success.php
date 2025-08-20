<?php
declare(strict_types=1);

namespace Xpify\Merchant\Controller\Billing;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface as IAction;
use Magento\Framework\App\RequestInterface as IRequest;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use SectionBuilder\Billing\Exception\PurchaseSectionException;
use Xpify\App\Service\GetCurrentApp;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Core\Helper\Utils;
use Xpify\Core\Model\Logger;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;
use Xpify\Merchant\Api\MerchantSubscriptionRepositoryInterface as ISubscriptionRepository;
use Xpify\Merchant\Helper\GraphqlQueryTrait;
use Xpify\Merchant\Model\Billing\SubscriptionSuccessResolverInterface as IBillingSuccessResolver;
use Xpify\PricingPlan\Api\PricingPlanRepositoryInterface as IPricingPlanRepository;
use Xpify\Merchant\Model\ResourceModel\MerchantSubscription as SubscriptionResource;

/**
 * Class Success
 * @since 1.0.1
 */
class Success implements IAction
{
    use GraphqlQueryTrait;

    private IRequest $request;
    /**
     * @var array
     */
    private array $responseResolvers;
    private GetCurrentApp $currentApp;
    private ShopifyContextInitializer $shopifyContextInitializer;
    private IMerchantRepository $merchantRepository;
    private ISubscriptionRepository $subscriptionRepository;
    private ResultFactory $resultFactory;
    private ManagerInterface $eventManager;
    private IPricingPlanRepository $pricingPlanRepository;
    private SubscriptionResource $subscriptionResource;

    /**
     * @param IRequest $request
     * @param GetCurrentApp $currentApp
     * @param ShopifyContextInitializer $shopifyContextInitializer
     * @param IMerchantRepository $merchantRepository
     * @param ISubscriptionRepository $subscriptionRepository
     * @param ResultFactory $resultFactory
     * @param ManagerInterface $eventManager
     * @param IPricingPlanRepository $pricingPlanRepository
     * @param SubscriptionResource $subscriptionResource
     * @param IBillingSuccessResolver[] $responseResolvers
     */
    public function __construct(
        IRequest                                  $request,
        GetCurrentApp                             $currentApp,
        ShopifyContextInitializer                 $shopifyContextInitializer,
        IMerchantRepository                       $merchantRepository,
        ISubscriptionRepository                   $subscriptionRepository,
        ResultFactory                             $resultFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        IPricingPlanRepository                    $pricingPlanRepository,
        SubscriptionResource                      $subscriptionResource,
        array                                     $responseResolvers = []
    ) {
        $this->request = $request;
        $this->responseResolvers = $responseResolvers;
        $this->currentApp = $currentApp;
        $this->shopifyContextInitializer = $shopifyContextInitializer;
        $this->merchantRepository = $merchantRepository;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->resultFactory = $resultFactory;
        $this->eventManager = $eventManager;
        $this->pricingPlanRepository = $pricingPlanRepository;
        $this->subscriptionResource = $subscriptionResource;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $unknownErrorMsg = \Xpify\Core\Model\Constants::INTERNAL_SYSTEM_ERROR_MESS;
        try {
            try {
                $app = $this->currentApp->get();
            } catch (NoSuchEntityException $e) {
                throw new PurchaseSectionException('Invalid request');
            }
            $chargeId = $this->getRequest()->getParam('charge_id');
            $planUid = $this->getRequest()->getParam('_pid');
            $appUid = $this->getRequest()->getParam('_i');
            if (
                !$appUid ||
                !$chargeId ||
                ($app?->getId() . "") !== Utils::uidToId($appUid) ||
                !$planUid
            ) {
                throw new PurchaseSectionException('Invalid request');
            }
            $interval = $this->getRequest()->getParam('_interval');
            $merchantId = $this->getRequest()->getParam('_mid');
            $sign = $this->getRequest()->getParam('_sign');
            $isValid = Utils::validateHmac([
                'data' => [
                    '_mid' => $merchantId,
                    '_i' => $appUid,
                    '_pid' => $planUid,
                    '_interval' => $interval,
                ],
                'buildQuery' => true,
                'buildQueryWithJoin' => true,
                'hmac' => $sign,
            ], \Xpify\Core\Model\Constants::SYS_SECRET_KEY);
            if (!$isValid) {
                throw new PurchaseSectionException('Invalid signature');
            }
            $this->shopifyContextInitializer->initialize($app);
            $merchant = $this->merchantRepository->getById((int) $merchantId);
            if (!$merchant?->getId()) {
                throw new PurchaseSectionException('Merchant not found');
            }
            $plan = $this->pricingPlanRepository->get(Utils::uidToId($planUid));
            if (!$plan?->getId()) {
                throw new PurchaseSectionException('Pricing plan not found!');
            }
            $sSubscription = self::query($merchant, [
                'query' => self::SUBSCRIPTION_STATUS_QUERY,
                'variables' => ['id' => $this->getShopifyAppSubscriptionId()],
            ]);
            if (empty($sSubscription['data']['node']['status'])) {
                Logger::getLogger("{$app->getName()}.subscription.log")->debug("Can't get subscription status ({$this->getShopifyAppSubscriptionId()}})");
                throw new PurchaseSectionException($unknownErrorMsg);
            }
            $subscriptionStatus = $sSubscription['data']['node']['status'];
            if ($subscriptionStatus !== 'ACCEPTED' && $subscriptionStatus !== 'ACTIVE') {
                throw new PurchaseSectionException('Invalid subscription');
            }

            try {
                $this->subscriptionResource->deactivateAllSubscriptions((int) $merchant->getId(), (int) $app->getId());
                $createdAt = $sSubscription['data']['node']['createdAt'];
                $subscription = $this->subscriptionRepository->create();
                $subscription->setMerchantId((int) $merchant->getId());
                $subscription->setPlanId((int) $plan->getId());
                $subscription->setAppId((int) $merchant->getAppId());
                $subscription->setCode($plan->getCode());
                $subscription->setName($plan->getName());
                $subscription->setDescription($plan->getDescription());
                $subscription->setPrice($plan->getIntervalAmount($interval));
                $subscription->setInterval($interval);
                $subscription->setCreatedAt($createdAt);
                $subscription->setStatus($subscriptionStatus);
                $subscription->setSubscriptionId($chargeId);
                $subscription = $this->subscriptionRepository->save($subscription);
            } catch (\Throwable $e) {
                $details = [
                    'merchant_id' => $merchant->getId(),
                    'app_id' => $app->getId(),
                    'plan_id' => $plan->getId(),
                    'charge_id' => $chargeId,
                    'interval' => $interval,
                    'created_at' => $createdAt ?? null,
                ];
                Logger::getLogger("App.subscription.log")->debug("Lỗi khi save subscription vào DB. {$e->getMessage()}. Trace: {$e->getTraceAsString()}");
                throw new PurchaseSectionException("Our system failed. Please contact us for support.");
            }

            $this->eventManager->dispatch('xpify_merchant_subscription_success', [
                'app' => $app,
                'merchant' => $merchant,
                'subscription' => $subscription,
                'charge_id' => $chargeId,
            ]);

            // sort response resolvers by getSortOrder of each resolver
            usort($this->responseResolvers, function (IBillingSuccessResolver $a, IBillingSuccessResolver $b) {
                return $a->getSortOrder() <=> $b->getSortOrder();
            });

            foreach ($this->responseResolvers as $resolver) {
                if ($resolver->shouldResolve($app, $merchant, $subscription)) {
                    return $resolver->resolve($app, $merchant, $subscription, $this->resultFactory);
                }
            }

            throw new PurchaseSectionException($unknownErrorMsg);
        } catch (PurchaseSectionException $e) {
            echo $e->getMessage();
        } catch (\Throwable $e) {
            Logger::getLogger("App.subscription.log")->debug("{$e->getMessage()}. Trace: {$e->getTraceAsString()}");
            echo $unknownErrorMsg;
        }
        return $this->resultFactory->create(ResultFactory::TYPE_RAW)->setHttpResponseCode(400)->setContents('');
    }

    /**
     * Get shopify app subscription id
     *
     * @return string
     */
    private function getShopifyAppSubscriptionId(): string
    {
        return 'gid://shopify/AppSubscription/' . $this->getRequest()->getParam('charge_id');
    }

    /**
     * Get request
     *
     * @return IRequest
     */
    public function getRequest(): IRequest
    {
        return $this->request;
    }

    private const SUBSCRIPTION_STATUS_QUERY = <<<'GRAPHQL'
        query GetSubscriptionStatus($id: ID!) {
            node(id: $id) {
                ... on AppSubscription { status createdAt }
            }
        }
    GRAPHQL;

}
