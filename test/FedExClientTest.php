<?php

require_once './FedExClient.php';
require_once './ShippingError.php';

class FedExClientTest extends PHPUnit_Framework_TestCase {
  protected $client;
  protected $soap;
  protected $rateReply;

  protected $recipient = array(
    'StreetLines' => '3011 N Warner',
    'City' => 'Tacoma',
    'StateOrProvinceCode' => 'WA',
    'PostalCode' => '98407',
    'CountryCode' => 'US',
  );

  protected $packagingType = 'BOX';

  protected $weight = 12;

  protected $errorXml = <<<_XML_
<?xml version='1.0' standalone='yes'?>
<RateReply>
  <HighestSeverity>ERROR</HighestSeverity>
  <Notifications>
    <Message>Something bad happened</Message>
  </Notifications>
</RateReply>
_XML_;

  protected $errorWithoutMessageXml = <<<_XML_
<?xml version='1.0' standalone='yes'?>
<RateReply>
  <HighestSeverity>ERROR</HighestSeverity>
</RateReply>
_XML_;

  protected $rateReplyMockXml = <<<_XML_
<?xml version='1.0' standalone='yes'?>
<RateReply>
  <RateReplyDetails>
    <ServiceType>FEDEX_1_DAY_FREIGHT</ServiceType>
    <RatedShipmentDetails>
      <ShipmentRateDetail>
        <TotalNetCharge>
          <Amount>123.45</Amount>
        </TotalNetCharge>
      </ShipmentRateDetail>
    </RatedShipmentDetails>
  </RateReplyDetails>

  <RateReplyDetails>
    <ServiceType>FIRST_OVERNIGHT</ServiceType>
    <RatedShipmentDetails>
      <ShipmentRateDetail>
        <TotalNetCharge>
          <Amount>1234.56</Amount>
        </TotalNetCharge>
      </ShipmentRateDetail>
    </RatedShipmentDetails>
  </RateReplyDetails>

  <RateReplyDetails>
    <ServiceType>STANDARD_OVERNIGHT</ServiceType>
    <RatedShipmentDetails>
      <ShipmentRateDetail>
        <TotalNetCharge>
          <Amount>12345.67</Amount>
        </TotalNetCharge>
      </ShipmentRateDetail>
    </RatedShipmentDetails>
  </RateReplyDetails>
</RateReply>
_XML_;

  protected $expectedResult = array(
    array( 'rate' => 123.45, 'method' => 'FEDEX_1_DAY_FREIGHT' ),
    array( 'rate' => 1234.56, 'method' => 'FIRST_OVERNIGHT' ),
    array( 'rate' => 12345.67, 'method' => 'STANDARD_OVERNIGHT' ),
  );

  public function setUp() {
    $this->soap = $this->getMockBuilder( 'SoapClient' )
      ->disableOriginalConstructor()
      ->setMethods( array('getRates') )
      ->getMock();

    $this->rateReply = $this->getMock( 'RateReply' );

    $this->client = new FedExClient( $this->soap );
  }

  /**
   * Test that the appropriate exception is thrown
   * when the StreetLines argument is empty.
   * @expectedException InvalidArgumentException
   */
  public function testGetAvailableRatesRecipientStreetLinesValidation() {
    $this->recipient['StreetLines'] = '';
    $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );
  }

  /**
   * Test that the appropriate exception is thrown
   * when the City argument is empty.
   * @expectedException InvalidArgumentException
   */
  public function testGetAvailableRatesRecipientCityValidation() {
    $this->recipient['City'] = '';
    $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );
  }

  /**
   * Test that the appropriate exception is thrown
   * when the StateOrProvinceCode argument is empty.
   * @expectedException InvalidArgumentException
   */
  public function testGetAvailableRatesRecipientStateOrProvinceCodeValidation() {
    $this->recipient['StateOrProvinceCode'] = '';
    $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );
  }

  /**
   * Test that the appropriate exception is thrown
   * when the PostalCode argument is empty.
   * @expectedException InvalidArgumentException
   */
  public function testGetAvailableRatesRecipientPostalCodeValidation() {
    $this->recipient['PostalCode'] = '';
    $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );
  }

  /**
   * Test that the appropriate exception is thrown
   * when the CountryCode argument is empty.
   * @expectedException InvalidArgumentException
   */
  public function testGetAvailableRatesRecipientCountryCodeValidation() {
    $this->recipient['CountryCode'] = '';
    $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );
  }

  /**
   * Test that an exception is thrown with the FedEx error message
   * @expectedException ShippingError
   * @expectedExceptionMessage Something bad happened
   */
  public function testGetAvailableRatesReturnedError() {
    $rateReply = new SimpleXMLElement( $this->errorXml );
    $this->injectRateReply( $rateReply );

    $rates = $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );
  }

  /**
   * Test that an exception is thrown with our general FedEx error message
   * when FedEx fails to specify one
   * @expectedException ShippingError
   * @expectedExceptionMessage FedEx returned an error
   */
  public function testGetAvailableRatesReturnedErrorWithoutMessage() {
    $rateReply = new SimpleXMLElement( $this->errorWithoutMessageXml );
    $this->injectRateReply( $rateReply );

    $rates = $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );
  }

  public function testGetAvailableRates() {
    $this->rateReply = new SimpleXMLElement( $this->rateReplyMockXml );

    $this->soap->expects( $this->once() )
      ->method('getRates')
      ->will( $this->returnValue($this->rateReply) );

    $rates = $this->client->getAvailableRates( $this->recipient,
      $this->packagingType,
      $this->weight );

    $this->assertEquals( $rates, $this->expectedResult );
  }



  /**
   * Inject a RateReply object into the return value from the SoapClient's
   * getRates() operation
   */
  protected function injectRateReply( $reply ) {
    $this->soap->expects( $this->once() )
      ->method( 'getRates' )
      ->will( $this->returnValue($reply) );
  }
}

?>