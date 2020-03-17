<?php
namespace Kodbruket\VsfKco\Controller\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Klarna\Core\Model\OrderFactory;

use Kodbruket\VsfKco\Model\ExtensionConstants;
use Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
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

use Psr\Log\LoggerInterface;

class ChangeCountry extends AbstractController
{
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * Validate constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param QuoteRepository $quoteRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        QuoteRepository $quoteRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        parent::__construct(
            $context,
            $logger,
            $quoteIdMaskFactory
        );

        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $httpResponseCode = 200;

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

        $this->logger->info("Change country:\n" . var_export($data, true));

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)
            ->setData($data)
            ->setHttpResponseCode($httpResponseCode);
    }
}
