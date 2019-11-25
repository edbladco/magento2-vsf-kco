<?php
namespace Kodbruket\VsfKco\Model;

class Event extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{
    const CACHE_TAG = 'vsfKco_event';

    protected $_eventPrefix = 'kodbruket_vsfKco_event';

    protected function _construct()
    {
        $this->_init(\Kodbruket\VsfKco\Model\ResourceModel\Event::class);
    }

    /**
     * Get identities
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
