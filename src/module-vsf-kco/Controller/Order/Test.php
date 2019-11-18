<?php
namespace Kodbruket\VsfKco\Controller\Order;

class Test extends Action
{
    public function execute()
    {
    	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$state = $objectManager->get(\Magento\Framework\App\State::class);
		$state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);
		$emailHelper = $objectManager->get(\Kodbruket\VsfKco\Helper\Email::class);
		$emailHelper->sendOrderCancelEmail("123123", "KKK");
    	die('123');
    }
}