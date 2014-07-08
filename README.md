Example usage:

    <?php

      require_once './FedExClient.php';
      require_once './ShippingError.php';

      $recipient = array(
        'StreetLines' => '3011 N Warner',
        'City' => 'Tacoma',
        'StateOrProvinceCode' => 'WA',
        'PostalCode' => '98407',
        'CountryCode' => 'US',
      );

      $packagingType = 'YOUR_PACKAGE';
      $weight = 10;

      $client = new FedExClient();
      $rates = $client->getAvailableRates( $recipient, $packagingType, $weight );

      foreach( $rates as $rate ) {
        echo $rate['method'] . ' costs $' . $rate['rate'];
      }

    ?>