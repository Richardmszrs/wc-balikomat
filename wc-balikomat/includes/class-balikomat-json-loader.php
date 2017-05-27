<?php
class Balikomat_Json_Loader {
  function load( $params = null ) {
    $url = $this->build( $params );
    $result = $this->fetch( $url );
    $body = $this->verify( $result );
    return $this->parse( $body );
  }

  function fetch( $url ) {
    return wp_remote_get( $url );
  }

  function verify( $result ) {
    if ( is_wp_error( $result ) ) {
      throw new Exception( 'Balikomat_Json_Loader Failed: Nepodařilo se získat obsah z URL adresy.' );
    }

    $code = $result['response']['code'];
    if ( $code != 200 ) {
      throw new Exception( 'Balikomat_Json_Loader Failed: Neplatná HTTP reakce ze serveru - ' . $code );
    }

    $body = wp_remote_retrieve_body( $result );
    if ( $body == '' ) {
      throw new Exception( 'Balikomat_Json_Loader Failed: Obsah Json je prázdný.' );
    }

    return $body;
  }

  function parse( $body ) {
    $json = json_decode( $body );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
      throw new Exception( 'Balikomat_Json_Loader Failed: Neplatný Json obsah získaný ze serveru - ' . json_last_error() );
    }
    return $json;
  }

  function build( $params ) {
    $base_url = 'https://bridge.intime.cz/public/paczkomaty/machines.json';
    $available_shipping = WC()->shipping->load_shipping_methods();
    $settings = $available_shipping[ "balikomat" ]->settings;

      if ( ! empty( $params['country'] ) ) {
        $base_url .= '?country=' . $params['country'];
      }
      return $base_url;
    }
}
