<?php
/**
 * Class AruRegisterRemote
 *
 * Connect and duplicate user in remote Wordpress site using User-API
 */
class AruRegisterRemote
{
    // Define APIs
    const GET_NOUNCE_API = 'get_nonce';
    const CREATE_USER_API = 'user/register';

    // Name of the plugin options
    protected $option_name = 'apify-remote-user';

    // Default values
    protected $data = array(
        'url_remote_site' => 'http://site-b.dev',
        'api_base' => 'api',
        'email_remote_notify' => 'no'
        );

    public function __construct()
    {
        if (!session_id())
            session_start();

        add_action('admin_init', array($this, 'aru_admin_init'));
        add_action('admin_menu', array($this, 'aru_add_page'));
        add_action('user_register', array($this, 'aru_register_remote'), 10, 1);

        // Listen for the activate event
        register_activation_hook(ARU_PLUGIN_FILE, array($this, 'aru_activate'));

        // Deactivation plugin
        register_deactivation_hook(ARU_PLUGIN_FILE, array($this, 'aru_deactivate'));
    }

    public function aru_activate()
    {
        update_option($this->option_name, $this->data);
    }

    public function aru_deactivate()
    {
        delete_option($this->option_name);
    }

    public function aru_admin_init()
    {
        register_setting('aru_options', $this->option_name, array($this, 'aru_validate'));
    }

    public function aru_add_page()
    {
        add_options_page('APIfy Remote User', 'APIfy Remote User', 'manage_options', 'aru_options', array($this, 'aru_options_do_page'));
    }

    public function aru_options_do_page()
    {
        $options = get_option($this->option_name);
        ?>
        <div class="wrap">
            <h2>APIfy Remote User Options</h2>
            <form method="post" action="options.php">
                <?php settings_fields('aru_options'); ?>
                <table class="form-table">
                    <tr valign="top"><th scope="row">Remote Website URL:</th>
                        <td><input type="text" name="<?php echo $this->option_name?>[url_remote_site]" value="<?php echo $options['url_remote_site']; ?>" /></td>
                    </tr>
                    <tr valign="top"><th scope="row">API Base:</th>
                        <td><input type="text" name="<?php echo $this->option_name?>[api_base]" value="<?php echo $options['api_base']; ?>" /></td>
                    </tr>
                    <tr valign="top"><th scope="row">Remote Email Notify:</th>
                        <td>
                            <input name="<?php echo $this->option_name?>[email_remote_notify]" type="radio" value="yes" <?php checked( 'yes', $options['email_remote_notify'] ); ?> /> Yes
                            <br>
                            <input name="<?php echo $this->option_name?>[email_remote_notify]" type="radio" value="no" <?php checked( 'no', $options['email_remote_notify'] ); ?> /> No
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    public function aru_validate($input)
    {

        $valid = array();
        $valid['url_remote_site'] = sanitize_text_field($input['url_remote_site']);
        $valid['api_base'] = sanitize_text_field($input['api_base']);
        $valid['email_remote_notify'] = sanitize_text_field($input['email_remote_notify']);

        if (strlen($valid['url_remote_site']) == 0) {
            add_settings_error(
    'url_remote_site',                     // Setting title
    'urlremotesite_texterror',            // Error ID
    'Please enter a valid URL',     // Error message
    'error'                         // Type of message
    );

    // Set it to the default value
            $valid['url_remote_site'] = $this->data['url_remote_site'];
        }

        if (strlen($valid['api_base']) == 0) {
            add_settings_error(
                'api_base',
                'apisecret_texterror',
                'Please specify the API base',
                'error'
                );

            $valid['api_base'] = $this->data['api_base'];
        }

        if (strlen($valid['email_remote_notify']) == 0) {
            add_settings_error(
                'email_remote_notify',
                'emailremotenotify_texterror',
                'Please select to notify user from remote site ',
                'error'
                );

            $valid['email_remote_notify'] = $this->data['email_remote_notify'];
        }

        return $valid;
    }

/**
* @param $user_id
*
* Set a proper description to it
*/
public function aru_register_remote($user_id)
{
    $settings = get_option('apify-remote-user');

    // The args for the nounce
    $params = array(
        'controller' => 'user',
        'method' => 'register'
        );

            // Generate the URL for the nounce
    $api_url = $settings['url_remote_site'];
    if ( substr( $api_url, -1 ) != '/' )
        $api_url .= '/';

    $api_url .= $settings['api_base'];
    if ( substr( $api_url, -1 ) != '/' )
        $api_url .= '/';

    $url = $api_url . self::GET_NOUNCE_API;
    $url = add_query_arg( $params, esc_url_raw( $url ) );

    // Make API request
    $get_nonce_response = wp_remote_get( esc_url_raw( $url ) );
    $decoded_response = json_decode( wp_remote_retrieve_body( $get_nonce_response ) );

    if (is_wp_error($get_nonce_response) || $decoded_response->status == 'error') {
        $this->aru_notify('error', $decoded_response);
    } else {
        $user_data = get_userdata($user_id);

        // The args to create user
        $params = array(
            'nonce' => $decoded_response->nonce,
            'username' => $user_data->user_login,
            'email' => urlencode( $user_data->user_email ),
            'display_name' => isset($_POST['first_name']) ? $_POST['first_name'] : (isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : ''),
            'first_name' => isset($_POST['first_name']) ? $_POST['first_name'] : (isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : ''),
            'last_name' => isset($_POST['last_name']) ? $_POST['last_name'] : (isset($_POST['billing_last_name']) ? $_POST['billing_last_name'] : ''),
            'user_pass' => isset($_POST['pass1']) ? $_POST['pass1'] : (isset($_POST['account_password']) ? $_POST['account_password'] : ''), // 'user_pass_hash' => urlencode($user_data->user_pass), // HASHED
            'notify' => $settings['email_remote_notify']
            );

        // Generate the URL to create user
        $url = $api_url . self::CREATE_USER_API;
        $url = add_query_arg( $params, esc_url_raw( $url ) );

        $create_user_response = wp_remote_get( esc_url_raw( $url ) );
        $decoded_response = json_decode( wp_remote_retrieve_body( $create_user_response ) );

        if ($decoded_response->status == 'ok')
            $this->aru_notify();
        else
            $this->aru_notify('error', $decoded_response);
    }
}

/**
* @param string $class
* @param string $message
*
* Set a proper description to it
*/
public function aru_html_notice($class = 'updated', $message = '')
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
public function aru_notice()
{
    add_action('admin_notices', array($this, 'aru_html_notice'), 10, 2);
    do_action('admin_notices', $_SESSION['notify']['class'], $_SESSION['notify']['message']);

    unset($_SESSION['notify']);
}

/**
* @param string $class
* @param mixed $response
*
* Set a proper description to it
*/
public function aru_notify($class = 'updated', $response = null)
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