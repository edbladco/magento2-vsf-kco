<?php

namespace Kodbruket\VsfKco\Test\Unit\Model\Klarna\DataTransform\Request;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Magento\Framework\DataObject;

/**
 * @covers \Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address
 */
class AddressTest extends TestCase
{
    const COUNTRY = 'SE';
    const REGION_ID = 1;
    const REGION = 'Kalmar';
    const CITY = 'Kalmar';

    /**
     * Mock region
     *
     * @var \Magento\Directory\Model\Region|PHPUnit_Framework_MockObject_MockObject
     */
    private $region;

    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Object to test
     *
     * @var \Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address
     */
    private $testObject;

    /**
     * Main set up method
     */
    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->region = $this->createMock(\Magento\Directory\Model\Region::class);

        $this->regionObject = new DataObject(
            [
                'id' => self::REGION_ID,
                'name' => self::REGION
            ]
        );

        $this->region->expects($this->any())
            ->method($this->logicalOr('loadByName','loadByCode'))
            ->with(self::REGION, self::COUNTRY)
            ->willReturn($this->regionObject);

        $this->testObject = $this->objectManager->getObject(
            \Kodbruket\VsfKco\Model\Klarna\DataTransform\Request\Address::class,
            [
                'region' => $this->region,
            ]
        );
    }

    /**
     * @return void
     */
    public function testPrepareMagentoAddress()
    {
        $streetName = 'Big Street';
        $streetNumber = '123';
        $streetAddress = 'The Kodbruket Building';
        $organizationName = 'Kodbruket';
        $familyName = 'Kod';
        $givenName = 'Bru Ket';
        $email = 'test@kodbruket.se';
        $title = 'Super';
        $postalCode = '1111 11';
        $phone = '+45 678912345';
        $dob = '10 Dec 2019';
        $gender = 'Male';
        $sameAsOther = 0;

        $prerequisites = new DataObject([
            'country' => self::COUNTRY,
            'street_name' => $streetName,
            'street_number' => $streetNumber,
            'street_address' => $streetAddress,
            'street_address2' => '',
            'organization_name' => $organizationName,
            'care_of' => '',
            'family_name' => $familyName,
            'given_name' => $givenName,
            'email' => $email,
            'title' => $title,
            'postal_code' => $postalCode,
            'city' => self::CITY,
            'phone' => $phone,
            'same_as other' => $sameAsOther,
            'region' => self::REGION,
            'customer_dob' => $dob,
            'customer_gender' => $gender,
        ]);

        $expectedResult = [
            'lastname' => $familyName,
            'firstname' => $givenName,
            'email' => $email,
            'company' => $organizationName,
            'prefix' => $title,
            'street' => $streetName.' '.$streetNumber,
            'postcode' => $postalCode,
            'city' => self::CITY,
            'telephone' => $phone,
            'country_id' => self::COUNTRY,
            'region_id' => self::REGION_ID,
            'region' => self::REGION,
            'same_as_other' => $sameAsOther,
            'dob' => $dob,
            'gender' => $gender
        ];

        $result = $this->testObject->prepareMagentoAddress($prerequisites);
        $this->assertEquals($expectedResult, $result);
    }
}
