<?php
namespace Kodbruket\VsfKco\Helper;

use Exception;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Translate\Inline\StateInterface as InlineTranslateStateInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

class Email extends AbstractHelper
{
    const EMAIL_TEMPLATE_IDENTIFIER = 'vsfkco_to_admin_order_cancelled';
    const XML_PATH_SENDER_EMAIL = 'contact/email/sender_email_identity';
    const XML_PATH_RECIPIENT_EMAIL = 'klarna/cancel/report_email';

    /**
     * @var InlineTranslateStateInterface
     */
    protected $inlineTranslation;

    /**
     * @var InlineTranslateStateInterface
     */
    protected $transportBuilder;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var State
     */
    private $appState;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param InlineTranslateStateInterface $inlineTranslation
     * @param TransportBuilder $transportBuilder
     * @param LoggerInterface $logger
     * @param State $appState
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        InlineTranslateStateInterface $inlineTranslation,
        TransportBuilder $transportBuilder,
        LoggerInterface $logger,
        State $appState
    )
    {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->inlineTranslation = $inlineTranslation;
        $this->transportBuilder = $transportBuilder;
        $this->logger = $logger;
        $this->appState = $appState;
    }

    /**
     * @param $klarnaOrderId
     * @param string $message
     * @param Exception|null $exception
     * @return $this
     */
    public function sendOrderCancelEmail($klarnaOrderId, $magentoOrderId = '', $message = '', Exception $exception = null)
    {
        $adminEmail = $this->scopeConfig->getValue(self::XML_PATH_RECIPIENT_EMAIL, ScopeInterface::SCOPE_STORE);

        if(empty($adminEmail)){
            return $this;
        }

        try{
            $this->appState->emulateAreaCode(Area::AREA_FRONTEND, function() use ($klarnaOrderId, $magentoOrderId, $message, $exception, $adminEmail){
                $this->inlineTranslation->suspend();
                $this->transportBuilder
                    ->setTemplateIdentifier(self::EMAIL_TEMPLATE_IDENTIFIER)
                    ->setTemplateOptions([
                        'area' => Area::AREA_FRONTEND,
                        'store' => Store::DEFAULT_STORE_ID,
                    ])
                    ->setTemplateVars([
                        'klarnaOrderId' => $klarnaOrderId,
                        'magentoOrderId' => $magentoOrderId,
                        'message' => $message,
                        'traceString' => $exception ? $exception->getTraceAsString() : ''
                    ])
                    ->setFrom($this->scopeConfig->getValue(self::XML_PATH_SENDER_EMAIL, ScopeInterface::SCOPE_STORE))
                    ->addTo($adminEmail);
                $transport = $this->transportBuilder->getTransport();
                $transport->sendMessage();
                $this->inlineTranslation->resume();
            });
        }
        catch(Exception $e){
            $this->logger->critical('Error during send order failure email: ' . $e->getMessage());
        }
        return $this;
    }
}
