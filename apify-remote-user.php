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


  function aru_register_remote( $user_id ) {
  	global $api_url;

  	$get_nouce_response = wp_remote_get( $api_url );

  	if ( is_wp_error( $get_nouce_response ) ) {
  		$error_message = $get_nouce_response->get_error_message();
  		echo "Something went wrong: $error_message";
  	} else {
  		$post_user_response = wp_remote_post( "http://site-b.dev/cardone/user/register/?username=john&email=john@domain.com&nonce=bb7eaefcc1&display_name=John" );
  	}

  	// if ( isset( $_POST['first_name'] ) )
    //     update_user_meta($user_id, 'first_name', $_POST['first_name']);

  }
  add_action( 'user_register', 'aru_register_remote', 10, 1 );