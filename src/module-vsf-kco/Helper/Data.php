<?php

namespace Kodbruket\VsfKco\Helper;

use Kodbruket\VsfKco\Model\Event;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Kodbruket\VsfKco\Model\EventFactory;

class Data extends AbstractHelper
{
    /**
     * @var EventFactory
     */
    protected $eventFactory;

    /**
     * Constructor.
     *
     * @param Context $context
     */
    public function __construct(
        Context $context,
        EventFactory $eventFactory
    )
    {
        parent::__construct($context);
        $this->eventFactory = $eventFactory;
    }

    /**
     * @param $eventName
     * @param $klarnaOrderId
     * @param $orderId
     * @param $message
     * @param string $rawData
     * @return Event
     * @throws \Exception
     */
    public function trackEvent($eventName, $klarnaOrderId, $orderId, $message, $rawData = '')
    {
        $event = $this->eventFactory->create();
        $event->setEventName($eventName);
        $event->setKlarnaOrderId($klarnaOrderId);
        $event->setOrderId($orderId);
        $event->setMessage($message);
        $event->setRawData($rawData);
        $event->save();

        return $event;
    }

    /**
     * Get next event of an event
     * @param Event $event
     * @return bool|\Magento\Framework\DataObject
     */
    public function getNextEvent(Event $event)
    {
        $collection = $this->eventFactory->create()->getCollection();
        $collection->addFieldToFilter('event_name', $event->getEventName());
        $collection->addFieldToFilter('klarna_order_id', $event->getKlarnaOrderId());
        $collection->getSelect()->where('event_id > ?', $event->getId());
        $collection->getSelect()->order('event_id ASC');
        $nextEvent = $collection->getFirstItem();
        return $nextEvent->getId() ? $nextEvent : false;
    }

    /**
     * Get previous event of an event
     * @param Event $event
     * @return bool|\Magento\Framework\DataObject
     */
    public function getPrevEvent(Event $event)
    {
        $collection = $this->eventFactory->create()->getCollection();
        $collection->addFieldToFilter('event_name', $event->getEventName());
        $collection->addFieldToFilter('klarna_order_id', $event->getKlarnaOrderId());
        $collection->getSelect()->where('event_id < ?', $event->getId());
        $collection->getSelect()->order('event_id DESC');
        $prevEvent = $collection->getFirstItem();
        return $prevEvent->getId() ? $prevEvent : false;
    }
}
