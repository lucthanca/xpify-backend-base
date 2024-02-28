<?php
declare(strict_types=1);

namespace Xpify\Auth\Controller\Auth;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface as IRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Shopify\Auth\OAuth;
use Shopify\Utils;
use Shopify\Webhooks\Topics;
use Xpify\App\Service\GetCurrentApp;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Merchant\Service\Billing;
use Xpify\Webhook\Service\Webhook;

class Callback implements HttpGetActionInterface
{
    private IRequest $request;
    private Webhook $webhookManager;
    private RedirectFactory $resultRedirectFactory;
    private GetCurrentApp $getCurrentApp;
    private ShopifyContextInitializer $contextInitializer;
    private Billing $billing;

    /**
     * @param IRequest $request
     * @param Webhook $webhookManager
     * @param RedirectFactory $resultRedirectFactory
     * @param ShopifyContextInitializer $contextInitializer
     * @param GetCurrentApp $getCurrentApp
     * @param Billing $billing
     */
    public function __construct(
        IRequest $request,
        Webhook $webhookManager,
        RedirectFactory $resultRedirectFactory,
        ShopifyContextInitializer $contextInitializer,
        GetCurrentApp $getCurrentApp,
        Billing $billing
    ) {
        $this->request = $request;
        $this->webhookManager = $webhookManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->getCurrentApp = $getCurrentApp;
        $this->contextInitializer = $contextInitializer;
        $this->billing = $billing;
    }
    /**
     * @inheritDoc
     */
    public function execute()
    {
        $app = $this->getCurrentApp->get();
        $this->contextInitializer->initialize($app);
        $session = OAuth::callback(
            $_COOKIE,
            $this->getRequest()->getQuery()->toArray(),
            ['Xpify\Auth\Service\CookieHandler', 'saveShopifyCookie'],
        );
        $host = $this->getRequest()->getParam('host');
        $this->webhookManager->register($session->getId(), $app);
        $redirectUrl = Utils::getEmbeddedAppUrl($host);
        list($shouldPayment, $payUrl) = $this->billing->check($session);
        if ($shouldPayment) {
            $redirectUrl = $payUrl;
        }

        return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
    }

    /**
     * @return IRequest
     */
    public function getRequest(): IRequest
    {
        return $this->request;
    }
}
