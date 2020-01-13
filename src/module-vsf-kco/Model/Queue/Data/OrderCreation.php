<?php
namespace Kodbruket\VsfKco\Model\Queue\Data;

use Kodbruket\VsfKco\Api\Data\Queue\OrderCreationInterface;

class OrderCreation implements OrderCreationInterface
{
    /**
     * @var string
     */
    private $klarnaOrderId;

    /**
     * @var \Magento\Quote\Api\Data\CartInterface
     */
    private $quote;

    /**
     * @return void
     * @param string $data
     */
    public function setKlarnaOrderId(string $data){
        $this->klarnaOrderId = $data;
    }

    /**
     * @return string
     */
    public function getKlarnaOrderId(){
        return $this->klarnaOrderId;
    }

    /**
     * @return void
     * @param \Magento\Quote\Api\Data\CartInterface $data
     */
    public function setQuote(\Magento\Quote\Api\Data\CartInterface $data){
        $this->quote = $data;
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function getQuote(){
        return $this->quote;
    }
}
