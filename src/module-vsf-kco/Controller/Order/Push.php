<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Helper\ConfigHelper;
use Klarna\Core\Model\OrderFactory;
use Klarna\Ordermanagement\Api\ApiInterface;
use Klarna\Ordermanagement\Model\Api\Ordermanagement;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface as MageOrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartInterface;
use Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address as AddressDataTransform;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Kodbruket\VsfKco\Helper\Order as OrderHelper;
use Kodbruket\VsfKco\Helper\Data as VsfKcoHelper;
use Kodbruket\VsfKco\Model\ExtensionConstants;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Class Push
 * @package Kodbruket\VsfKco\Controller\Order
 */
class Push extends Action implements CsrfAwareActionInterface
{
    const EVENT_NAME = 'Push';

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
     * @var MageOrderRepositoryInterface
     */
    private $mageOrderRepository;

    /**
     * @var AddressDataTransform
     */
    private $addressDataTransform;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

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
     * @param MageOrderRepositoryInterface $mageOrderRepository
     * @param AddressDataTransform $addressDataTransform
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerFactory $customerFactory
     * @param OrderHelper $orderHelper
     * @param EmailHelper $emailHelper
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
        MageOrderRepositoryInterface $mageOrderRepository,
        AddressDataTransform $addressDataTransform,
        CustomerRepositoryInterface $customerRepository,
        CustomerFactory $customerFactory,
        OrderHelper $orderHelper,
        VsfKcoHelper $helper,
        ProductRepositoryInterface $productRepository
    ) {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->klarnaOrderFactory = $klarnaOrderFactory;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->quoteManagement = $quoteManagement;
        $this->cartRepository = $cartRepository;
        $this->orderManagement = $orderManagement;
        $this->storeManager = $storeManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->mageOrderRepository = $mageOrderRepository;
        $this->addressDataTransform = $addressDataTransform;
        $this->customerRepository   = $customerRepository;
        $this->customerFactory      = $customerFactory;
        $this->orderHelper = $orderHelper;
        $this->helper = $helper;
        parent::__construct(
            $context
        );
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     * @throws \Klarna\Core\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $klarnaOrderId = $this->getRequest()->getParam('id');
$o = $this->getRequest()->getParam('o');
$q = $this->getRequest()->getParam('q');
if ($klarnaOrderId && $o && $q) {
$h = $this->acknowledgeOrder($klarnaOrderId, $o, $q);
var_dump($h);
die('ok');
return 'ok';
}

        $this->logger->info('Pushing Klarna Order Id: ' . $klarnaOrderId);


$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://outlook.office.com/webhook/bcd7ec5d-e10c-4350-9d27-96a45c486798@417e3258-5eb1-4df5-90b9-6578bf933f20/IncomingWebhook/2236e15ea7f646108c2423250256d951/57396f4f-3f55-4c6f-aa05-edd18cf8b1d8');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"text\": \"Push request: $klarnaOrderId\"}");

$headers = array();
$headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
/*
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
*/
curl_close($ch);

        $store = $this->storeManager->getStore();

        if (!$klarnaOrderId) {
            echo 'Klarna Order ID is required';
            return;
        }
        $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, 'Pushing Klarna Order Id: ' . $klarnaOrderId);
        $klarnaOrder = $this->klarnaOrderRepository->getByKlarnaOrderId($klarnaOrderId);

        if ($klarnaOrder->getIsAcknowledged()) {
            $message = 'Error: Order ' . $klarnaOrderId . ' has been acknowledged';
            $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, $message);
            return;
        }

        $this->orderManagement->resetForStore($store, ConfigHelper::KCO_METHOD_CODE);

        $placedKlarnaOrder = $this->orderManagement->getPlacedKlarnaOrder($klarnaOrderId);

        $maskedId = $placedKlarnaOrder->getDataByKey('merchant_reference2');

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');

        $quoteId = $quoteIdMask->getQuoteId();

        if( (int)$quoteId == 0 && ctype_digit(strval($maskedId)) ){
            $quoteId = (int) $maskedId;
        }

        $quote = $this->cartRepository->get($quoteId);
/*
        if ($klarnaOrderId == '7a1950fa-3b06-65c8-8b82-1cf49fa5cf12') {
            $itemsToAdd = [];
            $itemsToAdd[] = $this->productRepository->get('109364');
            $itemsToAdd[] = $this->productRepository->get('115465__18837');
echo $quote->getId();
$itemStatus = '';
            try {
                foreach ($itemsToAdd as $itemToAdd) {
                    echo $itemToAdd->getName() . "\n\n";
$itemStatus = $itemToAdd->getName();
                    $quote->addProduct($itemToAdd, 1);
                }
            } catch (\Exception $e) {
                echo $e->getMessage();
var_dump($itemStatus);
            }
$quote->getShippingAddress()->setShippingMethod('udc_7e424e05-cb57-447d-83d3-91973edb70da');
$quote->save();
        }*/

        if (!$quote->getId()) {
            $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, 'Quote is not existed in Magento');
        }

            $quote->setData(ExtensionConstants::FORCE_ORDER_PLACE, true);
            $quote->getShippingAddress()->setPaymentMethod(\Klarna\Kp\Model\Payment\Kp::METHOD_CODE);
            $payment = $quote->getPayment();
            $payment->importData(['method' => \Klarna\Kp\Model\Payment\Kp::METHOD_CODE]);
            $payment->setAdditionalInformation(ExtensionConstants::FORCE_ORDER_PLACE, true);
            $payment->setAdditionalInformation(ExtensionConstants::KLARNA_ORDER_ID, $klarnaOrderId);
$this->cartRepository->save($quote);

try {

        /**
         *  Update shipping/billing address for quote.
         */
        $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, 'Start Updating Order Address From Pushing Klarna',  'Quote ID: ' . $quote->getId());
        $this->updateOrderAddresses($placedKlarnaOrder, $quote);
        $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, null, 'End Order Address Update From Pushing Klarna',  'Quote ID: ' . $quote->getId());

        /**
         * Create order and acknowledged
         */
        $order = false;

             $order = $this->quoteManagement->submit($quote);
             $orderId = $order->getId();
             if ($orderId) {
                $klarnaOrder->setOrderId($orderId)->save();
             }
            $this->acknowledgeOrder($klarnaOrderId, $order->getId(), $quoteId);
            $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, $order->getId(), 'Magento order created in PushController with ID ' . $order->getIncrementId());
            return;
        } catch (\Exception $exception) {
            $message = 'Create order error in PushController ('.$quote->getId().')' . $exception->getMessage();
            $this->orderHelper->cancel($klarnaOrderId, $order ? $order->getId() : false, $message, $exception);
            $this->logger->critical($message);
            $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, $order ? $order->getId() : false, $message, $exception->getTraceAsString());

/*
if (count($quote->getErrors())) {
  foreach ($quote->getAllItems() as $item) {
    $itemErrors = $item->getErrorInfos();
    foreach ($itemErrors as $error) {
      $this->logger->info($error->getMessage());
    }
  }
}
*/
            return;
        }
        exit;
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
                $this->orderManagement->updateMerchantReferences($klarnaOrderId, $mageOrder->getIncrementId(), $quoteId);
                $this->orderManagement->acknowledgeOrder($klarnaOrderId);
                $klarnaOrder->setOrderId($orderId)
                    ->setIsAcknowledged(true)
                    ->save();
                $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, $orderId, 'Sent ACK successfully with Klarna ID: ' . $klarnaOrderId);
                return [$klarnaOrderId, $mageOrder->getIncrementId(), $quoteId];
            } catch (\Exception $exception) {
                $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, $orderId, 'Send ACK error: ' . $exception->getMessage(), $exception->getTraceAsString());
            }
        } else {
            $this->helper->trackEvent(self::EVENT_NAME, $klarnaOrderId, $orderId, 'Something went wrong when sending ACK');
        }
        return;
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

    /**
     * @param DataObject $checkoutData
     * @param \Magento\Quote\Model\Quote|CartInterface $quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updateOrderAddresses(DataObject $checkoutData, CartInterface $quote)
    {
        $this->logger->info('Start Updating Order Address From Pushing Klarna');

        if (!$checkoutData->hasBillingAddress() && !$checkoutData->hasShippingAddress()) {
            $this->logger->error(sprintf('Klarna order doesn\'t have billing and shipping address for quoteId %s', $quote->getId()));
            return;
        }

        $sameAsOther = $checkoutData->getShippingAddress() == $checkoutData->getBillingAddress();

        $billingAddress = new DataObject($checkoutData->getBillingAddress());

        $billingAddress->setSameAsOther($sameAsOther);

        $shippingAddress = new DataObject($checkoutData->getShippingAddress());

        $shippingAddress->setSameAsOther($sameAsOther);

        if (!$quote->getCustomerId()) {

            $websiteId = $quote->getStore()->getWebsiteId();

            $customer = $this->customerFactory->create();

            $customer->setWebsiteId($websiteId);

            $customer->loadByEmail($billingAddress->getEmail());

            if (!$customer->getEntityId()) {

                $customer->setWebsiteId($websiteId)
                    ->setStore($quote->getStore())
                    ->setFirstname($billingAddress->getGivenName())
                    ->setLastname($billingAddress->getFamilyName())
                    ->setEmail($billingAddress->getEmail())
                    ->setPassword($billingAddress->getEmail());

                $customer->save();
            }

            $customer = $this->customerRepository->getById($customer->getEntityId());

            $quote->assignCustomer($customer);
        }

        $quote->getBillingAddress()->addData(
            $this->addressDataTransform->prepareMagentoAddress($billingAddress)
        );


        $this->logger->info(sprintf('Updated Billing Address Data for QuoteId %s :', $quote->getId()).print_r($quote->getBillingAddress()->getData(),true));

        /**
         * @todo  check use 'Billing as shiiping'
         */

        if ($checkoutData->hasShippingAddress()) {

            $quote->setTotalsCollectedFlag(false);
            $quote->getShippingAddress()->addData(
                $this->addressDataTransform->prepareMagentoAddress($shippingAddress)
            );

$this->logger->info(var_export($quote->getErrors(), true));

if (count($quote->getErrors())) {

foreach ($quote->getAllItems() as $item) {
  $itemErrors = $item->getErrorInfos();
  if (count($itemErrors)) {
    foreach ($itemErrors as $error) {
      $this->logger->critical($item->getSku() . ' ' . $error['message']);
    }
  }
}

}

//            $this->logger->info(sprintf('Updated Shipping Address Data for QuoteId %s :', $quote->getId()).print_r($quote->getShippingAddress()->getData(),true));
        }

        $this->logger->info('End Updating Order Address From Pushing Klarna for QuoteId ' . $quote->getId());
    }
}
