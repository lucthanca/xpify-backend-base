<?php
declare(strict_types=1);

namespace Xpify\Core\Model\Authorization;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTime as Date;
use Magento\Framework\Webapi\Request;
use Magento\Integration\Api\Exception\UserTokenException;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\UserTokenReaderInterface;
use Magento\Integration\Api\UserTokenValidatorInterface;
use Magento\Integration\Helper\Oauth\Data as OauthHelper;
use Magento\Integration\Model\Oauth\TokenFactory;

class TokenUserContext extends \Magento\Webapi\Model\Authorization\TokenUserContext
{

    /**
     * @var UserTokenReaderInterface
     */
    private $userTokenReader;

    /**
     * @var UserTokenValidatorInterface
     */
    private $userTokenValidator;

    /**
     * Initialize dependencies.
     *
     * @param Request $request
     * @param TokenFactory $tokenFactory
     * @param IntegrationServiceInterface $integrationService
     * @param DateTime|null $dateTime
     * @param Date|null $date
     * @param OauthHelper|null $oauthHelper
     * @param UserTokenReaderInterface|null $tokenReader
     * @param UserTokenValidatorInterface|null $tokenValidator
     */
    public function __construct(
        Request $request,
        TokenFactory $tokenFactory,
        IntegrationServiceInterface $integrationService,
        DateTime $dateTime = null,
        Date $date = null,
        OauthHelper $oauthHelper = null,
        ?UserTokenReaderInterface $tokenReader = null,
        ?UserTokenValidatorInterface $tokenValidator = null
    ) {
        $this->request = $request;
        $this->tokenFactory = $tokenFactory;
        $this->integrationService = $integrationService;
        $this->userTokenReader = $tokenReader ?? ObjectManager::getInstance()->get(UserTokenReaderInterface::class);
        $this->userTokenValidator = $tokenValidator
            ?? ObjectManager::getInstance()->get(UserTokenValidatorInterface::class);
        parent::__construct($request, $tokenFactory, $integrationService, $dateTime, $date, $oauthHelper, $tokenReader, $tokenValidator);
    }

    protected function processRequest()
    {
        if ($this->isRequestProcessed) {
            return;
        }

        $authorizationHeaderValue = $this->request->getHeader('x-authorization');
        if (!$authorizationHeaderValue) $this->request->getHeader('Authorization');

        if (!$authorizationHeaderValue) {
            $this->isRequestProcessed = true;
            return;
        }

        $headerPieces = explode(" ", $authorizationHeaderValue);
        if (count($headerPieces) !== 2) {
            $this->isRequestProcessed = true;
            return;
        }

        $tokenType = strtolower($headerPieces[0]);
        if ($tokenType !== 'bearer') {
            $this->isRequestProcessed = true;
            return;
        }

        $bearerToken = $headerPieces[1];
        try {
            $token = $this->userTokenReader->read($bearerToken);
        } catch (UserTokenException $exception) {
            $this->isRequestProcessed = true;
            return;
        }
        try {
            $this->userTokenValidator->validate($token);
        } catch (AuthorizationException $exception) {
            $this->isRequestProcessed = true;
            return;
        }

        $this->userType = $token->getUserContext()->getUserType();
        $this->userId = $token->getUserContext()->getUserId();
        $this->isRequestProcessed = true;
    }
}
