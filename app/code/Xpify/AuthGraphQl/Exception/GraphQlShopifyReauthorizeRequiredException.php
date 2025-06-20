<?php
declare(strict_types=1);

namespace Xpify\AuthGraphQl\Exception;

//use GraphQL\Error\ClientAware;
//use Magento\Framework\Exception\AggregateExceptionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class GraphQlShopifyReauthorizeRequiredException extends LocalizedException
{
    const EXCEPTION_HEADER = 'X-Shopify-API-Request-Failure-Reauthorize-Url';
    const EXCEPTION_CATEGORY = 'x-shopify-reauthorize-required';

    /**
     * @var boolean
     */
    private $isSafe;

    /**
     * The array of errors that have been added via the addError() method
     *
     * @var \Magento\Framework\Exception\LocalizedException[]
     */
    private $errors = [];

    private ?string $failureReauthorizeUrl = null;

    /**
     * Initialize object
     *
     * @param Phrase $phrase
     * @param \Exception|null $cause
     * @param int $code
     * @param boolean $isSafe
     * @param null $failureReauthorizeUrl
     */
    public function __construct(Phrase $phrase, \Exception $cause = null, $code = 0, $isSafe = true, $failureReauthorizeUrl = null)
    {
        $this->isSafe = $isSafe;
        $this->failureReauthorizeUrl = $failureReauthorizeUrl;
        parent::__construct($phrase, $cause, $code);
    }

    /**
     * Get http status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return 401;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setReauthorizeUrl(string $url): self
    {
        $this->failureReauthorizeUrl = $url;
        return $this;
    }

    /**
     * Get reauthorize url
     *
     * @return string|null
     */
    public function getReauthorizeUrl(): ?string
    {
        return urldecode($this->failureReauthorizeUrl);
    }

    public function getCategory() : string
    {
        return self::EXCEPTION_CATEGORY;
    }

    /**
     * @inheritdoc
     */
    public function isClientSafe() : bool
    {
        return $this->isSafe;
    }

    /**
     * Get child errors if used as aggregate exception
     *
     * @return LocalizedException[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Add child error if used as aggregate exception
     *
     * @param LocalizedException $exception
     * @return $this
     */
    public function addError(LocalizedException $exception): self
    {
        $this->errors[] = $exception;
        return $this;
    }
}
