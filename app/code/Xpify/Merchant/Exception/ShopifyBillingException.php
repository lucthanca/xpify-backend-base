<?php
declare(strict_types=1);

namespace Xpify\Merchant\Exception;

use Exception;

class ShopifyBillingException extends Exception
{
    public $errorData;

    public function __construct(string $message, $errorData = null)
    {
        parent::__construct($message);

        $this->errorData = $errorData;
    }
}
