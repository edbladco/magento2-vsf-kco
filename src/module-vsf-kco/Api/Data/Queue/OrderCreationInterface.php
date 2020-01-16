<?php
namespace Kodbruket\VsfKco\Api\Data\Queue;

interface OrderCreationInterface
{
    /**
     * @return void
     * @param string $data
     */
    public function setKlarnaOrderId(string $data);

    /**
     * @return string
     */
    public function getKlarnaOrderId();

    /**
     * @return void
     * @param \Magento\Quote\Api\Data\CartInterface $data
     */
    public function setQuote(\Magento\Quote\Api\Data\CartInterface $data);

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function getQuote();
}
