<?php
/**
 * Plugin Name: APIfy Remote User
 * Plugin URI: https://github.com/elvismdev/apify-remote-user
 * Description: Creates users on a remote WordPress install.
 * Version: 1.1.2
 * Author: Elvis Morales
 * Author URI: https://twitter.com/n3rdh4ck3r
 * Requires at least: 3.5
 * Tested up to: 4.1
 * Collaborator: Leroy Ley Loredo
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('ARU_PLUGIN_FILE', __FILE__);
define( 'ARU_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once( ARU_PLUGIN_PATH . 'inc/user-add.php' );

if (is_admin()) {
	$api = new AruRegisterRemote();
	if (isset($_SESSION['notify']))
		$api->notice();
}