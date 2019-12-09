<?php
namespace Kodbruket\VsfKco\Test\Unit\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Kodbruket\VsfKco\Helper\Email as EmailHelper;

/**
 * @covers EmailHelper
 */
class EmailTest extends TestCase
{
    /**
     * Mock context
     *
     * @var \Magento\Framework\App\Helper\Context|PHPUnit_Framework_MockObject_MockObject
     */
    private $context;

    /**
     * Mock scopeConfig
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $scopeConfig;

    /**
     * Mock inlineTranslation
     *
     * @var \Magento\Framework\Translate\Inline\StateInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $inlineTranslation;

    /**
     * Mock transportBuilder
     *
     * @var \Magento\Framework\Mail\Template\TransportBuilder|PHPUnit_Framework_MockObject_MockObject
     */
    private $transportBuilder;

    /**
     * Mock logger
     *
     * @var \Psr\Log\LoggerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * Mock appState
     *
     * @var \Magento\Framework\App\State|PHPUnit_Framework_MockObject_MockObject
     */
    private $appState;

    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Object to test
     *
     * @var EmailHelper
     */
    private $_helper;

    /**
     * Main set up method
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->context = $this->createMock(\Magento\Framework\App\Helper\Context::class);
        $this->scopeConfig = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->inlineTranslation = $this->createMock(\Magento\Framework\Translate\Inline\StateInterface::class);
        $this->transportBuilder = $this->createMock(\Magento\Framework\Mail\Template\TransportBuilder::class);
        $this->logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->appState = $this->createMock(\Magento\Framework\App\State::class);
        $this->_helper = $this->objectManager->getObject(
        EmailHelper::class,
            [
                'context' => $this->context,
                'scopeConfig' => $this->scopeConfig,
                'inlineTranslation' => $this->inlineTranslation,
                'transportBuilder' => $this->transportBuilder,
                'logger' => $this->logger,
                'appState' => $this->appState,
            ]
        );
    }

    /**
     * @return array
     */
    public function dataProviderForTestSendOrderCancelEmail()
    {
        $klarnaOrderId = '123';
        $magentoOrderId = '456';
        $message = '';
        $exception = null;

        $this->setUp();

        return [
            'Case empty recipient email' => [
                'prerequisites' => [
                    'recipientEmail' => '',
                    'klarnaOrderId' => $klarnaOrderId,
                    'magentoOrderId' => $magentoOrderId,
                    'message' => $message,
                    'exception' => $exception
                ],
                'expectedResult' => $this->_helper
            ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestSendOrderCancelEmail
     */
    public function testSendOrderCancelEmail(array $prerequisites, $expectedResult)
    {
        $this->scopeConfig->expects($this->any())
            ->method('isSetFlag')
            ->with(EmailHelper::XML_PATH_RECIPIENT_EMAIL, ScopeInterface::SCOPE_STORES)
            ->willReturn($prerequisites['recipientEmail']);

        $result = $this->_helper->sendOrderCancelEmail(
            $prerequisites['klarnaOrderId'],
            $prerequisites['magentoOrderId'],
            $prerequisites['message'],
            $prerequisites['exception']
        );

        $this->assertEquals($expectedResult, $result);
    }
}
