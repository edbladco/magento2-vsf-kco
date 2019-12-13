<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Helper\ConfigHelper;
use Klarna\Core\Model\OrderFactory;
use Klarna\Ordermanagement\Api\ApiInterface;
use Klarna\Ordermanagement\Model\Api\Ordermanagement;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Kodbruket\VsfKco\Helper\Order as OrderHelper;
use Kodbruket\VsfKco\Helper\Data as VsfKcoHelper;

class Confirmation extends Action implements CsrfAwareActionInterface
{
    const EVENT_NAME = 'Confirmation';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderFactory
     */
    private $klarnaOrderFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $klarnaOrderRepository;

    /**
     * @var QuoteManagement
     */
    private $quoteManagement;


    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ApiInterface
     */
    private $orderManagement;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var VsfKcoHelper
     */
    private $helper;

    /**
     * Push constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param OrderFactory $klarnaOrderFactory
     * @param OrderRepositoryInterface $klarnaOrderRepository
     * @param QuoteManagement $quoteManagement
     * @param CartRepositoryInterface $cartRepository
     * @param Ordermanagement $orderManagement
     * @param StoreManagerInterface $storeManager
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigHelper $configHelper
     * @param OrderHelper $orderHelper
     * @param VsfKcoHelper $helper
     */

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        OrderFactory $klarnaOrderFactory,
        OrderRepositoryInterface $klarnaOrderRepository,
        QuoteManagement $quoteManagement,
        CartRepositoryInterface $cartRepository,
        Ordermanagement $orderManagement,
        StoreManagerInterface $storeManager,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ScopeConfigInterface $scopeConfig,
        ConfigHelper $configHelper,
        OrderHelper $orderHelper,
        VsfKcoHelper $helper
    ) {
        $this->logger = $logger;
        $this->klarnaOrderFactory = $klarnaOrderFactory;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->quoteManagement = $quoteManagement;
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->storeManager = $storeManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->scopeConfig = $scopeConfig;
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->helper = $helper;

        parent::__construct(
            $context
        );
    }

    public function execute()
    {
        $klarnaOrderId = $this->getRequest()->getParam('id');

        $this->logger->info('Confirmation for Klarna order ID: ' . $klarnaOrderId);

        $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, 'Confirmation for Klarna order ID: ' . $klarnaOrderId);

        $store = $this->storeManager->getStore();

        $resultRedirect = $this->resultRedirectFactory->create();

        $successUrl = $this->scopeConfig->getValue('klarna/vsf/successful_link', ScopeInterface::SCOPE_STORES, $store);

        $failedUrl = $this->scopeConfig->getValue('klarna/vsf/failed_link', ScopeInterface::SCOPE_STORES, $store);

        if (!$klarnaOrderId) {
            $this->logger->info('Klarna order ID is required for confirmation.');
            $resultRedirect->setUrl($failedUrl);
            return $resultRedirect;
        }

        $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);

        if ($klarnaOrder->getOrderId()) {
            $this->logger->info('Order already exists in Magento for Klarna order ID: ' . $klarnaOrder->getOrderId());
            $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, $klarnaOrder->getOrderId(), 'Order already exists in Magento for Klarna order ID: ' . $klarnaOrder->getOrderId());
            $resultRedirect->setUrl($successUrl.'?sid='.$klarnaOrderId);
            return $resultRedirect;
        }

        if ($klarnaOrder->getIsAcknowledged()) {
            $this->logger->info('Klarna order ID ' . $klarnaOrderId . ' has already been acknowledged.');
            $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, 'Klarna order ID ' . $klarnaOrderId . ' has already been acknowledged.');
            $resultRedirect->setUrl($successUrl.'?sid='.$klarnaOrderId);
            return $resultRedirect;
        }

        $this->orderManagement->resetForStore($store, ConfigHelper::KCO_METHOD_CODE);

        $placedKlarnaOrder = $this->orderManagement->getPlacedKlarnaOrder($klarnaOrderId);

        $maskedId = $placedKlarnaOrder->getDataByKey('merchant_reference2');

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');

        $quoteId = $quoteIdMask->getQuoteId();

        if( (int)$quoteId==0 && ctype_digit(strval($maskedId))){
            $quoteId = (int)$maskedId;
        }
        $quote = $this->cartRepository->get($quoteId);

        $store = $quote->getStoreId();

        $user = $this->configHelper->getApiConfig('merchant_id', $store);

        $password = $this->configHelper->getApiConfig('shared_secret', $store);

        $testMode = $this->configHelper->isApiConfigFlag('test_mode', $store);

        if ($testMode) {
            $url = 'https://api.playground.klarna.com/checkout/v3/orders/';
        } else {
            $url = 'https://api.klarna.com/checkout/v3/orders/';
        }

        $auth = base64_encode(sprintf("%s:%s", $user, $password));

        $context = stream_context_create([
            "http" => [
                "header" => "Authorization: Basic $auth"
            ]
        ]);
        $result = file_get_contents(
            $url . $klarnaOrderId,
            false,
            $context
        );

        // $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, "Klarna API Call: " . $url, $result);

        $kco = json_decode($result, true);

        if ($shippingMethod = $kco['selected_shipping_option']) {
            $shippingMethodString = json_encode($shippingMethod, JSON_UNESCAPED_UNICODE);

            if ($quote->getExtShippingInfo() != $shippingMethodString) {
                $quote->setExtShippingInfo($shippingMethodString);
                $quote->save();
            }
        }

        if ($quote->getId()) {
            try {
                /**
                 * Just redirect, we will handle submit quote in Push Controller
                 * @see Push
                 */
                $quote->setIsActive(false);
                $this->cartRepository->save($quote);
                $resultRedirect->setUrl($successUrl.'?sid='.$klarnaOrderId);
                $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, $klarnaOrder->getOrderId(), 'Reached to Confirmation page, disabled quote and redirect successfully ' , 'Redirect Url: '. $successUrl.'?sid='.$klarnaOrderId);
                return $resultRedirect;

            } catch (\Exception $exception) {
                $message = 'Redirect in Confirmation error ('.$quote->getId().')' . $exception->getMessage();
                $this->orderHelper->cancel($klarnaOrderId, false, $message, $exception);
                $this->logger->critical($message);
                $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrder, false, $message, $exception->getTraceAsString());
            }
        }

        $resultRedirect->setUrl($failedUrl);

        return $resultRedirect;
    }

    /**
     * Create CSRF validation exception
     *
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate for CSRF
     *
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
