<?php
/*
  Plugin Name: Auth Client
  Plugin URI:
  Description: This plugin will send request to remote server .For this you need to add auth key manually(auth key will be provided by remote server).
  To see this plugin in working mode you need activate "Auth Server" at remote server side.
  Author: Shalu
  Version: 1.6
  Author URI:
 */

class AuthClientHelper {

    private static $instance;

    private function __construct() {

        add_action('login_init', array($this, 'client_server_get_login_info')); //initialize load theme setting
        add_action('admin_menu', array($this, 'add_menu'));
    }

    public static function get_instance() {

        if (!isset(self::$instance))
            self::$instance = new self();

        return self::$instance;
    }

    function add_menu() {

        add_menu_page('Auth Client', 'Auth Client', 'manage_options', 'add_auth_key', array($this, 'auth_client_form'));
        add_action('admin_init', array($this, 'register_mysettings'));
    }

    public function register_mysettings() {

        register_setting('auth-setting-group', 'auth-setting-group', array($this, 'auth_validate_options'));
        add_settings_section('auth_client_section', 'Auth Key', array($this, 'auth_section_callback'), __FILE__);
        add_settings_field('domain_name', 'Domain Name', array($this, 'auth_domain_name_callback'), __FILE__, 'auth_client_section');
        add_settings_field('auth_key_id', 'Auth Key', array($this, 'auth_key_callback'), __FILE__, 'auth_client_section');
    }

    public function auth_section_callback() {

        echo "<p class =description>This plugin will not work individual you have to activate other plugin (Auth Server) at remote side.</p>";
    }

    public function auth_domain_name_callback() {


        $options = get_option('auth-setting-group');
        $site_url = get_option('siteurl');

        echo "<input id='domain_name' name='auth-setting-group[domain_name]' readonly='readonly'  size='35' type='text' value='{$site_url}' />";
    }

    public function auth_key_callback() {

        $options = get_option('auth-setting-group');
        echo "<input id='auth_key_id' name='auth-setting-group[auth_key_id]' size='35' type='text' value='{$options['auth_key_id']}' />";
        echo "<p class ='description'>This auth key should be same as the key generated at remote server.</p>";
    }

    public function auth_validate_options($input) {

        $options = get_option('auth-setting-group');
        $input['auth-setting-group'] = wp_filter_kses($input['auth-setting-group']);
        return $input;
    }

    public function auth_client_form() {
        ?>  
        <div class="wrap">

            <form method="post" action="options.php">
                <?php
                settings_fields('auth-setting-group');
                do_settings_sections(__FILE__);
                submit_button();
                ?>

            </form>
        </div>
        <?php
    }

    function client_server_get_login_info() {
        $client = array();

        $user_login = $_POST['log'];
        $user_pass = $_POST['pwd'];
        //here we get token key that we saved from wp-admin manually
        $get_option_value = get_option('auth-setting-group');
        //here we get toekn id saved from client server admin
        $auth_key_id = trim($get_option_value['auth_key_id']);

        //here we get domain name 
        $domain_name = trim($get_option_value['domain_name']);

        $url = "http://localhost/latestwp/?auth=true";
        $ch = curl_init();

        $post_data = array(
            "username" => $user_login,
            "password" => $user_pass,
            "authkey" => $auth_key_id,
            "domainname" => $domain_name
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // we are doing a POST request
        curl_setopt($ch, CURLOPT_POST, true);
        // adding the post variables to the request
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);

        //get info 
        $info = curl_getinfo($ch);
        //close curl
        //echo"<pre>";print_r($info);
        curl_close($ch);
        //echo"<pre>";print_r($output);
        //now let us examine the output
        if ($output !== false) {
            //echo $output;
            //echo "<pre>";print_r(maybe_unserialize($output));
            //unserialize response here  
            $info = maybe_unserialize($output);
            
           
            //if(is_array($info))
            //user was authenticated from remote side.it $info['auth'] return true or 1
            if ($info['auth']) {
               
                //let us see if the user not exist exists locally and authenticated from remote server
                // here we insert the user info locally
                if (!username_exists($user_login)) {
                    echo"user authenticated but not exist locally";
                    $info['user']->data->ID = null;
                    
                    wp_insert_user((array) ($info['user']->data));
                    
                }
                
               
                $user = get_user_by('login', $user_login);
                
               

                if (!is_wp_error($user)) {
                    
                    //wp_set_auth_cookie( $user_id, $remember, $secure )
                    wp_set_auth_cookie($user->ID, true, false);
                    wp_redirect(site_url('/'));
                    exit(0);
                } else {
                    //print_r($user);
                }
            }
        } //if $output not equal false


        if ($output === FALSE) {
            echo "cURL Error: " . curl_error($ch);
        }
    }

    //function close here
}

AuthClientHelper::get_instance(); //instantiate the helper which will setup the theme in turn



