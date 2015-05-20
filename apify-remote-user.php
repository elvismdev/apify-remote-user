<?php
/**
 * Plugin Name: Apify Remote User
 * Plugin URI: https://github.com/elvismdev/apify-remote-user
 * Description: Creates users on a remote WordPress install.
 * Version: 1.1.1
 * Author: Elvis Morales
 * Author URI: https://twitter.com/n3rdh4ck3r
 * Requires at least: 3.5
 * Tested up to: 4.1
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
  }

  $api_url = 'http://site-b.dev/cardone/get_nonce/?controller=user&method=register';


  add_action( 'user_register', 'aru_register_remote', 10, 1 );

  function aru_register_remote( $user_id ) {

  	$response = wp_remote_get( $api_url );
  	if( is_array($response) ) {
  $header = $response['headers']; // array of http header lines
  $body = $response['body']; // use the content
}

error_log($response);
print_r($response);

    // if ( isset( $_POST['first_name'] ) )
    //     update_user_meta($user_id, 'first_name', $_POST['first_name']);

}