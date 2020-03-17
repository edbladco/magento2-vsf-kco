<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Model\OrderFactory;

use Kodbruket\VsfKco\Model\ExtensionConstants;
use Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;

use Magento\Framework\DataObject;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use Psr\Log\LoggerInterface;

class AddressUpdate extends AbstractController
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var Address
     */
    private $addressDataTransform;

    /**
     * @var OrderRepositoryInterface
     */
    private $klarnaOrderRepository;

    /**
     * @var CustomerFactory
     */
    private $customerFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Constructor
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteRepository $quoteRepository
     * @param Address $addressDataTransform
     * @param CustomerFactory $customerFactory
     * @param OrderRepositoryInterface $klarnaOrderRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     *
     * @return void
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        CartRepositoryInterface $cartRepository,
        QuoteRepository $quoteRepository,
        Address $addressDataTransform,
        CustomerFactory $customerFactory,
        OrderRepositoryInterface $klarnaOrderRepository,
        CustomerRepositoryInterface $customerRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct(
            $context,
            $logger,
            $quoteIdMaskFactory
        );

        $this->logger = $logger;
        $this->cartRepository = $cartRepository;
        $this->quoteRepository = $quoteRepository;
        $this->addressDataTransform = $addressDataTransform;
        $this->klarnaOrderRepository = $klarnaOrderRepository;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     *
     * @throws \Magento\Framework\Exception\NotFoundException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $httpResponseCode = 200;
        $shippingMethodCode = null;
        $shippingDescription = "Shipping";

        /**
         * 1. Get quote
         */
        try {
            $data = $this->getKlarnaRequestData();
            $quoteId = $this->getQuoteId();
            $quote = $this->quoteRepository->get($quoteId);
        } catch (NoSuchEntityException $e) {
            $data = [
                'error_type' => 'approval_failed',
                'error_text' => 'The cart could not be found.'
            ];
            $httpResponseCode = 400;
        } catch (\Exception $e) {
            $data = [
                'error_type' => 'approval_failed',
                'error_text' => $e->getMessage()
            ];
            $httpResponseCode = 400;
        }

        if (isset($quote)) {
            /**
             * 2. Update address
             */
            $this->updateOrderAddresses($data, $quote);
            $namePrefix = "";

            /**
             * 3. Update shipping method
             */
            try {
                $selectedShippingMethod = $data->getData('selected_shipping_option');

                if ($selectedShippingMethod) {
                    $shippingMethodString = json_encode($selectedShippingMethod, JSON_UNESCAPED_UNICODE);
                    $quote->setExtShippingInfo($shippingMethodString);

                    if (
                        !array_key_exists('delivery_details', $selectedShippingMethod) ||
                        !(
                            array_key_exists('carrier', $selectedShippingMethod['delivery_details'])
                            && array_key_exists('class', $selectedShippingMethod['delivery_details'])
                        )
                    ) {
                        $shippingMethodCode = $selectedShippingMethod['id'];
                    } else {
                        $shippingMethodCode = $this->getShippingFromKSSCarrierClass($selectedShippingMethod['delivery_details']['carrier'].'_'.$selectedShippingMethod['delivery_details']['class']);
                    }

                    $shippingDescription = $selectedShippingMethod['name'];
                } else {
                    if ($shippingMethod = $this->getShippingMedthodFromOrderLines($data)) {
                        $shippingMethodCode = $shippingMethod['reference'];
                    }
                }

                if (isset($shippingMethodCode) && $shippingMethodCode !== false) {
                    $shippingMethodCode = $this->convertShippingMethodCode($shippingMethodCode);

                    $quote->getShippingAddress()
                        ->setShippingMethod($shippingMethodCode)
                        ->setShippingDescription($shippingDescription)
                        ->setCollectShippingRates(true)
                        ->collectShippingRates();

                    if (!$quote->getShippingAddress()->getShippingMethod()) {
                        throw new \Exception('The selected shipping method is not available.');
                    }
                } else {
                    throw new \Exception('The selected shipping method is not available.');
                }

                $quote->setTotalsCollectedFlag(false)
                    ->collectTotals()
                    ->save();

                $orderLines = $this->getOrderLinesWithoutShippingFee($data);

                $unitPrice = intval($quote->getShippingAddress()->getShippingInclTax() * 100);
                $totalAmount = intval($quote->getShippingAddress()->getShippingInclTax() * 100);
                $totalTaxAmount = intval($quote->getShippingAddress()->getShippingTaxAmount() * 100);
                $taxRate = $totalTaxAmount ? intval($totalTaxAmount / ($totalAmount - $totalTaxAmount) * 100 * 100) : 0;

                $orderLines[] = [
                    'type' => 'shipping_fee',
                    'name' => $namePrefix . $quote->getShippingAddress()->getShippingDescription(),
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'total_amount' =>  $totalAmount,
                    'total_tax_amount' => $totalTaxAmount
                ];

                $data->setOrderLines(array_values($orderLines));
                $data->setOrderAmount(intval(round($quote->getShippingAddress()->getGrandTotal() * 100)));
                $data->setOrderTaxAmount(intval(round($quote->getShippingAddress()->getTaxAmount() * 100)));
            } catch (\Exception $e) {
                $data = [
                    'error_type' => 'approval_failed',
                    'error_text' => $e->getMessage()
                ];
                $httpResponseCode = 400;
                $this->logger->info($e->getMessage());
            }
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)
            ->setData($data)
            ->setHttpResponseCode($httpResponseCode);
    }

    /**
     * Get order lines without shipping fee
     *
     * @param array $data
     *
     * @return array
     */
    public function getOrderLinesWithoutShippingFee($data)
    {
        $orderLines = $data->getOrderLines();

        foreach ($orderLines as $key => $orderLine) {
            if (array_key_exists('type', $orderLine) && $orderLine['type'] == 'shipping_fee') {
                unset($orderLines[$key]);
            }
        }

        return $orderLines;
    }

    /**
     * Convert shipping method code
     *
     * Makes sure shipping method code is delivered in the format
     * of carrier and method.
     *
     * @param string $shippingCode
     *
     * @return string
     */
    private function convertShippingMethodCode($shippingCode)
    {
        if (!strpos($shippingCode, '_')) {
            return $shippingCode . '_' . $shippingCode;
        }

        return $shippingCode;
    }

    /**
     * Get shipping from Klarna Shipping Service carrier class
     *
     * @param string $carrierClass
     *
     * @return bool|string
     */
    private function getShippingFromKSSCarrierClass($carrierClass) {
        $store = $this->storeManager->getStore();
        $mappings = $this->scopeConfig->getValue('klarna/vsf/carrier_mapping', ScopeInterface::SCOPE_STORES, $store);

        if ($mappings) {
            $mappings = json_decode($mappings, true);

            foreach ($mappings as $item) {
                if ($item['kss_carrier'] == $carrierClass) {
                    return $item['shipping_method'];
                }
            }
        }

        return false;
    }

    /**
     * Get shipping method from order lines
     *
     * @param DataObject $checkoutData
     *
     * @return bool|array
     */
    private function getShippingMedthodFromOrderLines(DataObject $checkoutData)
    {
        $orderLines = $checkoutData->getData('order_lines');

        if (is_array($orderLines)) {
            foreach ($orderLines as $line) {
                if (isset($line['type']) && $line['reference'] && $line['type'] === 'shipping_fee') {
                    return $line;
                }
            }
        }

        return false;
    }

    /**
     * Update order address
     *
     * @param DataObject $checkoutData
     * @param \Magento\Quote\Model\Quote|CartInterface $quote
     *
     * @return void
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updateOrderAddresses(DataObject $checkoutData, CartInterface $quote)
    {
        if (!$checkoutData->hasBillingAddress() && !$checkoutData->hasShippingAddress()) {
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

        /**
         * @todo  check use 'Billing as shipping'
         */
        if ($checkoutData->hasShippingAddress()) {
            $quote->setTotalsCollectedFlag(false);
            $quote->getShippingAddress()->addData(
                $this->addressDataTransform->prepareMagentoAddress($shippingAddress)
            );
        }
    }
}
