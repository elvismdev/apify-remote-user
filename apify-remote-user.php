<?php
/**
 * Plugin Name: Apify Remote User
 * Plugin URI: https://github.com/elvismdev/apify-remote-user
 * Description: Creates users on a remote WordPress install.
 * Version: 1.1.2
 * Author: Elvis Morales
 * Author URI: https://twitter.com/n3rdh4ck3r
 * Requires at least: 3.5
 * Tested up to: 4.1
 * Colaborator: Leroy Ley Loredo
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!session_id()) {
    session_start();
}

/**
 * Class AruRegisterRemote
 *
 * Connect and duplicate user in remote Wordpress site using User-API
 */
class AruRegisterRemote
{
    // Define APIs URL
    const GET_NOUNCE_API = 'http://www.wordpress-bb.dev/api/get_nonce/?controller=user&method=register';
    const CREATE_USER_API = 'http://www.wordpress-bb.dev/api/user/register';
    CONST EMAIL_NOTIFY = 'no';

    public function __construct()
    {
        add_action('user_register', array($this, 'aru_register_remote'), 10, 1);
    }

    /**
     * @param $user_id
     *
     * Set a proper description to it
     */
    public function aru_register_remote($user_id)
    {
        $get_nonce_response = wp_remote_get(self::GET_NOUNCE_API);
        $decoded_response = json_decode($get_nonce_response['body']);

        if (is_wp_error($get_nonce_response)) {
            $_SESSION['notify'] = array('class' => 'error', 'message' => $get_nonce_response->get_error_message());
        } elseif ($decoded_response->status == 'error') {
            $_SESSION['notify'] = array('class' => 'error', 'message' => $decoded_response->error);
        } else {
            $user_data = get_userdata($user_id);

            if (isset($_POST['first_name'])) {
                $first_name = $_POST['first_name'];
            } elseif (isset($_POST['billing_first_name'])) { // Data from Wocommerce checkout form
                $first_name = $_POST['billing_first_name'];
            }

            if (isset($_POST['last_name'])) {
                $last_name = $_POST['last_name'];
            } elseif (isset($_POST['billing_last_name'])) { // Data from Wocommerce checkout form
                $last_name = $_POST['billing_last_name'];
            }

            if (isset($_POST['pass1'])) {
                $password = $_POST['pass1'];
            } elseif (isset($_POST['account_password'])) { // Data from Wocommerce checkout form
                $password = $_POST['account_password'];
            }

            $create_user_response = wp_remote_get(self::CREATE_USER_API . '/?nonce=' . $decoded_response->nonce . '&username=' . $user_data->user_login . '&email=' . $user_data->user_email . '&display_name=' . $first_name . '&first_name=' . $first_name . '&last_name=' . $last_name . '&user_pass=' . $password . '&notify=' . self::EMAIL_NOTIFY);
            $decoded_response = json_decode($create_user_response['body']);
            if ($decoded_response->status == 'ok') {
                $_SESSION['notify'] = array('class' => 'updated', 'message' => 'User created remotely');
            } elseif (is_wp_error($get_nonce_response)) {
                $_SESSION['notify'] = array('class' => 'error', 'message' => $get_nonce_response->get_error_message());
            } elseif ($decoded_response->status == 'error') {
                $_SESSION['notify'] = array('class' => 'error', 'message' => $decoded_response->error);
            } else {
                $_SESSION['notify'] = array('class' => 'error', 'message' => 'Some error ocurred');
            }
        }
    }

    /**
     * @param string $class
     * @param string $message
     *
     * Set a proper description to it
     */
    public function html_notice($class = 'updated', $message = '')
    {
        echo '<div class="' . $class . ' notice is-dismissible"><p>' . $message . '</p>
                 <button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
             </div>';
    }

    /**
     * Set a proper description to it
     */
    public function notice()
    {
        add_action('admin_notices', array($this, 'html_notice'), 10, 2);
        do_action('admin_notices', $_SESSION['notify']['class'], $_SESSION['notify']['message']);

        unset($_SESSION['notify']);
    }
}

$api = new AruRegisterRemote();
if (isset($_SESSION['notify'])) {
    $api->notice();
}