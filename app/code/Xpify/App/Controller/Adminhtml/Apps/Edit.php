<?php
declare(strict_types=1);

namespace Xpify\App\Controller\Adminhtml\Apps;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Edit extends Action implements HttpGetActionInterface
{
    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Xpify_App::app_management';

    protected $rsForwardFactory;

    /**
     * @param Action\Context $context
     * @param \Magento\Backend\Model\View\Result\ForwardFactory $rsForwardFactory
     */
    public function __construct(
        Action\Context $context,
        \Magento\Backend\Model\View\Result\ForwardFactory $rsForwardFactory
    ) {
        parent::__construct($context);
        $this->rsForwardFactory = $rsForwardFactory;
    }

    /**
     * Forward to edit form
     *
     * @return \Magento\Backend\Model\View\Result\Forward|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // create forward to form
        $resultForward = $this->rsForwardFactory->create();
        // set params
        $resultForward->forward('form');
        return $resultForward;
    }
}
