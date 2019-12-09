<?php
namespace Kodbruket\VsfKco\Test\Unit\Controller\Order;

use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * @covers \Kodbruket\VsfKco\Controller\Order\Push
 */
class PushTest extends TestCase
{
    use \Kodbruket\VsfKco\Test\TestTraits;

    const KLARNA_ORDER_ID = 'abcd-efgh-jklm-nopq';
    const QUOTE_MASKED_ID = 'masked_id';
    const STORE_ID = 1;
    const QUOTE_ID = 1001;
    const PARENT_QUOTE_ID = 1000;
    const PRODUCT_ID = 20102;
    const PRODUCT_PRICE = 100;
    const ORDER_ID = 456;
    const ORDER_INCREMENT_ID = '100010001';
    const CACHE_IDENTIFIER = 'de6571d30123102e4a49a9483881a05f';
    const PRODUCT_SKU = 'TestProduct';

    /**
     * Mock context
     *
     * @var \Magento\Framework\App\Action\Context|PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    /**
     * Mock logger
     *
     * @var \Psr\Log\LoggerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * Mock klarnaOrderFactoryInstance
     *
     * @var \Klarna\Core\Model\Order|PHPUnit_Framework_MockObject_MockObject
     */
    private $klarnaOrderFactoryInstance;

    /**
     * Mock klarnaOrderFactory
     *
     * @var \Klarna\Core\Model\OrderFactory|PHPUnit_Framework_MockObject_MockObject
     */
    private $klarnaOrderFactory;

    /**
     * Mock klarnaOrderRepository
     *
     * @var \Klarna\Core\Api\OrderRepositoryInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $klarnaOrderRepository;

    /**
     * Mock cartRepository
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $cartRepository;

    /**
     * Mock orderManagement
     *
     * @var \Klarna\Ordermanagement\Model\Api\Ordermanagement|PHPUnit_Framework_MockObject_MockObject
     */
    private $orderManagement;

    /**
     * Mock storeManager
     *
     * @var \Magento\Store\Model\StoreManagerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $storeManager;

    /**
     * Mock quoteIdMaskFactoryInstance
     *
     * @var \Magento\Quote\Model\QuoteIdMask|PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteIdMaskFactoryInstance;

    /**
     * Mock quoteIdMaskFactory
     *
     * @var \Magento\Quote\Model\QuoteIdMaskFactory|PHPUnit_Framework_MockObject_MockObject
     */
    private $quoteIdMaskFactory;

    /**
     * Mock mageOrderRepository
     *
     * @var \Magento\Sales\Api\OrderRepositoryInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $mageOrderRepository;

    /**
     * Mock addressDataTransform
     *
     * @var \Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address|PHPUnit_Framework_MockObject_MockObject
     */
    private $addressDataTransform;

    /**
     * Mock customerRepository
     *
     * @var \Magento\Customer\Api\CustomerRepositoryInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $customerRepository;

    /**
     * Mock customerFactoryInstance
     *
     * @var \Magento\Customer\Model\Customer|PHPUnit_Framework_MockObject_MockObject
     */
    private $customerFactoryInstance;

    /**
     * Mock customerFactory
     *
     * @var \Magento\Customer\Model\CustomerFactory|PHPUnit_Framework_MockObject_MockObject
     */
    private $customerFactory;

    /**
     * Mock helper
     *
     * @var \Kodbruket\VsfKco\Helper\Data|PHPUnit_Framework_MockObject_MockObject
     */
    private $helper;

    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Object to test
     *
     * @var \Kodbruket\VsfKco\Controller\Order\Push
     */
    private $testObject;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $requestObj;

    /**
     * Main set up method
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->context = $this->createMock(\Magento\Framework\App\Action\Context::class);
        $this->logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->klarnaOrderFactoryInstance = $this->createMock(\Klarna\Core\Model\Order::class);
        $this->klarnaOrderFactory = $this->getMockupFactory(\Klarna\Core\Model\Order::class);
        $this->klarnaOrderFactory->method('create')->willReturn($this->klarnaOrderFactoryInstance);
        $this->klarnaOrderRepository = $this->createMock(\Klarna\Core\Api\OrderRepositoryInterface::class);
        $this->cartRepository = $this->createMock(\Magento\Quote\Api\CartRepositoryInterface::class);
        $this->orderManagement = $this->createMock(\Klarna\Ordermanagement\Model\Api\Ordermanagement::class);
        $this->storeManager = $this->createMock(\Magento\Store\Model\StoreManagerInterface::class);
        $this->quoteIdMaskFactoryInstance = $this->createMock(\Magento\Quote\Model\QuoteIdMask::class);
        $this->quoteIdMaskFactory = $this->getMockupFactory(\Magento\Quote\Model\QuoteIdMask::class);
        $this->quoteIdMaskFactory->method('create')->willReturn($this->quoteIdMaskFactoryInstance);
        $this->mageOrderRepository = $this->createMock(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->addressDataTransform = $this->createMock(\Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address::class);
        $this->customerRepository = $this->createMock(\Magento\Customer\Api\CustomerRepositoryInterface::class);
        $this->customerFactoryInstance = $this->createMock(\Magento\Customer\Model\Customer::class);
        $this->customerFactory = $this->getMockupFactory(\Magento\Customer\Model\Customer::class);
        $this->customerFactory->method('create')->willReturn($this->customerFactoryInstance);
        $this->helper = $this->createMock(\Kodbruket\VsfKco\Helper\Data::class);

        // Prepare some data
        $this->requestObj = $this->createMock(\Magento\Framework\App\RequestInterface::class);
        $this->context->expects($this->any())
            ->method('getRequest')
            ->willReturn($this->requestObj);

        $this->testObject = $this->objectManager->getObject(
        \Kodbruket\VsfKco\Controller\Order\Push::class,
            [
                'context' => $this->context,
                'logger' => $this->logger,
                'klarnaOrderFactory' => $this->klarnaOrderFactory,
                'klarnaOrderRepository' => $this->klarnaOrderRepository,
                'cartRepository' => $this->cartRepository,
                'orderManagement' => $this->orderManagement,
                'storeManager' => $this->storeManager,
                'quoteIdMaskFactory' => $this->quoteIdMaskFactory,
                'mageOrderRepository' => $this->mageOrderRepository,
                'addressDataTransform' => $this->addressDataTransform,
                'customerRepository' => $this->customerRepository,
                'customerFactory' => $this->customerFactory,
                'helper' => $this->helper,
            ]
        );
    }

    /**
     * @return array
     */
    public function dataProviderForTestExecute()
    {
        return [
            'Case Already Acknowledged' => [
                'prerequisites' => [
                    'is_acknowledged' => true
                ],
                'expectedResult' => NULL
            ],
            'Case New' => [
                'prerequisites' => [
                    'is_acknowledged' => false
                ],
                'expectedResult' => NULL
            ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestExecute
     */
    public function testExecute(array $prerequisites, $expectedResult)
    {
        $klanaOrderObj = new DataObject([
            'is_acknowledged' => $prerequisites['is_acknowledged']
        ]);
        $placedKlarnaOrder = new DataObject([
            'merchant_reference2' => self::QUOTE_MASKED_ID
        ]);
        $quoteIdMask = new DataObject([
            'quote_id' => self::QUOTE_ID
        ]);

        $quote = $this->getQuoteMock($this->getBillingAddress(), $this->getShippingAddress());

        $this->requestObj->expects($this->any())
            ->method('getParam')
            ->with('id')
            ->willReturn(self::KLARNA_ORDER_ID);

        $this->klarnaOrderRepository->expects($this->any())
            ->method('getByKlarnaOrderId')
            ->with(self::KLARNA_ORDER_ID)
            ->willReturn($klanaOrderObj);

        $this->orderManagement->expects($this->any())
            ->method('getPlacedKlarnaOrder')
            ->with(self::KLARNA_ORDER_ID)
            ->willReturn($placedKlarnaOrder);

        $this->quoteIdMaskFactoryInstance->expects($this->any())
            ->method('load')
            ->with(self::QUOTE_MASKED_ID, 'masked_id')
            ->willReturn($quoteIdMask);

        $this->cartRepository->expects($this->any())
            ->method('get')
            ->with(self::QUOTE_ID)
            ->willReturn($quote);

        $result = $this->testObject->execute();
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testCreateCsrfValidationException()
    {
        $this->assertNull($this->testObject->createCsrfValidationException($this->requestObj));
    }

    /**
     * @return void
     */
    public function testValidateForCsrf()
    {
        $this->assertTrue($this->testObject->validateForCsrf($this->requestObj));
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
