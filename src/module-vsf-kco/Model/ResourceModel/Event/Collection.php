<?php
namespace Kodbruket\VsfKco\Model\ResourceModel\Event;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected $_idFieldName = 'event_id';
    protected $_eventPrefix = 'kodbruket_vsfKco_event_collection';
    protected $_eventObject = 'event_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Kodbruket\VsfKco\Model\Event::class, \Kodbruket\VsfKco\Model\ResourceModel\Event::class);
    }

}
