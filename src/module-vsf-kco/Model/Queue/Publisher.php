<?php
namespace Kodbruket\VsfKco\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use Kodbruket\VsfKco\Api\Data\Queue\OrderCreationInterface;

class Publisher
{
    const ORDER_QUEUE_TOPIC_NAME = 'vsf.kco.klarna.order.create';

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(PublisherInterface $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * @param OrderCreationInterface $orderCreation
     */
    public function execute(OrderCreationInterface $orderCreation)
    {
        $this->publisher->publish(self::ORDER_QUEUE_TOPIC_NAME, $orderCreation);
    }
}
