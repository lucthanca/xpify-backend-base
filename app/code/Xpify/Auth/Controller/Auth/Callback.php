<?php
declare(strict_types=1);

namespace Xpify\Auth\Controller\Auth;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface as IRequest;
use Magento\Framework\Controller\Result\RedirectFactory;
use Shopify\Auth\OAuth;
use Shopify\Exception\CookieNotFoundException;
use Shopify\Exception\OAuthSessionNotFoundException;
use Shopify\Utils;
use Xpify\App\Service\GetCurrentApp;
use Xpify\Core\Helper\ShopifyContextInitializer;
use Xpify\Merchant\Service\Billing;
use Xpify\Webhook\Service\Webhook;
use Magento\Framework\Event\ManagerInterface as IEventManager;

class Callback implements HttpGetActionInterface
{
    private IRequest $request;
    private Webhook $webhookManager;
    private RedirectFactory $resultRedirectFactory;
    private GetCurrentApp $getCurrentApp;
    private ShopifyContextInitializer $contextInitializer;
    private Billing $billing;
    private IEventManager $eventManager;

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
        Billing $billing,
        IEventManager $eventManager
    ) {
        $this->request = $request;
        $this->webhookManager = $webhookManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->getCurrentApp = $getCurrentApp;
        $this->contextInitializer = $contextInitializer;
        $this->billing = $billing;
        $this->eventManager = $eventManager;
    }
    /**
     * @inheritDoc
     */
    public function execute()
    {
        $app = $this->getCurrentApp->get();
        $this->contextInitializer->initialize($app);
        try {
            $session = OAuth::callback(
                $_COOKIE,
                $this->getRequest()->getQuery()->toArray(),
                ['Xpify\Auth\Service\CookieHandler', 'saveShopifyCookie'],
            );
        } catch (CookieNotFoundException|OAuthSessionNotFoundException|\Exception $e) {
            // create raw result
            $rawResult = \Magento\Framework\App\ObjectManager::getInstance()->create(\Magento\Framework\Controller\Result\Raw::class);
            $httpResponse = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\Response\Http::class);
            $rawResult->setHttpResponseCode(403);
            $message = "An error occurred while processing your request. Please try again.";
            $title = "Error";
            if ($e instanceof OAuthSessionNotFoundException || $e instanceof CookieNotFoundException) {
                $message = $e->getMessage();
                if ($e instanceof CookieNotFoundException) {
                    $message = 'You may have taken more than 60 seconds to complete the OAuth process and the session cannot be found';
                }
                $message .= " (or maybe cookies are disabled in your browser). Please try again.";
                $title = "Session Timeout";
            }
            // create html template variable
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
    <title>$title</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: "Nunito", sans-serif;
            font-optical-sizing: auto;
            font-weight: 400;
            font-style: normal;
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
        }
        .message-container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            width: 80%; /* Set width to occupy 80% of the viewport */
        }
        .message-container h1 {
            margin: 0 0 20px;
            font-size: 28px;
            color: #333;
        }
        .message-container p {
            margin: 0;
            font-size: 20px;
            color: #666;
            line-height: 1.6; /* Improved line spacing for readability */
        }
        .logo {
            max-width: 200px; /* Limit maximum width of the logo */
            height: auto;
            margin-bottom: 30px;
        }
        @media (max-width: 600px) {
            /* Responsive adjustments for smaller screens */
            .message-container {
                padding: 20px;
            }
            .message-container h1 {
                font-size: 24px;
            }
            .message-container p {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="message-container">
        <img src="https://api.omnithemes.com/media/whatsapp/omnithemes-1.png" alt="Omnithemes Logo" class="logo">
        <h1>$title</h1>
        <p>$message</p>
        <p><strong>If the problem persists, please <a href="https://omnithemes.com/contact/" target="_blank">contact</a> support.</strong></p>
        <p style="margin-top: 0.5rem"><i>Thanks for your patience!</i></p>
    </div>
</body>
</html>
HTML;

            $rawResult->setContents($html);
            $rawResult->renderResult($httpResponse);
            return $httpResponse;
        }
        $host = $this->getRequest()->getParam('host');
        $this->webhookManager->register($session->getId());
        $redirectUrl = Utils::getEmbeddedAppUrl($host);
        list($shouldPayment, $payUrl) = $this->billing->check($session);
        if ($shouldPayment) {
            $redirectUrl = $payUrl;
        }

        $this->eventManager->dispatch('app_installed_successfully', [
            'app' => $app,
            'shop' => $session->getShop(),
        ]);
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
