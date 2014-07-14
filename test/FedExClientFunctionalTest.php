<?php

require_once './FedExClient.php';
require_once './ShippingError.php';

class FedExClientFunctionalTest extends PHPUnit_Framework_TestCase {
  protected $client;
  protected $soap;
  protected $rateReply;

  /**
   * An array of test cases. Each includes:
   *   - a recipient array with shipping details
   *   - packagingType
   *   - weight
   *
   * See FedExClient class itself for more details.
   *
   * @var array
   */
  protected $cases = array(
    array(
      'recipient' => array(
        // This address was taken directly from the fedex-common.php example
        // so it better friggin' work
        'StreetLines' => array('13450 Farmcrest Ct'),
        'City' => 'Herndon',
        'StateOrProvinceCode' => 'VA',
        'PostalCode' => '20171',
        'CountryCode' => 'US'
      ),
      'packagingType' => 'YOUR_PACKAGING',
      'weight' => 5,
    ),
    array(
      'recipient' => array(
        'StreetLines' => array('3011 N Warner'),
        'City' => 'Tacoma',
        'StateOrProvinceCode' => 'WA',
        'PostalCode' => '98407',
        'CountryCode' => 'US',
      ),
      'packagingType' => 'YOUR_PACKAGING',
      'weight' => 12.5,
    ),
    array(
      'recipient' => array(
        'StreetLines' => '1201 Pacific Ave',
        'City' => 'Tacoma',
        'StateOrProvinceCode' => 'WA',
        'PostalCode' => '98402',
        'CountryCode' => 'US',
      ),
      'packagingType' => 'FEDEX_BOX',
      'weight' => 2.1,
    ),
    array(
      'recipient' => array(
        'StreetLines' => '5918 38th Ave',
        'City' => 'Sacramento',
        'StateOrProvinceCode' => 'CA',
        'PostalCode' => '95824',
        'CountryCode' => 'US',
      ),
      'packagingType' => 'FEDEX_BOX',
      'weight' => 15.6,
    ),
  );

  public function setUp() {
    $this->client = new FedExClient();
  }

  public function testGetAvailableRates() {
    foreach( $this->cases as $case ) {
      $rates = $this->client->getAvailableRates( $case['recipient'],
        $case['packagingType'],
        $case['weight'] );

      $this->assertInternalType( 'array', $rates );
      $this->assertNotEmpty( $rates );

      foreach( $rates as $i => $service ) {
        $this->assertInternalType( 'integer', $i );
        $this->assertInternalType( 'float', $service['rate'] );
        $this->assertInternalType( 'string', $service['method'] );
      }
    }
  }
}

?>