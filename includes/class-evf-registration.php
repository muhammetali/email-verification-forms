<?php
/**
 * Plugin Name: Email Verification Forms
 * Plugin URI: https://wordpress.org/plugins/email-verification-forms/
 * Description: Professional email verification system for WordPress and WooCommerce user registration with customizable forms, AJAX support, and comprehensive admin dashboard.
 * Version: 1.0.0
 * Author:  Muhammet Ali
 * Author URI: https://fixmob.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: email-verification-forms
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 *
 * Email Verification Forms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Email Verification Forms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Email Verification Forms. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin version
define('EVF_VERSION', '1.0.0');

// Plugin paths
define('EVF_PLUGIN_FILE', __FILE__);
define('EVF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EVF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EVF_INCLUDES_PATH', EVF_PLUGIN_PATH . 'includes/');
define('EVF_ASSETS_PATH', EVF_PLUGIN_PATH . 'assets/');
define('EVF_TEMPLATES_PATH', EVF_PLUGIN_PATH . 'templates/');
define('EVF_ASSETS_URL', EVF_PLUGIN_URL . 'assets/');

class EVF_Registration {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Registration form ve page handling
        add_action('wp_loaded', array($this, 'handle_registration_page'));
        add_action('template_redirect', array($this, 'handle_registration_endpoints'));

        // Frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // AJAX handlers for registration
        add_action('wp_ajax_evf_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_nopriv_evf_register_user', array($this, 'ajax_register_user'));

        add_action('wp_ajax_evf_check_email', array($this, 'ajax_check_email'));
        add_action('wp_ajax_nopriv_evf_check_email', array($this, 'ajax_check_email'));

        add_action('wp_ajax_evf_set_password', array($this, 'ajax_set_password'));
        add_action('wp_ajax_nopriv_evf_set_password', array($this, 'ajax_set_password'));

        // Verification handling
        add_action('wp_ajax_evf_verify_code', array($this, 'ajax_verify_code'));
        add_action('wp_ajax_nopriv_evf_verify_code', array($this, 'ajax_verify_code'));

        add_action('wp_ajax_evf_resend_code', array($this, 'ajax_resend_code'));
        add_action('wp_ajax_nopriv_evf_resend_code', array($this, 'ajax_resend_code'));

        // WordPress login redirect (when WooCommerce is not active)
        add_action('login_form_register', array($this, 'redirect_registration'));
    }

    /**
     * WordPress kayıt formunu yönlendir
     */
    public function redirect_registration() {
        wp_redirect(home_url('/email-verification/register/'));
        exit;
    }

    /**
     * Registration page'i handle et
     */
    public function handle_registration_page() {
        // Registration page path'i kontrol et
        $request_uri = $_SERVER['REQUEST_URI'];

        if (strpos($request_uri, '/email-verification/register') !== false) {
            $this->show_registration_page();
            exit;
        }
    }

    /**
     * Registration endpoints'leri handle et
     */
    public function handle_registration_endpoints() {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $path_parts = explode('/', $path);

        // email-verification/verify/TOKEN
        if (count($path_parts) >= 3 && $path_parts[0] === 'email-verification' && $path_parts[1] === 'verify') {
            $token = sanitize_text_field($path_parts[2]);
            $this->handle_email_verification($token);
            exit;
        }

        // email-verification/set-password/TOKEN
        if (count($path_parts) >= 3 && $path_parts[0] === 'email-verification' && $path_parts[1] === 'set-password') {
            $token = sanitize_text_field($path_parts[2]);
            $this->handle_password_setup($token);
            exit;
        }
    }

    /**
     * Registration sayfasını göster
     */
    private function show_registration_page() {
        // Registration template'ini include et
        $template_path = EVF_TEMPLATES_PATH . 'registration-form.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback: simple registration form
            $this->show_simple_registration_form();
        }
    }

    /**
     * Simple registration form (fallback)
     */
    private function show_simple_registration_form() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html(get_bloginfo('name')); ?> - <?php esc_html_e('Kayıt Ol', 'email-verification-forms'); ?></title>
            <?php wp_head(); ?>
        </head>
        <body <?php body_class('evf-registration-page'); ?>>
        <div class="evf-registration-wrapper">
            <div class="evf-registration-card">
                <div class="evf-header">
                    <h1><?php esc_html_e('Kayıt Ol', 'email-verification-forms'); ?></h1>
                    <p><?php esc_html_e('Hesap oluşturmak için e-posta adresinizi girin', 'email-verification-forms'); ?></p>
                </div>

                <form id="evf-registration-form" class="evf-form">
                    <div class="evf-form-group">
                        <label for="evf-email"><?php esc_html_e('E-posta Adresi', 'email-verification-forms'); ?></label>
                        <input type="email" id="evf-email" name="email" required>
                    </div>

                    <button type="submit" class="evf-btn evf-btn-primary">
                        <?php esc_html_e('Devam Et', 'email-verification-forms'); ?>
                    </button>
                </form>

                <div class="evf-footer">
                    <p><?php esc_html_e('Zaten hesabınız var mı?', 'email-verification-forms'); ?> <a href="<?php echo wp_login_url(); ?>"><?php esc_html_e('Giriş yapın', 'email-verification-forms'); ?></a></p>
                </div>
            </div>
        </div>

        <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Frontend scripts enqueue
     */
    public function enqueue_frontend_scripts() {
        if (strpos($_SERVER['REQUEST_URI'], '/email-verification/') !== false) {
            wp_enqueue_style('evf-frontend-style', EVF_ASSETS_URL . 'css/evf-frontend.css', array(), EVF_VERSION);
            wp_enqueue_script('evf-frontend-script', EVF_ASSETS_URL . 'js/evf-frontend.js', array('jquery'), EVF_VERSION, true);

            wp_localize_script('evf-frontend-script', 'evf_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('evf_nonce'),
                'plugin_mode' => 'wordpress',
                'is_woocommerce_active' => false,
                'messages' => array(
                    'checking_email' => __('E-posta kontrol ediliyor...', 'email-verification-forms'),
                    'sending_verification' => __('Doğrulama e-postası gönderiliyor...', 'email-verification-forms'),
                    'email_sent' => __('Doğrulama e-postası gönderildi!', 'email-verification-forms'),
                    'email_exists' => __('Bu e-posta adresi zaten kayıtlı.', 'email-verification-forms'),
                    'invalid_email' => __('Geçerli bir e-posta adresi girin.', 'email-verification-forms'),
                    'error' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'email-verification-forms')
                )
            ));
        }
    }

    /**
     * AJAX: E-posta kontrolü
     */
    public function ajax_check_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error('invalid_email');
        }

        // E-posta zaten kayıtlı mı?
        if (email_exists($email)) {
            wp_send_json_error('email_exists');
        }

        wp_send_json_success('email_available');
    }

    /**
     * AJAX: Kullanıcı kayıt
     */
    public function ajax_register_user() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error('invalid_email');
        }

        if (email_exists($email)) {
            wp_send_json_error('email_exists');
        }

        // Rate limiting kontrolü
        if ($this->check_rate_limit($email)) {
            wp_send_json_error('rate_limit');
        }

        // Database'e kayıt ekle
        $database = EVF_Database::instance();
        $token = wp_generate_uuid4();
        $expires_at = gmdate('Y-m-d H:i:s', strtotime('+24 hours'));

        $registration_id = $database->insert_registration(array(
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires_at,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field(wp_unslash(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '')),
            'verification_type' => 'link',
            'status' => 'pending'
        ));

        if (!$registration_id) {
            wp_send_json_error('registration_failed');
        }

        // Verification e-postası gönder
        $email_handler = EVF_Email::instance();
        $sent = $email_handler->send_verification_email($email, $token);

        if (!$sent) {
            wp_send_json_error('email_send_failed');
        }

        wp_send_json_success(array(
            'message' => __('Doğrulama e-postası gönderildi!', 'email-verification-forms')
        ));
    }

    /**
     * E-posta doğrulama işlemi
     */
    private function handle_email_verification($token) {
        if (!$token) {
            $this->show_error_page(__('Geçersiz doğrulama bağlantısı.', 'email-verification-forms'));
            return;
        }

        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration) {
            $this->show_error_page(__('Doğrulama bağlantısı bulunamadı.', 'email-verification-forms'));
            return;
        }

        // Token süresi kontrolü
        if (strtotime($registration->expires_at) < current_time('timestamp')) {
            $this->show_error_page(__('Doğrulama bağlantısının süresi dolmuş.', 'email-verification-forms'));
            return;
        }

        // E-posta doğrulandı olarak işaretle
        $database->mark_email_verified($registration->id);

        // Parola belirleme sayfasına yönlendir
        $this->show_password_setup_page($token, $registration->email);
    }

    /**
     * Parola belirleme işlemi
     */
    private function handle_password_setup($token) {
        if (!$token) {
            $this->show_error_page(__('Geçersiz bağlantı.', 'email-verification-forms'));
            return;
        }

        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration || $registration->status !== 'email_verified') {
            $this->show_error_page(__('Geçersiz veya süresi dolmuş bağlantı.', 'email-verification-forms'));
            return;
        }

        $this->show_password_setup_page($token, $registration->email);
    }

    /**
     * AJAX: Parola belirleme
     */
    public function ajax_set_password() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $token = sanitize_text_field($_POST['token']);
        $password = $_POST['password']; // Don't sanitize password

        if (!$token || !$password) {
            wp_send_json_error('missing_data');
        }

        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration || $registration->status !== 'email_verified') {
            wp_send_json_error('invalid_token');
        }

        // Parola güçlülük kontrolü
        if (strlen($password) < get_option('evf_min_password_length', 8)) {
            wp_send_json_error('password_too_weak');
        }

        // Kullanıcı oluştur
        $user_id = wp_create_user(
            sanitize_user($registration->email),
            $password,
            $registration->email
        );

        if (is_wp_error($user_id)) {
            wp_send_json_error('user_creation_failed');
        }

        // Kullanıcıyı verified olarak işaretle
        update_user_meta($user_id, 'evf_email_verified', 1);

        // Registration'ı completed olarak işaretle
        $database->mark_registration_completed($registration->id, $user_id);

        // Welcome e-postası gönder
        $email_handler = EVF_Email::instance();
        $email_handler->send_welcome_email($user_id);

        // Admin bildirimini gönder
        if (get_option('evf_admin_notifications', true)) {
            $email_handler->send_admin_notification($user_id, $registration->email);
        }

        // Kullanıcıyı otomatik login yap
        wp_set_auth_cookie($user_id);
        wp_set_current_user($user_id);

        wp_send_json_success(array(
            'redirect_url' => home_url()
        ));
    }

    /**
     * AJAX: Kod doğrulama (WooCommerce mode'da kullanılmaz)
     */
    public function ajax_verify_code() {
        wp_send_json_error('not_supported_in_wordpress_mode');
    }

    /**
     * AJAX: Kod tekrar gönderme (WooCommerce mode'da kullanılmaz)
     */
    public function ajax_resend_code() {
        wp_send_json_error('not_supported_in_wordpress_mode');
    }

    /**
     * Rate limiting kontrolü
     */
    private function check_rate_limit($email) {
        $cache_key = 'evf_rate_limit_' . md5($email . $this->get_client_ip());
        $last_attempt = wp_cache_get($cache_key, 'evf_rate_limit');

        if ($last_attempt && (time() - $last_attempt) < (get_option('evf_rate_limit', 15) * 60)) {
            return true;
        }

        wp_cache_set($cache_key, time(), 'evf_rate_limit', get_option('evf_rate_limit', 15) * 60);
        return false;
    }

    /**
     * Client IP adresini al
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ?
            sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    }

    /**
     * Hata sayfası göster
     */
    private function show_error_page($message) {
        $template_path = EVF_TEMPLATES_PATH . 'error-page.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback error page
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php esc_html_e('Hata', 'email-verification-forms'); ?></title>
                <?php wp_head(); ?>
            </head>
            <body class="evf-error-page">
            <div class="evf-error-container">
                <h1><?php esc_html_e('Hata', 'email-verification-forms'); ?></h1>
                <p><?php echo esc_html($message); ?></p>
                <a href="<?php echo esc_url(home_url()); ?>" class="evf-btn evf-btn-primary">
                    <?php esc_html_e('Ana Sayfaya Dön', 'email-verification-forms'); ?>
                </a>
            </div>
            <?php wp_footer(); ?>
            </body>
            </html>
            <?php
        }
    }

    /**
     * Parola belirleme sayfası göster
     */
    private function show_password_setup_page($token, $email) {
        $template_path = EVF_TEMPLATES_PATH . 'password-setup.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback password setup page
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php esc_html_e('Parola Belirle', 'email-verification-forms'); ?></title>
                <?php wp_head(); ?>
            </head>
            <body class="evf-password-setup-page">
            <div class="evf-password-wrapper">
                <div class="evf-password-card">
                    <div class="evf-header">
                        <h1><?php esc_html_e('Parola Belirle', 'email-verification-forms'); ?></h1>
                        <p><?php echo esc_html(sprintf(__('%s için parola belirleyin', 'email-verification-forms'), $email)); ?></p>
                    </div>

                    <form id="evf-password-form" class="evf-form">
                        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                        <div class="evf-form-group">
                            <label for="evf-password"><?php esc_html_e('Parola', 'email-verification-forms'); ?></label>
                            <input type="password" id="evf-password" name="password" required>
                        </div>

                        <div class="evf-form-group">
                            <label for="evf-password-confirm"><?php esc_html_e('Parola Tekrar', 'email-verification-forms'); ?></label>
                            <input type="password" id="evf-password-confirm" name="password_confirm" required>
                        </div>

                        <button type="submit" class="evf-btn evf-btn-primary">
                            <?php esc_html_e('Hesap Oluştur', 'email-verification-forms'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php wp_footer(); ?>
            </body>
            </html>
            <?php
        }
    }

    /**
     * Database tablosu var mı kontrol et
     */
    public function is_database_ready() {
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}evf_pending_registrations'");
        return !empty($table_exists);
    }
}

/**
 * Plugin'i başlat
 */
function EVF() {
    return EmailVerificationForms::instance();
}

// Plugin'i global olarak başlat
EVF();

/**
 * Plugin aktivasyonu sonrası çevreyi kontrol et
 */
register_activation_hook(__FILE__, function() {
    // Çevre kontrolü ve gerekli setup'ları yap
    if (class_exists('EmailVerificationForms')) {
        EmailVerificationForms::instance()->activate();
    }
});

/**
 * Plugin yüklendiğinde versiyon kontrolü yap
 */
add_action('plugins_loaded', function() {
    if (class_exists('EmailVerificationForms')) {
        EVF()->check_version_update();
    }
}, 5);