<?php
declare(strict_types=1);

namespace Xpify\Merchant\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Shopify\Auth\AccessTokenOnlineUserInfo;
use Shopify\Auth\Session;
use Shopify\Auth\SessionStorage;
use Xpify\App\Service\GetCurrentApp;
use Xpify\Merchant\Api\MerchantRepositoryInterface as IMerchantRepository;
use Xpify\Merchant\Api\Data\MerchantInterface as IMerchant;

class MerchantStorage implements SessionStorage
{
    private array $runtimeCache = [];
    private IMerchantRepository $merchantRepository;
    private \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder;
    private GetCurrentApp $getCurrentApp;

    /**
     * @param IMerchantRepository $merchantRepository
     * @param GetCurrentApp $getCurrentApp
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        IMerchantRepository $merchantRepository,
        GetCurrentApp $getCurrentApp,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->merchantRepository = $merchantRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->getCurrentApp = $getCurrentApp;
    }

    /**
     * @inheritDoc
     */
    public function storeSession(Session $session): bool
    {
        $app = $this->getCurrentApp->get();
        try {
            $merchant = $this->loadMerchantBySessionid($session->getId());
        } catch (NoSuchEntityException $e) {
            $merchant = $this->merchantRepository->create();
            $merchant->setSessionId($session->getId());
            $merchant->setAppId((int) $app->getId());
        }
        $merchant->setShop($session->getShop());
        $merchant->setState($session->getState());
        $merchant->setIsOnline((int) $session->isOnline());
        $merchant->setAccessToken($session->getAccessToken());
        $merchant->setExpiresAt($session->getExpires());
        $merchant->setScope($session->getScope());
        if (!empty($session->getOnlineAccessInfo())) {
            $merchant->setUserId($session->getOnlineAccessInfo()->getId());
            $merchant->setUserFirstName($session->getOnlineAccessInfo()->getFirstName());
            $merchant->setUserLastName($session->getOnlineAccessInfo()->getLastName());
            $merchant->setUserEmail($session->getOnlineAccessInfo()->getEmail());
            $merchant->setUserEmailVerified((int) $session->getOnlineAccessInfo()->isEmailVerified());
            $merchant->setAccountOwner((int) $session->getOnlineAccessInfo()->isAccountOwner());
            $merchant->setLocale($session->getOnlineAccessInfo()->getLocale());
            $merchant->setCollaborator((int) $session->getOnlineAccessInfo()->isCollaborator());
        }
        try {
            $this->merchantRepository->save($merchant);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function loadSession(string $sessionId): ?Session
    {
        try {
            $merchant = $this->loadMerchantBySessionid($sessionId);
        } catch (NoSuchEntityException $e) {
            return null;
        }
        $session = new Session(
            $merchant->getSessionId(),
            $merchant->getShop(),
            $merchant->getIsOnline() === 1,
            $merchant->getState()
        );
        if ($merchant->getExpiresAt()) {
            $session->setExpires($merchant->getExpiresAt());
        }
        if ($merchant->getAccessToken()) {
            $session->setAccessToken($merchant->getAccessToken());
        }
        $session->setScope($merchant->getScope());
        if ($merchant->getUserId()) {
            $onlineAccessInfo = new AccessTokenOnlineUserInfo(
                $merchant->getUserId(),
                $merchant->getUserFirstName(),
                $merchant->getUserLastName(),
                $merchant->getUserEmail(),
                $merchant->getUserEmailVerified() === 1,
                $merchant->getAccountOwner() === 1,
                $merchant->getLocale(),
                $merchant->getCollaborator() === 1
            );
            $session->setOnlineAccessInfo($onlineAccessInfo);
        }
        return $session;
    }

    /**
     * @inheritDoc
     */
    public function deleteSession(string $sessionId): bool
    {
        try {
            $merchant = $this->loadMerchantBySessionid($sessionId);
            $this->merchantRepository->delete($merchant);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Load merchant by session id
     *
     * @param string $sessId
     * @return IMerchant
     * @throws NoSuchEntityException
     */
    public function loadMerchantBySessionid(string $sessId): IMerchant
    {
        $app = $this->getCurrentApp->get();
        if (isset($this->runtimeCache[$this->getCachedId($sessId)])) {
            return $this->runtimeCache[$this->getCachedId($sessId)];
        }
        $this->searchCriteriaBuilder->addFilter(IMerchant::SESSION_ID, $sessId);
        $this->searchCriteriaBuilder->addFilter(IMerchant::APP_ID, $app->getId());
        $this->searchCriteriaBuilder->setPageSize(1);
        $searchResults = $this->merchantRepository->getList($this->searchCriteriaBuilder->create());
        if ($searchResults->getTotalCount() === 0) {
            throw new NoSuchEntityException(__('Merchant with session id "%1" does not exist.', $sessId));
        }
        /** @var IMerchant $merchant */
        $merchant = current($searchResults->getItems());
        if (!$merchant->getId()) {
            throw new NoSuchEntityException(__('Merchant with session id "%1" does not exist.', $sessId));
        }
        $this->runtimeCache[$this->getCachedId($sessId)] = $merchant;
        return $merchant;
    }

    /**
     * Get cached id
     *
     * @param string $sessionId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getCachedId(string $sessionId): string
    {
        $app = $this->getCurrentApp->get();
        return $sessionId . "_{$app->getRemoteId()}";
    }
}
