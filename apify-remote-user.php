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

  $api_url = 'http://site-b.dev/api/get_nonce/?controller=user&method=register';

  function user_added_remotely() {
  	?>
  	<div class="updated">
  		<p><?php esc_html_e( 'User created remotely', 'apify-remote-user' ); ?></p>
  	</div>
  	<?php
  }

  function aru_register_remote( $user_id ) {
  	global $api_url;

  	$get_nouce_response = wp_remote_get( $api_url );
  	$decoded_response = json_decode( $get_nouce_response['body'] );

  	if ( is_wp_error( $get_nouce_response ) ) {
  		$error_message = $get_nouce_response->get_error_message();
  		// This admin notice needs work, is still not displaying after user create
  		add_action( 'admin_notices', function() use ($error_message) {
  			echo '<div class="error"><p>'. $error_message . '</p></div>';
  		}, 11);
  	} elseif ($decoded_response->status == 'error') {
  		// This admin notice needs work, is still not displaying after user create
  		add_action( 'admin_notices', function() use ($decoded_response) {
  			echo '<div class="error"><p>'. $decoded_response->error . '</p></div>';
  		}, 11);
  	} else {
  		if ( isset( $_POST['user_login'] ) )
  			$user_login = $_POST['user_login'];
  		if ( isset( $_POST['email'] ) )
  			$email = $_POST['email'];
  		$create_user_response = wp_remote_get( 'http://site-b.dev/api/user/register/?username='.$user_login.'&email='.$email.'&nonce='.$decoded_response->nonce.'&display_name=Pepe' );
  		$decoded_response = json_decode( $create_user_response['body'] );
  		if ($decoded_response->status == 'ok') {
  			// This admin notice needs work, is still not displaying after user create
  			add_action( 'admin_notices', 'user_added_remotely');
  		}
  	}

  	// if ( isset( $_POST['first_name'] ) )
    //     update_user_meta($user_id, 'first_name', $_POST['first_name']);

  }
  add_action( 'user_register', 'aru_register_remote', 10, 1 );