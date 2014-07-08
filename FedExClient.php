<?php

/**
 * For more details about the FedEx API, see:
 * http://www.fedex.com/templates/components/apps/wpor/secure/downloads/pdf/Aug13/PropDevGuide.pdf
 *
 * Example usage:
 *
 *  require_once './FedExClient.php';
 *  require_once './ShippingError.php';
 *
 *  $recipient = array(
 *    'StreetLines' => '3011 N Warner',
 *    'City' => 'Tacoma',
 *    'StateOrProvinceCode' => 'WA',
 *    'PostalCode' => '98407',
 *    'CountryCode' => 'US',
 *  );
 *
 *  $packagingType = 'YOUR_PACKAGE';
 *
 *  $weight = 10;
 *
 *  $client = new FedExClient();
 *  $rates = $client->getAvailableRates( $recipient, $packagingType, $weight );
 *
 *  foreach( $rates as $rate ) {
 *    echo $rate['method'] . ' costs $' . $rate['rate'];
 *  }
 */
class FedExClient {

  const WEIGHT_UNIT_LB = 'LB';


  /**
   * The relative path to the WSDL file for Fedex's Rate Service
   * @var string
   */
  public static $wsdl = './RateService_v14.wsdl';

  /**
   * The FedEx developer API Key
   * @var string
   */
  public static $apiKey = 'jQ8WrDd2t8UlqH4Z';

  /**
   * The password for the FedEx developer account
   * @var string
   */
  public static $password = 'Pine4pplefeddream';

  /**
   * The FedEx shipping account number
   * @var string
   */
  public static $account = '510087402';

  /**
   * The FedEx meter number
   * @var string
   */
  public static $meter = '100230273';
  // public static $meter = '510087402';

  /**
   * The FedEx API Version details
   * @var array
   */
  public static $apiVersion = array(
    'ServiceId' => 'crs',
    'Major' => '14',
    'Intermediate' => '0',
    'Minor' => '0',
  );


  /**
   * The origin information, to be used in RateRequest/RequestedShipment/Shipper
   * @var array
   */
  public static $shipper = array(
    'Contact' => array(
      'PersonName' => 'Coby Tamayo',
      'CompanyName' => 'Tamayo Web Solutions',
      'PhoneNumber' => '2532229139'
    ),
    'Address' => array(
      'StreetLines' => array('820 S Cushman Ave'),
      'City' => 'Tacoma',
      'StateOrProvinceCode' => 'WA',
      'PostalCode' => '98405',
      'CountryCode' => 'US',
      'Residential' => 1
    ),
  );

  /**
   * The internal SoapClient instance used to make the request
   * @var SoapClient
   */
  protected $soapClient;

  /**
   * Constructor.
   * @param $client A SoapClient instance (optional)
   */
  public function __construct( $client=null ) {
    if( empty($client) ) {
      $client = new SoapClient( self::$wsdl, array('trace' => true) );
    }

    $this->soapClient = $client;
  }


  /**
   * Get the available services and corresponding rates.
   *
   * See the 2013 FedEx Web Services Developer Guide, ch. 2 for more details.
   *
   * @param  Array $recipient the shipping info for the destination, including:
   *   - mixed StreetLine an array of all street address lines,
   *     or a single string for just one line
   *   - string City name of city, town, etc.
   *   - string StateOrProvinceCode required if shipping to USA or Canada
   *   - string PostalCode zip or intl postal code
   *   - string CountryCode the two-letter code
   * @param  string $packagingType the type of packaging; see Appendix K in
   *   the Developer Guide
   * @param  decimal $weight
   * @throws InvalidArgumentException if a required key is omitted from recipient
   * @throws ShippingError if FedEx reports an error or returns an invalid
   *   response
   * @return array services and their corresponding rates, in the form:
   *   Array (
   *     0 => Array (
   *       'method' => 'Overnight',
   *       'rate' => 10.00
   *     ),
   *     1 => Array (
   *       'method' => 'Standard',
   *       'rate' => 7.00,
   *     ),
   *     ...
   *   )
   */
  public function getAvailableRates( $recipient, $packagingType, $weight ) {

    $required = array(
      'StreetLines',
      'City',
      'StateOrProvinceCode',
      'PostalCode',
      'CountryCode',
    );

    // Require the, er, required fields
    foreach( $required as $key ) {
      if( empty($recipient[$key]) ) {
        throw new InvalidArgumentException( "recipient: $key is required" );
      }
    }

    // Create a single array out of the arguments
    $request = $this->buildRequest( $recipient, $packagingType, $weight );

    try {
      $response = $this->soapClient->getRates( $request );
    } catch(SoapFault $fault) {

      // Compile as detailed a message as possible
      $message = $fault->getMessage();
      if( isset($fault->detail->desc) ) {
        $message .= ": {$fault->detail->desc}";
      }

      throw new ShippingError( $message );
    }

    return $this->parseResponse( $response );
  }

  /**
   * Build a request in the form of an array to be passed to getRates().
   * See getAvailableRates() for parameter list.
   * @param  array $recipient
   * @param  string $packagingType
   * @param  decimal $weight
   * @return array the array to be passed to getRates()
   */
  protected function buildRequest( $recipient, $packagingType, $weight ) {
    // Convert StreetLines from string to array
    if( is_string($recipient['StreetLines']) ) {
      $recipient['StreetLines'] = array( $recipient['StreetLines'] );
    }

    return array(
      'Version' => self::$apiVersion,

      'WebAuthenticationDetail' => array(
        'UserCredential' => array(
          'Key' => self::$apiKey,
          'Password' => self::$password,
        ),
      ),

      'ClientDetail' => array(
        'AccountNumber' => self::$account,
        'MeterNumber' => self::$meter,
      ),

      'RequestedShipment' => array(
        // just one package per call for now
        'PackageCount' => 1,
        'Shipper' => self::$shipper,
        'Recipient' => array( 'Address' => $recipient ),
        'PackagingType' => $packagingType,
        'TotalWeight' => (float)$weight,

        // only one line item for now
        'RequestedPackageLineItems' => array(
          array(
            'GroupPackageCount' => 1,
            'Weight' => array(
              'Value' => (float)$weight,
              'Units' => self::WEIGHT_UNIT_LB,
            ),
          ),
        ),
      ),
    );
  }

  /**
   * Parse the response into an array of just the stuff we care about.
   * @param  RateReply $response the response object
   * @return array
   */
  protected function parseResponse( $response ) {

    // throw an exception if there are any obvious errors
    $this->validateResponse( $response );

    // populate this with all services/rates
    $parsed = array();

    foreach( $response->RateReplyDetails as $detail ) {

      // Skip incomplete elements
      if( isset($detail->RatedShipmentDetails)
          and isset($detail->ServiceType) ) {

        // Dive down deeper...
        $ratedShipmentDetails = $detail->RatedShipmentDetails;

        foreach( $ratedShipmentDetails as $rated ) {

          // Check again for incompleteness
          if( isset($rated->ShipmentRateDetail->TotalNetCharge->Amount) ) {

            // Holy Extensible Markup Language, Batman! A usable element!
            $service = array(
              'method' => "{$detail->ServiceType}",
              'rate' => (float)$rated->ShipmentRateDetail->TotalNetCharge->Amount,
            );

            if( !in_array($service, $parsed) ) {
              // Sometimes FedEx distinguishes services based on minor details,
              // but we only care about rate/method here
              $parsed[] = $service;
            }
          }
        }
      }
    }

    return $parsed;
  }

  /**
   * Validate the response object, throwing an error if necessary
   * @param  RateReply $response the response object
   * @throws ShippingError if FedEx reports an error, or if RateReplyDetail
   * elements are not present.
   */
  protected function validateResponse( $response ) {

    if( empty($response) ) {
      throw new ShippingError( 'Fedex returned an empty response' );
    }

    // Detect FedEx errors
    if( isset($response->HighestSeverity)
        and ($response->HighestSeverity == 'ERROR') ) {

      $message = $this->getErrorMessage( $response );

      throw new ShippingError( $message );
      // throw new ShippingError( var_export($response,true) );
    }

    // Make sure RateReplyDetail elements are present
    if( empty($response->RateReplyDetails) ) {

      $dump = var_export( $response, true );
      throw new ShippingError(
        "error parsing response: no RateReplyDetails collection found" );
    }
  }

  /**
   * Parse FedEx's response for an error message
   * @param  RateReply $response the response object
   * @return string
   */
  protected function getErrorMessage( $response ) {
    if( $response->HighestSeverity != 'ERROR' ) {
      // Not actually an error
      $message = '';

    } else if( is_array($response->Notifications) ) {
      // Several messages present...

      $errors = array();
      foreach( $response->Notifications as $note ) {

        // Note each actual error
        if( isset($note->Severity)
            and isset($note->Message)
            and ($note->Severity == 'ERROR') ) {
          $errors[] = trim( $note->Message );
        }

        // Final, compound error message
        $count = count( $errors );
        $messages = implode( $errors, '...' );
        $message = "$count error(s): $messages";
      }
    } else {
      // No error message present, but FedEx definitely reported an error.

      $message = isset( $response->Notifications->Message )
        ? $response->Notifications->Message
        : 'FedEx returned an error';
    }

    return $message;
  }
}

?>