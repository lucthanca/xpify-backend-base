<?php
declare(strict_types=1);

namespace Xpify\Merchant\Controller\Adminhtml\Merchants;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Save extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = Edit::ADMIN_RESOURCE;

    public function execute()
    {
        // do nothing yet
        $redirectData = [
            'path' => 'xpify/merchants',
            'params' => []
        ];
        return $this->resultRedirectFactory->create()->setPath($redirectData['path'], $redirectData['params']);
    }
}
