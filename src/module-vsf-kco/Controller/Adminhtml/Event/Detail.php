<?php

namespace Kodbruket\VsfKco\Controller\Adminhtml\Event;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Kodbruket\VsfKco\Model\EventFactory;

class Detail extends Action
{
    protected $resultPageFactory = false;
    protected $_publicActions = ['detail'];

    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry;

    /**
     * Detail constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EventFactory $eventFactory
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        EventFactory $eventFactory,
        Registry $registry
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->eventFactory = $eventFactory;
        $this->coreRegistry = $registry;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface|Page
     */
    public function execute()
    {
        $id = (int)$this->_request->getParam('id');
        $event = $this->eventFactory->create()->load($id);
        if ($id && $event->getId()) {
            $this->coreRegistry->register('vsf_current_event', $event);
        } else {
            $this->messageManager->addError(__('This event no longer exists.'));
            /** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('*/*/');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('Event Details')));
        return $resultPage;
    }
}
