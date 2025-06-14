<?php
/**
 * EVF WooCommerce Integration Class
 * WooCommerce entegrasyonu - DÜZELTİLMİŞ VERSİYON
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce {

    private static $instance = null;
    private static $initialized = false; // Çoklu init engelleyici

    /**
     * Alt sınıf referansları
     */
    private $ajax_handler = null;
    private $email_handler = null;
    private $display_handler = null;
    private $ui_handler = null;
    private $password_handler = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Çoklu init'i engelle
        if (self::$initialized) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Already initialized, skipping...');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Initializing for the first time');
        }

        // WooCommerce'in tam yüklenmesini bekle
        if (did_action('woocommerce_loaded')) {
            $this->init();
        } else {
            add_action('woocommerce_loaded', array($this, 'init'), 1);
        }

        self::$initialized = true;
    }

    /**
     * Ana initialization
     */
    public function init() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: init() called');
        }

        // Alt sınıfları yükle
        $this->load_sub_classes();

        // Hook'ları başlat
        $this->init_hooks();

        // Late hooks (WooCommerce'in tamamlanmasından sonra)
        add_action('init', array($this, 'init_late_hooks'), 20);
    }

    /**
     * Alt sınıfları yükle
     */
    private function load_sub_classes() {
        // AJAX Handler - EN ÖNEMLİ
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-ajax.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-ajax.php';
            $this->ajax_handler = EVF_WooCommerce_AJAX::instance();
        }

        // Email Handler
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-email.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-email.php';
            $this->email_handler = EVF_WooCommerce_Email::instance();
        }

        // Display Handler
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-display.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-display.php';
            $this->display_handler = EVF_WooCommerce_Display::instance();
        }

        // UI Handler
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-ui.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-ui.php';
            $this->ui_handler = EVF_WooCommerce_UI::instance();
        }

        // Password Handler
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-password.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-password.php';
            $this->password_handler = EVF_WooCommerce_Password::instance();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Sub-classes loaded');
        }
    }

    /**
     * Ana hook'ları kaydet
     */
    private function init_hooks() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Initializing hooks');
        }

        // Registration hooks
        add_action('woocommerce_created_customer', array($this, 'handle_customer_registration'), 10, 3);

        // Login hooks - verified olmayan kullanıcıları engelle
        add_filter('wp_authenticate_user', array($this, 'check_user_verification'), 10, 2);

        // Account hooks - verified olmayan kullanıcıları logout yap
        add_action('wp_loaded', array($this, 'check_logged_in_user_verification'));

        // WooCommerce customer emails'leri deaktif et
        add_action('init', array($this, 'disable_woocommerce_customer_emails'), 15);

        // Query vars
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Rewrite rules
        add_action('init', array($this, 'add_rewrite_rules'), 10);
    }

    /**
     * Geç hook'lar (WooCommerce tamamen yüklendikten sonra)
     */
    public function init_late_hooks() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Late hooks initialized');
        }

        // Template redirect
        add_action('template_redirect', array($this, 'handle_verification_requests'), 5);

        // Rewrite rules flush (sadece ilk kurulumda)
        if (get_option('evf_wc_rewrite_rules_flushed') !== 'yes') {
            flush_rewrite_rules();
            update_option('evf_wc_rewrite_rules_flushed', 'yes');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Rewrite rules flushed');
            }
        }
    }

    /**
     * WooCommerce customer emails'leri deaktif et
     */
    public function disable_woocommerce_customer_emails() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Customer emails disabled');
        }

        // New account email'i deaktif et
        remove_action('woocommerce_created_customer_notification', array('WC_Emails', 'customer_new_account'), 10, 3);

        // Email class'ından da kaldır
        add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');
    }

    /**
     * Müşteri kaydı handle et
     */
    public function handle_customer_registration($customer_id, $new_customer_data = array(), $password_generated = false) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: New customer registered - ID: ' . $customer_id . ', Email: ' . $new_customer_data['user_email'] . ', Password generated: ' . ($password_generated ? 'YES' : 'NO'));
        }

        $user = get_user_by('id', $customer_id);
        if (!$user) {
            return;
        }

        $email = $user->user_email;

        // Admin'ler exempt
        if (user_can($customer_id, 'manage_options')) {
            return;
        }

        // Zaten verified ise skip
        if (get_user_meta($customer_id, 'evf_email_verified', true)) {
            return;
        }

        // Temporary password set et (eğer password generated ise)
        if ($password_generated) {
            update_user_meta($customer_id, 'evf_temp_password', wp_generate_password(12));

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Temporary password set for user ' . $customer_id);
            }
        }

        // Verification type belirle
        $verification_type = get_option('evf_verification_method', 'code');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Using verification type: ' . $verification_type);
        }

        // Database'e kaydet
        $database = EVF_Database::instance();
        $registration_data = array(
            'user_id' => $customer_id,
            'email' => $email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'verification_type' => $verification_type,
            'status' => 'pending',
            'token' => wp_generate_uuid4(),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+24 hours')),
            'created_at' => current_time('mysql')
        );

        $registration_id = $database->create_registration($registration_data);

        if (!$registration_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Database insert failed');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Database insert successful, ID: ' . $registration_id);
        }

        // E-posta gönder
        $email_handler = EVF_Email::instance();

        if ($verification_type === 'code') {
            $code = $database->generate_verification_code();
            $database->save_verification_code($registration_id, $code);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Sending verification code email to ' . $email . ' with code: ' . $code);
            }

            $result = $email_handler->send_verification_code_email($email, $code);
        } else {
            $verification_url = home_url('/wc-email-verification/' . $registration_data['token']);
            $result = $email_handler->send_verification_email($email, $verification_url);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Code email sent result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }
    }

    /**
     * Kullanıcı login'de verification kontrolü
     */
    public function check_user_verification($user, $password) {
        if (is_wp_error($user)) {
            return $user;
        }

        // Admin'ler exempt
        if (user_can($user->ID, 'manage_options')) {
            return $user;
        }

        // E-posta verified mi kontrol et
        if (!get_user_meta($user->ID, 'evf_email_verified', true)) {
            return new WP_Error('email_not_verified',
                __('E-posta adresiniz henüz doğrulanmamış. Lütfen e-postanızı kontrol edin.', 'email-verification-forms')
            );
        }

        return $user;
    }

    /**
     * Login'li kullanıcı verification kontrolü
     */
    public function check_logged_in_user_verification() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Admin'ler exempt
        if (user_can($user_id, 'manage_options')) {
            return;
        }

        // Account sayfasında değilse skip
        if (!is_wc_endpoint_url() && !is_account_page()) {
            return;
        }

        // Verified olmayan kullanıcıları logout yap
        if (!get_user_meta($user_id, 'evf_email_verified', true)) {
            wp_logout();
            wp_redirect(add_query_arg('evf_error', 'not_verified', wc_get_page_permalink('myaccount')));
            exit;
        }
    }

    /**
     * Query vars ekle
     */
    public function add_query_vars($vars) {
        $vars[] = 'wc_email_verification';
        $vars[] = 'wc_code_verify';
        $vars[] = 'evf_action';
        $vars[] = 'evf_token';
        return $vars;
    }

    /**
     * Rewrite rules ekle
     */
    public function add_rewrite_rules() {
        // E-posta verification endpoint
        add_rewrite_rule(
            '^wc-email-verification/([^/]+)/?$',
            'index.php?wc_email_verification=$matches[1]',
            'top'
        );

        // Kod verification endpoint
        add_rewrite_rule(
            '^wc-code-verification/([^/]+)/?$',
            'index.php?wc_code_verify=$matches[1]',
            'top'
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email verification endpoint added');
        }
    }

    /**
     * Verification request'lerini handle et
     */
    public function handle_verification_requests() {
        $email_token = get_query_var('wc_email_verification');
        $code_email = get_query_var('wc_code_verify');

        if ($email_token) {
            $this->handle_email_verification($email_token);
        }

        if ($code_email) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Verification redirect triggered with action: wc_code_verify');
            }
            $this->handle_code_verification_redirect($code_email);
        }
    }

    /**
     * E-posta verification handle et
     */
    private function handle_email_verification($token) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email verification - Token: ' . $token);
        }

        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration) {
            wp_redirect(add_query_arg('evf_error', 'invalid_token', wc_get_page_permalink('myaccount')));
            exit;
        }

        if ($registration->status === 'completed') {
            wp_redirect(add_query_arg('evf_success', 'already_verified', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Token'ın süresi dolmuş mu kontrol et
        if (strtotime($registration->expires_at) < time()) {
            wp_redirect(add_query_arg('evf_error', 'token_expired', wc_get_page_permalink('myaccount')));
            exit;
        }

        // E-posta'yı doğrulanmış olarak işaretle
        update_user_meta($registration->user_id, 'evf_email_verified', 1);
        $database->mark_registration_completed($registration->id, $registration->user_id);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email verified successfully for user: ' . $registration->user_id);
        }

        // Başarı sayfasına yönlendir
        wp_redirect(add_query_arg('evf_success', 'email_verified', wc_get_page_permalink('myaccount')));
        exit;
    }

    /**
     * Kod verification redirect handle et
     */
    private function handle_code_verification_redirect($email) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Magic link clicked - Email: ' . $email);
        }

        // E-posta ile registration'ı bul
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code' 
             ORDER BY id DESC LIMIT 1",
            $email
        ));

        if (!$registration) {
            wp_redirect(add_query_arg('evf_error', 'registration_not_found', wc_get_page_permalink('myaccount')));
            exit;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Magic link clicked - Email: ' . $email . ', Status: ' . $registration->status);
        }

        // Zaten completed ise account'a yönlendir
        if ($registration->status === 'completed') {
            wp_redirect(add_query_arg('evf_success', 'already_verified', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Display handler'ı kullanarak kod doğrulama sayfasını göster
        if ($this->display_handler) {
            $this->display_handler->show_code_verification_page($registration);
        } else {
            // Fallback
            wp_redirect(add_query_arg('evf_error', 'display_error', wc_get_page_permalink('myaccount')));
            exit;
        }
    }

    /**
     * Alt sınıf referanslarını döndür
     */
    public function get_ajax_handler() {
        return $this->ajax_handler;
    }

    public function get_email_handler() {
        return $this->email_handler;
    }

    public function get_display_handler() {
        return $this->display_handler;
    }

    public function get_ui_handler() {
        return $this->ui_handler;
    }

    public function get_password_handler() {
        return $this->password_handler;
    }
}