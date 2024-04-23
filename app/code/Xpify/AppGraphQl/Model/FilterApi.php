<?php
declare(strict_types=1);

namespace Xpify\AppGraphQl\Model;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\AuthorizationInterface;
use Xpify\Core\GraphQl\ApiFilterInterface as IApiFilter;
use Xpify\App\Api\Data\AppInterface as IApp;

class FilterApi implements IApiFilter
{
    private AuthorizationInterface $authorization;

    /**
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        \Magento\Framework\AuthorizationInterface $authorization
    ) {
        $this->authorization = $authorization;
    }

    /**
     * Check if the current request is valid
     *
     * @param string|null $currentAppID
     * @return bool
     */
    public function isValid(?IApp $authApp): bool
    {
        $context = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\Registry::class)->registry('g_context');
        $isAdmin = $context->getUserId() && $context->getUserType() === UserContextInterface::USER_TYPE_ADMIN;
        $isAllowed = $this->authorization->isAllowed(\Xpify\App\Controller\Adminhtml\Apps\Edit::ADMIN_RESOURCE);
        return $isAdmin && $isAllowed;
    }
}
