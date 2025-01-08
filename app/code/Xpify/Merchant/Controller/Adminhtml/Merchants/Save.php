<?php
declare(strict_types=1);

namespace Xpify\Merchant\Controller\Adminhtml\Merchants;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Xpify\Merchant\Ui\Component\Form\MerchantDataProvider;

class Save extends Action implements HttpPostActionInterface
{
    const ADMIN_RESOURCE = Edit::ADMIN_RESOURCE;

    public function execute()
    {
        $id = $this->getRequest()->getPost()->toArray()[MerchantDataProvider::GENERAL_FIELDSET_NAME]['entity_id'] ?? null;
        // do nothing yet
        $redirectData = [
            'path' => 'xpify/merchants/edit',
            'params' => compact('id')
        ];
        return $this->resultRedirectFactory->create()->setPath($redirectData['path'], $redirectData['params']);
    }
}
