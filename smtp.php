<?php
/*
Plugin Name: Multisite SMTP
Plugin URI: https://github.com/rohmann/wp-multisite-smtp
Description: Allows for the setup of SMTP mail via constants in wp-config.php
Author: Alexander Rohmann
Author URI: https://github.com/rohmann/
Version: 2.0
Text Domain: multisite-smtp
*/

/**
 * Configures WordPress PHPMailer with values from wp-config.php
 * Documentation: https://github.com/rohmann/wp-multisite-smtp
 */
class Multisite_SMTP {

    /**
     * I am a singleton
     */
    protected static $instance;

    /**
     * Store validation types
     */
    protected $validations;

    /**
     * Checks settings, and hooks into phpmailer if everything is good.
     */
    function __construct() {

        $this->prepare_settings();
        $check = $this->validate();

        if( is_wp_error( $check ) ) {
            trigger_error($check->get_error_message(), E_USER_WARNING);
        } else {
            add_action( 'phpmailer_init', array( $this, 'mailer') );
        }

        if ( defined('GLOBAL_SMTP_FROM') ) {
            add_filter( 'wp_mail_from', array( $this, 'get_from' ), -999 );
        }

        if ( defined('GLOBAL_SMTP_FROM_NAME') ) {
            add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ), -999 );
        }

        unset($this->validations);
    }

    /**
     * Self Instantiate
     * Note: Plugin can be disable internally by setting GLOBAL_SMTP_DISABLE to true.
     * This is useful if you have different needs between staging and production environments.
     */
    public static function launch() {
        self::$instance = (defined('GLOBAL_SMTP_DISABLE') && GLOBAL_SMTP_DISABLE ) ? null : new self();
    }

    /**
     * Prepare PHP Mailer settings. Allows for minimum configuration by asumming common defualts.
     */
    protected function prepare_settings() {
        $this->validations = new stdClass;
        $this->validations->required = array('GLOBAL_SMTP_HOST','GLOBAL_SMTP_USER','GLOBAL_SMTP_PASSWORD');
        $this->validations->is_email = array('GLOBAL_SMTP_RETURN_PATH','GLOBAL_SMTP_REPLYTO_FROM');
        $this->validations->is_int = array('GLOBAL_SMTP_PORT','GLOBAL_SMTP_TIMEOUT');
        $this->validations->should_be = array('GLOBAL_SMTP_SECURE' => array('ssl','tls','none'),
            'GLOBAL_SMTP_AUTH_TYPE' => array('LOGIN','PLAIN','NTLM') );

        //Assume any undefined settings
        $assume = array(
            'GLOBAL_SMTP_PORT'=> 465,
            'GLOBAL_SMTP_SECURE' => 'ssl',
            'GLOBAL_SMTP_TIMEOUT' => 10,
            'GLOBAL_SMTP_FROM' => '',
            'GLOBAL_SMTP_FROM_NAME' => '',
            'GLOBAL_SMTP_AUTH_TYPE' => 'LOGIN',
        );

        foreach ($assume as $setting => $default) {
            if(!defined($setting)) {
                define($setting, $default);
            }
        }
    }

    /**
     * Callback for wp_mail_from filter
     * @return string from email address
     */
    public function get_from() {
        return GLOBAL_SMTP_FROM;
    }

    /**
     * Callback for wp_mail_from_name filter
     * @return string from email address
     */
    public function get_from_name() {
        return GLOBAL_SMTP_FROM_NAME;
    }

    /**
     * Validate Configuration to ensure things are setup correctly
     * @return bool|WP_Error Returns true if successful, else WP_Error with message
     */
    protected function validate() {

        foreach ($this->validations->required as $setting) {
            if(!defined($setting)) {
                return new WP_Error( 'multisite-smtp', sprintf( __( '%s is required for Multisite SMTP. Please define this in wp-config.php.', 'multisite-smtp' ), $setting ) );
            }
        }

        foreach ($this->validations->is_email as $setting) {
            if (defined($setting) && !is_email(constant($setting))) {
                return new WP_Error( 'multisite-smtp', sprintf( __( 'Value of %s is not a valid email address. Check wp-config.php, or ensure a valid fallback is available.', 'multisite-smtp' ), $setting ) );
            }
        }

        foreach ($this->validations->is_int as $setting) {
            if(defined($setting) && !is_int(constant($setting)) ) {
                return new WP_Error( 'multisite-smtp', sprintf( __( '%s should be an integer. The force is not so strong with this one...', 'multisite-smtp' ), $setting ) );
            }
        }

        foreach ($this->validations->should_be as $setting => $allowed) {
            if(defined($setting) && !in_array(constant($setting), $allowed)) {
                return new WP_Error( 'multisite-smtp', sprintf( __( '%s is invalid. It should be one of these values: "%s"', 'multisite-smtp' ), $setting, implode('" , "',$allowed) ) );
            }
        }

        return true;
    }

    /**
     * Hook PHP Mailer to use our SMTP settings
     */
    public function mailer( $phpmailer ) {

        //debug?
        if ( defined('GLOBAL_SMTP_DEBUG') && GLOBAL_SMTP_DEBUG && is_admin() && ( !defined('DOING_AJAX') || !DOING_AJAX ) ) {

            $phpmailer->SMTPDebug = true;

            // There's no way to close this <pre> without plugging wp_mail, which this project aims to avoid
            // It will make the PHPMailer output more readable, but only when used with: https://wordpress.org/plugins/check-email/
            if ( isset($_GET['page']) && 'checkemail' == $_GET['page'] )
                echo '<pre>';
        }

        //preset
        $phpmailer->Mailer = "smtp";
        $phpmailer->SMTPAuth = ( 'none' != GLOBAL_SMTP_SECURE );

        //required
        $phpmailer->Host = GLOBAL_SMTP_HOST;
        $phpmailer->Username = GLOBAL_SMTP_USER;
        $phpmailer->Password = GLOBAL_SMTP_PASSWORD;

        //assumed
        $phpmailer->Port = GLOBAL_SMTP_PORT;
        $phpmailer->SMTPSecure = GLOBAL_SMTP_SECURE;
        $phpmailer->AuthType = GLOBAL_SMTP_AUTH_TYPE;

        //Optional
        $phpmailer->Sender = defined('GLOBAL_SMTP_RETURN_PATH') ? GLOBAL_SMTP_RETURN_PATH : $phpmailer->From;

        if(defined('GLOBAL_SMTP_REPLYTO_FROM')) {
            $phpmailer->AddReplyTo(GLOBAL_SMTP_REPLYTO_FROM, defined('GLOBAL_SMTP_REPLYTO_FROM_NAME') ? GLOBAL_SMTP_REPLYTO_FROM_NAME : $phpmailer->FromName );
        }
    }

}

/**
 * Fire it up.
 */
Multisite_SMTP::launch();