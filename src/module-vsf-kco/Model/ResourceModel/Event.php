<?php
namespace Kodbruket\VsfKco\Model\ResourceModel;

class Event extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    )
    {
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('vsf_kco_events', 'event_id');
    }
}
