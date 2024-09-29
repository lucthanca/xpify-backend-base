<?php
declare(strict_types=1);

namespace Xpify\Merchant\Controller\Adminhtml\Merchants;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Xpify\Merchant\Model\MerchantFactory;

class Form extends \Magento\Backend\App\Action implements HttpGetActionInterface
{
    private MerchantFactory $merchantFactory;
    private Registry $registry;
    private PageFactory $pageFactory;
    private DataPersistorInterface $dataPersistor;

    /**
     * @param Context $context
     * @param MerchantFactory $merchantFactory
     * @param DataPersistorInterface $dataPersistor
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        \Xpify\Merchant\Model\MerchantFactory $merchantFactory,
        DataPersistorInterface $dataPersistor,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    ) {
        parent::__construct($context);
        $this->merchantFactory = $merchantFactory;
        $this->pageFactory = $pageFactory;
        $this->dataPersistor = $dataPersistor;
    }

    public function execute()
    {
        // Create emtpty merchant
        $merchant = $this->merchantFactory->create();
        if ($id = $this->getRequest()->getParam('id')) {
            $merchant->load($id);
        } else {
            throw new NoSuchEntityException(__('Merchant not found'));
        }

        if (!$merchant->getId()) {
            throw new NoSuchEntityException(__('Merchant not found'));
        }
        $title = __("Edit: %1", $merchant->getName());
        $this->dataPersistor->set('current_merchant', $merchant);
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
