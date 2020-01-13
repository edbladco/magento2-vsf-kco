<?php

namespace Kodbruket\VsfKco\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Klarna\Ordermanagement\Model\Api\Ordermanagement;
use Kodbruket\VsfKco\Helper\Email as EmailHelper;

class Order extends AbstractHelper
{
    const XML_KLARNA_CANCEL_ALLOW = 'klarna/cancel/allow';
    const XML_KLARNA_CANCEL_ORDER_STATUS = 'klarna/cancel/failing_order_status';

    /**
     * @var ResourceConnection
     */
    private $_resource;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectmanager;

    /**
     * @var Ordermanagement
     */
    protected $orderManagement;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var EmailHelper
     */
    protected $emailHelper;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        ObjectManagerInterface $objectmanager,
        OrderRepositoryInterface $orderRepository,
        ScopeConfigInterface $scopeConfig,
        EmailHelper $emailHelper
    )
    {
        parent::__construct($context);
        $this->_resource = $resource;
        $this->objectmanager = $objectmanager;
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
        $this->emailHelper = $emailHelper;
    }

    public function getOrderManagement()
    {
        if(!$this->orderManagement) {
            $this->orderManagement = $this->objectmanager->create(Ordermanagement::class);
        }

        return $this->orderManagement;
    }

    /**
     * Cancel Klarna and Magento order
     * @param string $klarnaOrderId
     * @param int|bool $orderId
     * @param string $message
     * @param null $exception
     */
    public function cancel(string $klarnaOrderId, $orderId = false, $message = '', $exception = null)
    {
        if ($this->canCancelKlarnaOrder() && $this->_cancelKlarnaOrder($klarnaOrderId)){
            // Cancel Magento order (if it is created)
            $this->_cancelMagentoOrder($orderId);
            // Email to admin
            $this->emailHelper->sendOrderCancelEmail($klarnaOrderId, $orderId, $message, $exception);
            return true;
        }

        return false;
    }

    /**
     * Cancel Klarna order by ID
     * @param string $klarnaOrderId
     * @return bool
     */
    private function _cancelKlarnaOrder(string $klarnaOrderId)
    {
        if(!$klarnaOrderId){
            return false;
        }
        
        $response = $this->getOrderManagement()->cancel($klarnaOrderId);
        return $response && (bool)$response->getIsSuccessful();
    }

    /**
     * Cancel Magento order
     * @param int|bool $orderId
     * @return bool
     */
    private function _cancelMagentoOrder($orderId)
    {
        if(!$orderId || $orderId <= 0){
            return false;
        }

        if($status = $this->getFailingOrderStatus()){
            $state = $this->getDefaultStateByStatus($status);
            try{
                $order = $this->orderRepository->get($orderID);
                $order->setState($state)->setStatus($status);
                $this->orderRepository->save($order);
                return true;
            } catch (\Exception $e){
                return false;
            }
        }

        return false;
    }

    /**
     * Get default order state code by status code
     * @param string $status
     * @return string
     */
    protected function getDefaultStateByStatus(string $status)
    {
        $connection = $this->_resource->getConnection();
        $select = $connection
            ->select('state')
            ->from(['s' => $resource->getTableName('sales_order_status_state')])
            ->where('s.status = ? AND s.is_default = 1', $status);
        return (string)$connection->fetchOne($select) ?: $status;
    }

    /**
     * Cancel Klarna order or not
     * @return bool
     */
    public function canCancelKlarnaOrder()
    {
        return $this->scopeConfig->isSetFlag(self::XML_KLARNA_CANCEL_ALLOW);
    }

    /**
     * Get failing order status
     * @return string
     */
    public function getFailingOrderStatus()
    {
        return (string)$this->scopeConfig->getValue(self::XML_KLARNA_CANCEL_ORDER_STATUS);
    }
}
