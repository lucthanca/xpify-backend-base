<?php
declare(strict_types=1);

namespace Xpify\Core\GraphQl\Query;

use Xpify\Core\Model\Logger;

/**
 * This class was born to handle the error when the access token is expired.
 * Default magento core graphql just gap the error -> push it to the error array in the response.
 * But we need to throw the exception when the access token is expired. to handle redirection in frontend.
 * This will present in the rewritten GraphQl controller.
 * @see \Xpify\Core\GraphQl\Controller\GraphQl
 */
class ErrorHandler extends \Magento\Framework\GraphQl\Query\ErrorHandler
{
    /**
     * @inheritDoc
     * @throws \Xpify\AuthGraphQl\Exception\GraphQlShopifyReauthorizeRequiredException
     */
    public function handle(array $errors, callable $formatter): array
    {
        /** @var \GraphQL\Error\Error $error */
        foreach ($errors as $error) {
            if ($error?->getPrevious() instanceof \Xpify\AuthGraphQl\Exception\GraphQlShopifyReauthorizeRequiredException) {
                throw $error?->getPrevious();
            }

            if ($error->getPrevious()) {
                $request = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\RequestInterface::class);
                $diedumpHeader = $request->getHeader('X-DIEDUMP');
                if ($diedumpHeader) {
                    dd($error->getPrevious());
                }
                // try log first 10 trace
                $trace = $error->getPrevious()->getTrace();
                $traces = array_slice($trace, 0, 10);
                $message = "";
                foreach ($traces as $index =>  $trace) {
                    $message .= "[#$index] " . $trace['file'] . ":" . $trace['line'] . " - " . $trace['function'] . "\n";
                }
                // log the error
                Logger::getLogger('graphql.log')->debug(__("%1 - Trace: %2", $error->getPrevious()->getMessage(), $message)->render());
            }
        }
        return parent::handle($errors, $formatter);
    }
}
