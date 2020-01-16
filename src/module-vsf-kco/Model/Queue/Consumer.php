<?php
namespace Kodbruket\VsfKco\Model\Queue;

use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Kodbruket\VsfKco\Api\Data\Queue\OrderCreationInterface;
use Kodbruket\VsfKco\Helper\Data as VsfKcoHelper;

class Consumer
{
    const EVENT_NAME = "Queue";

    /**
     * @var VsfKcoHelper
     */
    protected $helper;

    public function __construct(VsfKcoHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param OrderCreationInterface $orderCreation
     * @throws LocalizedException
     * @return void
     */
    public function processMessage(OrderCreationInterface $orderCreation)
    {
        $this->helper->submitQuote($orderCreation->getKlarnaOrderId(), $orderCreation->getQuote(), true);
    }
}
