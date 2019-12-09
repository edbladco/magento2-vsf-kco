<?php
namespace Kodbruket\VsfKco\Test\Unit\Helper;

use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Klarna\Ordermanagement\Model\Api\Ordermanagement;

/**
 * @covers \Kodbruket\VsfKco\Helper\Order
 */
class OrderTest extends TestCase
{
    /**
     * Mock context
     *
     * @var \Magento\Framework\App\Helper\Context|PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    /**
     * Mock resource
     *
     * @var \Magento\Framework\App\ResourceConnection|PHPUnit_Framework_MockObject_MockObject
     */
    private $resource;

    /**
     * Mock objectmanager
     *
     * @var \Magento\Framework\ObjectManagerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $objectmanager;

    /**
     * Mock orderRepository
     *
     * @var \Magento\Sales\Api\OrderRepositoryInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $orderRepository;

    /**
     * Mock scopeConfig
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $scopeConfig;

    /**
     * Mock emailHelper
     *
     * @var \Kodbruket\VsfKco\Helper\Email|PHPUnit_Framework_MockObject_MockObject
     */
    private $emailHelper;

    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Object to test
     *
     * @var \Kodbruket\VsfKco\Helper\Order
     */
    private $_helper;

    /**
     * Mock orderManagement
     *
     * @var Ordermanagement
     */
    private $orderManagement;

    /**
     * Main set up method
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->context = $this->createMock(\Magento\Framework\App\Helper\Context::class);
        $this->resource = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $this->objectmanager = $this->createMock(\Magento\Framework\ObjectManagerInterface::class);
        $this->orderRepository = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->emailHelper = $this->createMock(\Kodbruket\VsfKco\Helper\Email::class);

        $this->orderManagement = $this->createMock(Ordermanagement::class);
        $this->objectmanager->expects($this->any())
            ->method('create')
            ->with(Ordermanagement::class)
            ->willReturn($this->orderManagement);

        $this->_helper = $this->objectManager->getObject(
        \Kodbruket\VsfKco\Helper\Order::class,
            [
                'context' => $this->context,
                'resource' => $this->resource,
                'objectmanager' => $this->objectmanager,
                'orderRepository' => $this->orderRepository,
                'scopeConfig' => $this->scopeConfig,
                'emailHelper' => $this->emailHelper,
            ]
        );
    }

    /**
     * @return void
     */
    public function testGetOrderManagement()
    {
        $this->assertEquals($this->orderManagement, $this->_helper->getOrderManagement());
    }

    /**
     * @return array
     */
    public function dataProviderForTestCancel()
    {
        $data = [
            'klarnaOrderId' => '123',
            'orderId' => '456',
            'message' => '',
            'exception' => null
        ];

        return [
            'Case: Disabled Order Cancellation' => [
                'prerequisites' => [ 'canCancel' => 0 ] + $data,
                'expectedResult' => false
            ],
            'Case: Enabled Order Cancellation' => [
                'prerequisites' => [ 'canCancel' => 1 ] + $data,
                'expectedResult' => (bool)random_int(0, 1)
        ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestCancel
     */
    public function testCancel(array $prerequisites, bool $expectedResult)
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(\Kodbruket\VsfKco\Helper\Order::XML_KLARNA_CANCEL_ALLOW)
            ->willReturn($prerequisites['canCancel']);

        $this->orderManagement->expects($this->any())
            ->method('cancel')
            ->with($prerequisites['klarnaOrderId'])
            ->willReturn(new DataObject(['is_successful' => $expectedResult]));

        $result = $this->_helper->cancel($prerequisites['klarnaOrderId'], $prerequisites['orderId'], $prerequisites['message'], $prerequisites['exception']);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testCanCancelKlarnaOrder()
    {
        $expectedResult = (bool)random_int(0,1);
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with(\Kodbruket\VsfKco\Helper\Order::XML_KLARNA_CANCEL_ALLOW)
            ->willReturn($expectedResult);

        $result = $this->_helper->canCancelKlarnaOrder();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function dataProviderForTestGetFailingOrderStatus()
    {
        $statuses = [
            \Magento\Sales\Model\Order::STATE_NEW,
            \Magento\Sales\Model\Order::STATE_PROCESSING,
            \Magento\Sales\Model\Order::STATE_COMPLETE,
            \Magento\Sales\Model\Order::STATE_CLOSED,
            \Magento\Sales\Model\Order::STATE_CANCELED,
            \Magento\Sales\Model\Order::STATE_HOLDED,
        ];

        $expectedResult = $statuses[array_rand($statuses)];

        return [
            'Testcase 1' => [
                'prerequisites' => $expectedResult,
                'expectedResult' => $expectedResult
            ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestGetFailingOrderStatus
     */
    public function testGetFailingOrderStatus(string $prerequisites, string $expectedResult)
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with(\Kodbruket\VsfKco\Helper\Order::XML_KLARNA_CANCEL_ORDER_STATUS)
            ->willReturn($prerequisites);

        $this->assertEquals($expectedResult, $this->_helper->getFailingOrderStatus());
    }
}
