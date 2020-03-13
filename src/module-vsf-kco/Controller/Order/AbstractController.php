<?php

namespace Kodbruket\VsfKco\Controller\Order;

use Magento\Framework\DataObject;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Quote\Model\QuoteIdMaskFactory;

use Psr\Log\LoggerInterface;

abstract class AbstractController extends Action implements CsrfAwareActionInterface
{
    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var Context
     */
    public $context;

    /**
     * @var DataObject
     */
    public $klarnaRequestData;

    /**
     * Constructor
     * 
     * @param Context $context
     * @param LoggerInterface $logger
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * 
     * @return void
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        parent::__construct(
            $context
        );

        $this->context = $context;
        $this->logger = $logger;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Get Klarna request data
     *
     * @return DataObject
     */
    public function getKlarnaRequestData()
    {
        if (null === $this->klarnaRequestData) {
            /** @var \Magento\Framework\App\Request\Http $request */
            $request = $this->getRequest();

            if (!$request->getContent()) {
                throw new \Exception();
            }

            $this->klarnaRequestData = new DataObject(
                json_decode($request->getContent(), true)
            );
        }

        return $this->klarnaRequestData;
    }

    /**
     * Get quote ID
     *
     * @return int
     */
    public function getQuoteId()
    {
        if (!$this->klarnaRequestData) {
            $this->getKlarnaRequestData();
        }

        $mask = $this->klarnaRequestData->getData(
            'merchant_reference2'
        );

        /** @var $quoteIdMask QuoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($mask, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        if ((int) $quoteId == 0 && ctype_digit(strval($mask))) {
            $quoteId = (int) $mask;
        }

        return $quoteId;
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
