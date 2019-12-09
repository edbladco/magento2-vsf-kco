<?php

namespace Kodbruket\VsfKco\Test;

trait TestTraits{
    private function getMockupFactory($instanceName)
    {
        /** Magento\Framework\TestFramework\Unit\Helper\ObjectManager */
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $factory = $this->getMockBuilder($instanceName . 'Factory')
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        return $factory;
    }
}
