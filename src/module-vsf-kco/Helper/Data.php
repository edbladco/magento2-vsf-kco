<?php

namespace Kodbruket\VsfKco\Helper;

use Klarna\Core\Api\OrderRepositoryInterface;
use Kodbruket\VsfKco\Model\Event;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Kodbruket\VsfKco\Model\EventFactory;
use Kodbruket\VsfKco\Model\Queue\Publisher;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderRepositoryInterface as MageOrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Kodbruket\VsfKco\Api\Data\Queue\OrderCreationInterface;

class Data extends AbstractHelper
{
    const EVENT_NAME = "Helper";
    const XML_ENABLE_QUEUE = 'klarna/vsf/create_order_using_queue';
    const XML_ENABLE_TRACKING = 'klarna/tracking/enable';

    private $_storeId = null;

    /**
     * @var EventFactory
     */
    protected $eventFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Kodbruket\VsfKco\Helper\Order
     */
    protected $orderHelper;

    /**
     * @var Publisher
     */
    protected $publisher;

    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var OrderRepositoryInterface
     */
    protected $klarnaOrderRepository;

    /**
     * @var MageOrderRepositoryInterface
     */
    protected $mageOrderRepository;

    /**
     * @var OrderCreationInterface
     */
    protected $orderCreation;

    /**
     * Constructor.
     * @param Context $context
     * @param EventFactory $eventFactory
     * @param StoreManagerInterface $storeManager
     * @param \Kodbruket\VsfKco\Helper\Order $orderHelper
     * @param Publisher $publisher
     * @param QuoteManagement $quoteManagement
     * @param OrderRepositoryInterface $klarnaOrderRepository
     * @param MageOrderRepositoryInterface $mageOrderRepository
     * @param OrderCreationInterface $orderCreation
     */
    public function __construct(
        Context $context,
        EventFactory $eventFactory,
        StoreManagerInterface $storeManager,
        \Kodbruket\VsfKco\Helper\Order $orderHelper,
        Publisher $publisher,
        QuoteManagement $quoteManagement,
        OrderRepositoryInterface $klarnaOrderRepository,
        MageOrderRepositoryInterface $mageOrderRepository,
        OrderCreationInterface $orderCreation
    )
    {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->eventFactory = $eventFactory;
        $this->orderHelper = $orderHelper;
        $this->publisher = $publisher;
        $this->quoteManagement = $quoteManagement;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->mageOrderRepository = $mageOrderRepository;
        $this->orderCreation = $orderCreation;
    }

    /**
     * @param $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = $storeId;
        return $this;
    }

    /**
     * @return int|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId()
    {
        if(null === $this->_storeId){
            $this->_storeId = $this->storeManager->getStore()->getId();
        }
        return $this->_storeId;
    }

    /**
     * @return bool
     */
    public function isUsingQueueForOrderCreation()
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLE_QUEUE, ScopeInterface::SCOPE_STORES, $this->getStoreId());
    }

    /**
     * @return bool
     */
    public function isEnableEventTracking()
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLE_TRACKING, ScopeInterface::SCOPE_STORES, $this->getStoreId());
    }

    /**
     * @param $klarnaOrderId
     * @param CartInterface $quote
     * @param bool $byPassQueue
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function submitQuote($klarnaOrderId, CartInterface $quote, $byPassQueue = false)
    {
        $this->setStoreId($quote->getStoreId());

        if(!$byPassQueue && $this->isUsingQueueForOrderCreation()){
            $this->orderCreation->setKlarnaOrderId($klarnaOrderId);
            $this->orderCreation->setQuote($quote);
            $result = $this->publisher->execute($this->orderCreation);
            $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, 'Added to cart creation queue', 'Quote ID: ' . $quote->getId());
        }
        else{
            $order = false;
            try {
                $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);

                if ($klarnaOrder->getIsAcknowledged()) {
                    $message = 'Error: Order ' . $klarnaOrderId . ' has been acknowledged';
                    $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, $message);
                    return;
                }

                $order = $this->quoteManagement->submit($quote);
                $orderId = $order->getId();
                if ($orderId) {
                    $klarnaOrder->setOrderId($orderId)->save();
                }

                $isAcknowledge = $this->acknowledgeOrder($klarnaOrderId, $order->getId(), $quote->getId());

                if($isAcknowledge){
                    if($this->isUsingQueueForOrderCreation()){
                        $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, $order->getId(), 'Magento order created by Queue with ID ' . $order->getIncrementId());
                    }
                    else{
                        $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, $order->getId(), 'Magento order created in PushController with ID ' . $order->getIncrementId());
                    }
                }
                else{
                    $message = 'Error: Could not acknowledge the Order ' . $klarnaOrderId;
                    throw new \Exception($message);
                }
            } catch (\Exception $exception) {
                if($this->isUsingQueueForOrderCreation()){
                    $message = 'Create order error by Queue. QuoteID: '.$quote->getId() . $exception->getMessage();
                }
                else{
                    $message = 'Create order error in PushController. QuoteID: '.$quote->getId() . $exception->getMessage();
                }
                $this->orderHelper->cancel($klarnaOrderId, $order ? $order->getId() : false, $message, $exception);
                $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, $order ? $order->getId() : false, $message, $exception->getTraceAsString());
            }
        }
    }

    /**
     * @param $klarnaOrderId
     * @param $orderId
     * @param $quoteId
     */
    private function acknowledgeOrder($klarnaOrderId, $orderId, $quoteId)
    {
        if ($klarnaOrderId && $orderId && $quoteId) {
            try {
                $mageOrder = $this->mageOrderRepository->get($orderId);
                $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);
                $this->orderHelper->getOrderManagement()->updateMerchantReferences($klarnaOrderId, $mageOrder->getIncrementId(), $quoteId);
                $this->orderHelper->getOrderManagement()->acknowledgeOrder($klarnaOrderId);
                $klarnaOrder->setOrderId($orderId)
                    ->setIsAcknowledged(true)
                    ->save();
                $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, $orderId, 'Sent ACK successfully with Klarna ID: ' . $klarnaOrderId);
                return true;
            } catch (\Exception $exception) {
                $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, $orderId, 'Send ACK error: ' . $exception->getMessage(), $exception->getTraceAsString());
            }
        } else {
            $this->trackEvent(self::EVENT_NAME, $klarnaOrderId, $orderId, 'Something went wrong when sending ACK');
        }
        return false;
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
        if(!$this->isEnableEventTracking()){
            return $this;
        }

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
