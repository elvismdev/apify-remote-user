<?php
/**
 * Class AruRegisterRemote
 *
 * Connect and duplicate user in remote Wordpress site using User-API
 */
class AruRegisterRemote
{
    // Define APIs URL
    const GET_NOUNCE_API = '/get_nonce/?controller=user&method=register';
    const CREATE_USER_API = '/user/register';

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

        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_page'));
        add_action('user_register', array($this, 'aru_register_remote'), 10, 1);

        // Listen for the activate event
        register_activation_hook(ARU_PLUGIN_FILE, array($this, 'activate'));

        // Deactivation plugin
        register_deactivation_hook(ARU_PLUGIN_FILE, array($this, 'deactivate'));
    }

    public function activate() {
        update_option($this->option_name, $this->data);
    }

    public function deactivate() {
        delete_option($this->option_name);
    }

    public function admin_init() {
        register_setting('aru_options', $this->option_name, array($this, 'validate'));
    }

    public function add_page() {
        add_options_page('APIfy Remote User', 'APIfy Remote User', 'manage_options', 'aru_options', array($this, 'options_do_page'));
    }

    public function options_do_page() {
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

    public function validate($input) {

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

    $get_nonce_response = wp_remote_get($settings['url_remote_site'] . '/' . $settings['api_base'] . self::GET_NOUNCE_API);
    $decoded_response = json_decode($get_nonce_response['body']);

    if (is_wp_error($get_nonce_response) || $decoded_response->status == 'error') {
        $this->notify('error', $decoded_response);
    } else {
        $user_data = get_userdata($user_id);

        $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : (isset($_POST['billing_first_name']) ? $_POST['billing_first_name'] : '');
        $last_name = isset($_POST['last_name']) ? $_POST['last_name'] : (isset($_POST['billing_last_name']) ? $_POST['billing_last_name'] : '');
        $password = isset($_POST['pass1']) ? $_POST['pass1'] : (isset($_POST['account_password']) ? $_POST['account_password'] : '');

        $create_user_response = wp_remote_get($settings['url_remote_site'] . '/' . $settings['api_base'] . self::CREATE_USER_API . '/?nonce=' . $decoded_response->nonce . '&username=' . $user_data->user_login . '&email=' . $user_data->user_email . '&display_name=' . $first_name . '&first_name=' . $first_name . '&last_name=' . $last_name . '&user_pass=' . $password . '&notify=' . $settings['email_remote_notify']);
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