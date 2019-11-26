<?php

namespace Kodbruket\VsfKco\Controller\Adminhtml\Event;

use Magento\Backend\App\Action;

class Index extends Action
{
    protected $resultPageFactory = false;
    protected $_publicActions = ['index'];

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('Klarna Events')));

        return $resultPage;
    }
}
