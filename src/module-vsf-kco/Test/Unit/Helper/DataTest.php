<?php

namespace Kodbruket\VsfKco\Test\Unit\Helper;

use Klarna\Ordermanagement\Model\Api\Ordermanagement;
use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject;
use Kodbruket\VsfKco\Helper\Data as DataHelper;

/**
 * @covers DataHelper
 */
class DataTest extends TestCase
{
    use \Kodbruket\VsfKco\Test\TestTraits;

    const STORE_ID = 1;
    const QUOTE_ID = 1001;
    const PARENT_QUOTE_ID = 1000;
    const PRODUCT_ID = 20102;
    const PRODUCT_PRICE = 100;
    const KLARNA_ORDER_ID = 123;
    const ORDER_ID = 456;
    const ORDER_INCREMENT_ID = '100010001';
    const CACHE_IDENTIFIER = 'de6571d30123102e4a49a9483881a05f';
    const PRODUCT_SKU = 'TestProduct';

    /**
     * Mock context
     *
     * @var \Magento\Framework\App\Helper\Context|MockObject
     */
    private $context;

    /**
     * Mock eventFactoryInstance
     *
     * @var \Kodbruket\VsfKco\Model\Event|MockObject
     */
    private $eventFactoryInstance;

    /**
     * Mock eventFactory
     *
     * @var \Kodbruket\VsfKco\Model\EventFactory|MockObject
     */
    private $eventFactory;

    /**
     * Mock storeManager
     *
     * @var \Magento\Store\Model\StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * Mock orderHelper
     *
     * @var \Kodbruket\VsfKco\Helper\Order|MockObject
     */
    private $orderHelper;

    /**
     * Mock publisher
     *
     * @var \Kodbruket\VsfKco\Model\Queue\Publisher|MockObject
     */
    private $publisher;

    /**
     * Mock quoteManagement
     *
     * @var \Magento\Quote\Model\QuoteManagement|MockObject
     */
    private $quoteManagement;

    /**
     * Mock klarnaOrderRepository
     *
     * @var \Klarna\Core\Api\OrderRepositoryInterface|MockObject
     */
    private $klarnaOrderRepository;

    /**
     * Mock mageOrderRepository
     *
     * @var \Magento\Sales\Api\OrderRepositoryInterface|MockObject
     */
    private $mageOrderRepository;

    /**
     * Mock orderCreation
     *
     * @var \Kodbruket\VsfKco\Api\Data\Queue\OrderCreationInterface|MockObject
     */
    private $orderCreation;

    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Object to test
     *
     * @var DataHelper
     */
    private $_helper;

    /**
     * Main set up method
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->context = $this->createMock(\Magento\Framework\App\Helper\Context::class);
        $this->eventFactoryInstance = $this->createMock(\Kodbruket\VsfKco\Model\Event::class);
        $this->eventFactory = $this->getMockupFactory(\Kodbruket\VsfKco\Model\Event::class);
        $this->eventFactory->method('create')->willReturn($this->eventFactoryInstance);
        $this->storeManager = $this->createMock(\Magento\Store\Model\StoreManagerInterface::class);
        $this->orderHelper = $this->createMock(\Kodbruket\VsfKco\Helper\Order::class);
        $this->publisher = $this->createMock(\Kodbruket\VsfKco\Model\Queue\Publisher::class);
        $this->quoteManagement = $this->createMock(\Magento\Quote\Model\QuoteManagement::class);
        $this->klarnaOrderRepository = $this->createMock(\Klarna\Core\Api\OrderRepositoryInterface::class);
        $this->mageOrderRepository = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->orderCreation = $this->createMock(\Kodbruket\VsfKco\Api\Data\Queue\OrderCreationInterface::class);

        $this->orderManagement = $this->createMock(Ordermanagement::class);
        $this->orderHelper->expects($this->any())
            ->method('getOrderManagement')
            ->willReturn($this->orderManagement);

        $this->_helper = $this->objectManager->getObject(
            DataHelper::class,
            [
                'context' => $this->context,
                'eventFactory' => $this->eventFactory,
                'storeManager' => $this->storeManager,
                'orderHelper' => $this->orderHelper,
                'publisher' => $this->publisher,
                'quoteManagement' => $this->quoteManagement,
                'klarnaOrderRepository' => $this->klarnaOrderRepository,
                'mageOrderRepository' => $this->mageOrderRepository,
                'orderCreation' => $this->orderCreation,
                'scopeConfig' => $this->scopeConfig
            ]
        );

        $this->_helper->setStoreId(self::STORE_ID);
    }

    /**
     * @return void
     */
    public function testSetStoreId()
    {
        $return = $this->_helper->setStoreId(self::STORE_ID);
        $this->assertEquals(DataHelper::class, get_class($return));
    }

    /**
     * @return void
     */
    public function testGetStoreId()
    {
        $this->assertEquals(self::STORE_ID, $this->_helper->getStoreId());
    }

    /**
     * @return void
     */
    public function testIsUsingQueueForOrderCreation()
    {
        $expectedResult = (bool)random_int(0, 1);
        $this->scopeConfig->expects($this->any())
            ->method('isSetFlag')
            ->with(DataHelper::XML_ENABLE_QUEUE, ScopeInterface::SCOPE_STORES)
            ->willReturn($expectedResult);

        $this->assertEquals($expectedResult, $this->_helper->isUsingQueueForOrderCreation());
    }

    /**
     * @return void
     */
    public function testIsEnableEventTracking()
    {
        $expectedResult = (bool)random_int(0, 1);
        $this->scopeConfig->expects($this->any())
            ->method('isSetFlag')
            ->with(DataHelper::XML_ENABLE_TRACKING, ScopeInterface::SCOPE_STORES)
            ->willReturn($expectedResult);

        $this->assertEquals($expectedResult, $this->_helper->isEnableEventTracking());
    }

    /**
     * @return array
     */
    public function dataProviderForTestTrackEvent()
    {
        $this->setUp();

        $data = [
            'eventName' => 'eventName',
            'klarnaOrderId' => self::KLARNA_ORDER_ID,
            'orderId' => self::ORDER_ID,
            'message' => '',
            'rawData' => ''
        ];

        $event = $this->eventFactory->create($data);

        return [
            'Case Disabled Tracking' => [
                'prerequisites' => ['tracking' => 0] + $data,
                'expectedResult' => $this->_helper
            ],
            'Case Enabled Tracking' => [
                'prerequisites' => ['tracking' => 1] + $data,
                'expectedResult' => $event
            ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestTrackEvent
     */
    public function testTrackEvent(array $prerequisites, $expectedResult)
    {
        // Test case: Enabled event tracking
        $this->scopeConfig->expects($this->any())
            ->method('isSetFlag')
            ->with(DataHelper::XML_ENABLE_TRACKING, ScopeInterface::SCOPE_STORES)
            ->willReturn($prerequisites['tracking']);

        $result = $this->_helper->trackEvent(
            $prerequisites['eventName'],
            $prerequisites['klarnaOrderId'],
            $prerequisites['orderId'],
            $prerequisites['message'],
            $prerequisites['rawData']
        );

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return array
     */
    public function dataProviderForTestSubmitQuote()
    {
        $this->setUp();

        $billingAddress = $this->getBillingAddress();
        $shippingAddress = $this->getShippingAddress();
        $quote = $this->getQuoteMock($billingAddress, $shippingAddress);

        $klarnaOrderId = self::KLARNA_ORDER_ID;
        $magentoOrderId = self::ORDER_ID;

        // Prepare Klarna Order
        $klarnaOrder = $this->createMock(\Klarna\Core\Model\Order::class);
        $klarnaOrder->expects($this->any())
            ->method('getIsAcknowledged')
            ->willReturn(false);
        $klarnaOrder->expects($this->any())
            ->method($this->logicalOr('getKlarnaOrderId', 'getId'))
            ->willReturn($klarnaOrderId);
        $klarnaOrder->expects($this->any())
            ->method($this->logicalOr('setOrderId', 'setIsAcknowledged'))
            ->willReturn($klarnaOrder);

        // Prepare Magento Order
        $magentoOrder = $this->createMock(\Magento\Sales\Model\Order::class);
        $magentoOrder->expects($this->any())
            ->method('getId')
            ->willReturn($magentoOrderId);

        $data = [
            'klarnaOrderId' => $klarnaOrderId,
            'magentoOrderId' => $magentoOrderId,
            'quote' => $quote,
            'klarnaOrder' => $klarnaOrder,
            'magentoOrder' => $magentoOrder
        ];

        return [
            'Case Disable Queue & No ByPass' => [
                'prerequisites' => ['enableQueue' => 0, 'byPassQueue' => 0] + $data,
                'expectedResult' => $this->_helper
            ],
            'Case Disable Queue & ByPass' => [
                'prerequisites' => ['enableQueue' => 0, 'byPassQueue' => 1] + $data,
                'expectedResult' => $this->_helper
            ],
            'Case Enabled Queue & No ByPass' => [
                'prerequisites' => ['enableQueue' => 1, 'byPassQueue' => 0] + $data,
                'expectedResult' => $this->_helper
            ],
            'Case Enabled Queue & ByPass' => [
                'prerequisites' => ['enableQueue' => 1, 'byPassQueue' => 1] + $data,
                'expectedResult' => $this->_helper
            ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestSubmitQuote
     */
    public function testSubmitQuote(array $prerequisites, $expectedResult)
    {
        // Enable queue
        $this->scopeConfig->expects($this->any())
            ->method('isSetFlag')
            ->with($this->logicalOr(
                DataHelper::XML_ENABLE_TRACKING, ScopeInterface::SCOPE_STORES,
                DataHelper::XML_ENABLE_QUEUE, ScopeInterface::SCOPE_STORES
            ))
            ->willReturn($prerequisites['enableQueue']);

        // Setup klarnaOrderRepository
        $this->klarnaOrderRepository->expects($this->any())
            ->method('getByKlarnaOrderId')
            ->with($prerequisites['klarnaOrderId'])
            ->willReturn($prerequisites['klarnaOrder']);

        // Setup mageOrderRepository
        $this->mageOrderRepository->expects($this->any())
            ->method('get')
            ->with($prerequisites['magentoOrderId'])
            ->willReturn($prerequisites['magentoOrder']);

        $this->quoteManagement->expects($this->any())
            ->method('submit')
            ->with($prerequisites['quote'])
            ->willReturn($prerequisites['magentoOrder']);

        $result = $this->_helper->submitQuote($prerequisites['klarnaOrderId'], $prerequisites['quote'], $prerequisites['byPassQueue']);

        $this->assertNull($result);
    }

    /**
     * @param null $product
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getQuoteItemMock($product = null)
    {
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->setMethods([
                'getSku',
                'getQty',
                'getCalculationPrice',
                'getName',
                'getIsVirtual',
                'getProductId',
                'getProduct'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $quoteItem->method('getName')
            ->willReturn('Test Product');
        $quoteItem->method('getSku')
            ->willReturn('TestProduct');
        $quoteItem->method('getQty')
            ->willReturn(1);
        $quoteItem->method('getCalculationPrice')
            ->willReturn(self::PRODUCT_PRICE);
        $quoteItem->method('getIsVirtual')
            ->willReturn(false);
        $quoteItem->method('getProductId')
            ->willReturn(self::PRODUCT_ID);
        $quoteItem->method('getProduct')
            ->willReturn($product ? $product : $this->getProductMock());
        return $quoteItem;
    }

    /**
     * Get quote mock with quote items
     *
     * @param $billingAddress
     * @param $shippingAddress
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getQuoteMock($billingAddress, $shippingAddress)
    {
        $quoteItem = $this->getQuoteItemMock();
        $quoteMethods = [
            'getId', 'getBoltParentQuoteId', 'getSubtotal', 'getAllVisibleItems',
            'getAppliedRuleIds', 'isVirtual', 'getShippingAddress', 'collectTotals',
            'getQuoteCurrencyCode', 'getBillingAddress', 'getReservedOrderId', 'getTotals',
            'getStoreId', 'getUseRewardPoints', 'getUseCustomerBalance', 'getRewardCurrencyAmount',
            'getCustomerBalanceAmountUsed', 'getData'
        ];
        $quote = $this->getMockBuilder(Quote::class)
            ->setMethods($quoteMethods)
            ->disableOriginalConstructor()
            ->getMock();
        $quote->method('getId')
            ->willReturn(self::QUOTE_ID);
        $quote->method('getReservedOrderId')
            ->willReturn(self::ORDER_INCREMENT_ID);
        $quote->method('getBoltParentQuoteId')
            ->willReturn(self::PARENT_QUOTE_ID);
        $quote->method('getSubtotal')
            ->willReturn(self::PRODUCT_PRICE);
        $quote->method('getAllVisibleItems')
            ->willReturn([$quoteItem]);
        $quote->method('getAppliedRuleIds')
            ->willReturn('2,3');
        $quote->method('isVirtual')
            ->willReturn(false);
        $quote->method('getBillingAddress')
            ->willReturn($billingAddress);
        $quote->method('getShippingAddress')
            ->willReturn($shippingAddress);
        $quote->method('getQuoteCurrencyCode')
            ->willReturn('$');
        $quote->method('collectTotals')
            ->willReturnSelf();
        //$quote->method('getTotals')
        //    ->willReturn([]);
        $quote->expects($this->any())
            ->method('getStoreId')
            ->will($this->returnValue("1"));
        // $quote->method('getUseRewardPoints')
        //     ->willReturn(false);
        //$quote->method('getUseCustomerBalance')
        //    ->willReturn(false);
        return $quote;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getBillingAddress()
    {
        $addressData = $this->getAddressData();
        $billingAddress = $this->getMockBuilder(Quote\Address::class)
            ->setMethods([
                'getFirstname', 'getLastname', 'getCompany', 'getTelephone', 'getStreetLine',
                'getCity', 'getRegion', 'getPostcode', 'getCountryId', 'getEmail',
                'getDiscountAmount', 'getCouponCode'
            ])
            ->disableOriginalConstructor()
            ->getMock();
        $billingAddress->method('getFirstname')
            ->willReturn($addressData['first_name']);
        $billingAddress->method('getLastname')
            ->willReturn($addressData['last_name']);
        $billingAddress->method('getCompany')
            ->willReturn($addressData['company']);
        $billingAddress->method('getTelephone')
            ->willReturn($addressData['phone']);
        $billingAddress->method('getStreetLine')
            ->will($this->returnValueMap([
                [1, $addressData['street_address1']],
                [2, $addressData['street_address2']]
            ]));
        $billingAddress->method('getCity')
            ->willReturn($addressData['locality']);
        $billingAddress->method('getRegion')
            ->willReturn($addressData['region']);
        $billingAddress->method('getPostcode')
            ->willReturn($addressData['postal_code']);
        $billingAddress->method('getCountryId')
            ->willReturn($addressData['country_code']);
        $billingAddress->method('getEmail')
            ->willReturn($addressData['email']);
        return $billingAddress;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getShippingAddress()
    {
        return $this->getBillingAddress();
    }

    /**
     * @return array
     */
    private function getAddressData()
    {
        return [
            'company' => "",
            'country' => "Sweden",
            'country_code' => "SE",
            'email' => "test@kodbruket.se",
            'first_name' => "Tester",
            'last_name' => "User",
            'locality' => "Kalmar",
            'phone' => "+45123456789",
            'postal_code' => "12345",
            'region' => "Kalmar",
            'street_address1' => "The big street",
            'street_address2' => null,
        ];
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getProductMock()
    {
        $product = $this->getMockBuilder(Product::class)
            ->setMethods(['getId', 'getDescription', 'getTypeInstance', 'getOrderOptions'])
            ->disableOriginalConstructor()
            ->getMock();
        $product->method('getId')
            ->willReturn(self::PRODUCT_ID);
        $product->method('getDescription')
            ->willReturn('Product Description');
        $product->method('getTypeInstance')
            ->willReturnSelf();
        $product->method('getOrderOptions')
            ->withAnyParameters()
            ->willReturn([]);
        return $product;
    }
}
