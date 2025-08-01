<?php

namespace Xpify\AuthGraphQl\Model\Context;

use Magento\Framework\App\RequestInterface as IRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\GraphQl\Model\Query\ContextParametersInterface;
use Magento\GraphQl\Model\Query\ContextParametersProcessorInterface;
use Xpify\App\Service\GetCurrentApp;
use Xpify\AuthGraphQl\Exception\GraphQlShopifyReauthorizeRequiredException;
use Xpify\AuthGraphQl\Model\EnsureMerchantSession;

/**
 * Class MerchantToContext
 *
 * @since 1.0.1
 */
class MerchantToContext implements ContextParametersProcessorInterface
{
    private IRequest $request;
    private GetCurrentApp $getCurrentApp;
    private EnsureMerchantSession $ensureMerchantSession;

    /**
     * @param IRequest $request
     * @param GetCurrentApp $getCurrentApp
     * @param EnsureMerchantSession $ensureMerchantSession
     */
    public function __construct(
        IRequest $request,
        GetCurrentApp $getCurrentApp,
        EnsureMerchantSession $ensureMerchantSession
    ) {

        $this->request = $request;
        $this->getCurrentApp = $getCurrentApp;
        $this->ensureMerchantSession = $ensureMerchantSession;
    }

    /**
     * @throws GraphQlAuthorizationException
     * @throws GraphQlShopifyReauthorizeRequiredException
     */
    public function execute(ContextParametersInterface $contextParameters): ContextParametersInterface
    {
        try {
            $xpifyAuthRequiredHeader = $this->request->getHeader('x-auth-required', '1');
            // Ignore if the header is set to '0'
            if ($xpifyAuthRequiredHeader === '0') {
                return $contextParameters;
            }
            $this->ensureMerchantSession->execute();
            $merchant = $this->ensureMerchantSession->getMerchant();
            if (!$merchant) {
                return $contextParameters;
            }
            $contextParameters->addExtensionAttribute('merchant', $merchant);
        } catch (GraphQlShopifyReauthorizeRequiredException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Do nothing, just ignore authenticate merchant
        }

        return $contextParameters;
    }
}
