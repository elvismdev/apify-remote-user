<?php
/**
 * Class AruRegisterRemote
 *
 * Connect and duplicate user in remote Wordpress site using User-API
 */
class AruRegisterRemote
{
    // Define APIs URL
    const GET_NOUNCE_API = 'http://site-b.dev/api/get_nonce/?controller=user&method=register';
    const CREATE_USER_API = 'http://site-b.dev/api/user/register';
    const EMAIL_NOTIFY = 'no';

    // Name of the plugin options
    protected $option_name = 'apify-remote-user';

    // Default values
    protected $data = array(
        'url_remote_site' => 'http://site-b.dev',
        'api_secret' => 'api',
        'email_remote_notify' => 'no'
        );

    public function __construct()
    {
        if (!session_id())
            session_start();

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

        if (is_wp_error($get_nonce_response) || $decoded_response->status == 'error') {
            $this->notify('error', $decoded_response);
        } else {
            $user_data = get_userdata($user_id);

            $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : (isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : '');
            $last_name = isset($_POST['last_name']) ? $_POST['last_name'] : (isset($_POST['billing_last_name']) ? $_POST['billing_last_name'] : '');
            $password = isset($_POST['pass1']) ? $_POST['pass1'] : (isset($_POST['account_password']) ? $_POST['account_password'] : '');

            $create_user_response = wp_remote_get(self::CREATE_USER_API . '/?nonce=' . $decoded_response->nonce . '&username=' . $user_data->user_login . '&email=' . $user_data->user_email . '&display_name=' . $first_name . '&first_name=' . $first_name . '&last_name=' . $last_name . '&user_pass=' . $password . '&notify=' . self::EMAIL_NOTIFY);
            $decoded_response = json_decode($create_user_response['body']);
            if ($decoded_response->status == 'ok')
                $this->notify();
            else
                $this->notify('error', $decoded_response);
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
        if (!$message)
            return;

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

    /**
     * @param string $class
     * @param mixed $response
     *
     * Set a proper description to it
     */
    public function notify($class = 'updated', $response = null)
    {
        switch ($class) {
            case 'updated':
            $message = 'User created remotely';
            break;
            case 'error':
            $message = 'Some error ocurred';
            if (method_exists($response, 'get_error_message'))
                $message = $response->get_error_message();
            elseif (isset($response->error))
                $message = $response->error;
            break;
            default:
            $message = 'Some default message';
            break;
        }

        $_SESSION['notify'] = array('class' => $class, 'message' => $message);
    }
}