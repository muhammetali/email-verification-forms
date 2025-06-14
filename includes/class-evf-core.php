<?php
/**
 * EVF Core Class
 * Ana işlevsellik sınıfı - DÜZELTİLMİŞ VERSİYON
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Core {

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
     * Hook'ları başlat - SADECE TEMEL HOOKS
     */
    private function init_hooks() {
        // Scripts ve styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('login_enqueue_scripts', array($this, 'enqueue_login_scripts'));

        // Custom endpoints
        add_action('wp_loaded', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_custom_endpoints'));

        // SADECE WordPress mode'da AJAX handlers ekle (WooCommerce yoksa)
        if (!evf_is_woocommerce_active()) {
            add_action('wp_ajax_evf_verify_code', array($this, 'ajax_verify_code'));
            add_action('wp_ajax_nopriv_evf_verify_code', array($this, 'ajax_verify_code'));
            add_action('wp_ajax_evf_resend_code', array($this, 'ajax_resend_code'));
            add_action('wp_ajax_nopriv_evf_resend_code', array($this, 'ajax_resend_code'));

            // WordPress login override
            add_action('login_form_register', array($this, 'redirect_registration'));
        }

        // Ortak AJAX handlers (her modda çalışan)
        add_action('wp_ajax_evf_check_email', array($this, 'ajax_check_email'));
        add_action('wp_ajax_nopriv_evf_check_email', array($this, 'ajax_check_email'));
        add_action('wp_ajax_evf_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_nopriv_evf_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_evf_set_password', array($this, 'ajax_set_password'));
        add_action('wp_ajax_nopriv_evf_set_password', array($this, 'ajax_set_password'));

        // Global verification check hooks
        add_action('wp_loaded', array($this, 'check_user_verification_status'));

        // Cron job hooks
        add_action('evf_auto_delete_unverified', array($this, 'auto_delete_unverified_accounts'));
        add_action('wp_loaded', array($this, 'setup_auto_delete_cron'));
    }

    /**
     * Frontend scripts ve styles
     */
    public function enqueue_frontend_scripts() {
        // CSS
        wp_enqueue_style(
            'evf-frontend-style',
            EVF_ASSETS_URL . 'css/evf-frontend.css',
            array(),
            EVF_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'evf-frontend-script',
            EVF_ASSETS_URL . 'js/evf-frontend.js',
            array('jquery'),
            EVF_VERSION,
            true
        );

        // JavaScript config
        wp_localize_script('evf-frontend-script', 'evf_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('evf_nonce'),
            'messages' => array(
                'loading' => __('Yükleniyor...', 'email-verification-forms'),
                'error' => __('Bir hata oluştu.', 'email-verification-forms'),
                'email_exists' => __('Bu e-posta adresi zaten kayıtlı.', 'email-verification-forms'),
                'invalid_email' => __('Geçerli bir e-posta adresi girin.', 'email-verification-forms'),
            ),
            'min_password_length' => get_option('evf_min_password_length', 8),
            'require_strong_password' => get_option('evf_require_strong_password', true),
        ));
    }

    /**
     * Login scripts
     */
    public function enqueue_login_scripts() {
        $this->enqueue_frontend_scripts();
    }

    /**
     * Rewrite rules ekle
     */
    public function add_rewrite_rules() {
        // Email verification endpoints
        add_rewrite_rule(
            '^email-verification/verify/([^/]+)/?$',
            'index.php?evf_action=verify&evf_token=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^email-verification/set-password/([^/]+)/?$',
            'index.php?evf_action=set_password&evf_token=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^email-verification/register/?$',
            'index.php?evf_action=register',
            'top'
        );

        // Query vars ekle
        add_rewrite_tag('%evf_action%', '([^&]+)');
        add_rewrite_tag('%evf_token%', '([^&]+)');
    }

    /**
     * Custom endpoints'leri handle et
     */
    public function handle_custom_endpoints() {
        $action = get_query_var('evf_action');

        if (!$action) {
            return;
        }

        switch ($action) {
            case 'verify':
                $token = get_query_var('evf_token');
                if ($token) {
                    $this->handle_email_verification(sanitize_text_field($token));
                }
                break;

            case 'set_password':
                $token = get_query_var('evf_token');
                if ($token) {
                    $this->handle_password_setup(sanitize_text_field($token));
                }
                break;

            case 'register':
                if (!evf_is_woocommerce_active()) {
                    $this->show_registration_page();
                }
                break;
        }
    }

    /**
     * E-posta doğrulama handle et
     */
    private function handle_email_verification($token) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Core: Email verification - Token: ' . $token);
        }

        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration) {
            $this->show_error_page(__('Geçersiz doğrulama bağlantısı.', 'email-verification-forms'));
            return;
        }

        if ($registration->status === 'completed') {
            $this->show_already_verified_page($registration->email);
            return;
        }

        // Token'ın süresi dolmuş mu kontrol et
        if (strtotime($registration->expires_at) < time()) {
            $this->show_error_page(__('Doğrulama bağlantısının süresi dolmuş.', 'email-verification-forms'));
            return;
        }

        // E-posta doğrulandı - status'u güncelle
        $database->mark_email_verified($registration->id);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Core: Email verified successfully for: ' . $registration->email);
        }

        // Parola belirleme sayfasına yönlendir
        wp_redirect(home_url('/email-verification/set-password/' . $token));
        exit;
    }

    /**
     * Parola belirleme handle et
     */
    private function handle_password_setup($token) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Core: Password setup - Token: ' . $token);
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
     * Kullanıcı doğrulama durumu kontrolü
     */
    public function check_user_verification_status() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Admin'ler exempt
        if (user_can($user_id, 'manage_options')) {
            return;
        }

        // Verified olmayan kullanıcıları logout yap
        if (!evf_is_user_verified($user_id)) {
            wp_logout();

            $redirect_url = evf_is_woocommerce_active() ?
                wc_get_page_permalink('myaccount') :
                wp_login_url();

            wp_redirect(add_query_arg('evf_error', 'not_verified', $redirect_url));
            exit;
        }
    }

    /**
     * AJAX: Kod doğrulama (SADECE WordPress mode)
     */
    public function ajax_verify_code() {
        if (evf_is_woocommerce_active()) {
            wp_send_json_error('use_woocommerce_handler');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Core: WordPress mode - ajax_verify_code called');
        }

        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);
        $code = sanitize_text_field($_POST['verification_code']);

        if (!is_email($email) || !$code) {
            wp_send_json_error('invalid_data');
        }

        // Kod formatı kontrolü
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            wp_send_json_error('invalid_code_format');
        }

        $database = EVF_Database::instance();

        // Maksimum deneme kontrolü
        if ($database->is_code_attempts_exceeded($email)) {
            $this->delete_registration_by_email($email);
            wp_send_json_error('max_attempts');
        }

        // Kodu doğrula
        $registration = $database->verify_code($email, $code);

        if (!$registration) {
            $database->increment_code_attempts($email);
            wp_send_json_error('invalid_code');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Core: Code verified successfully for: ' . $email);
        }

        // Başarılı doğrulama - parola belirleme sayfasına yönlendir
        $redirect_url = home_url('/email-verification/set-password/' . $registration->token);

        wp_send_json_success(array(
            'redirect_url' => $redirect_url
        ));
    }

    /**
     * AJAX: Kod tekrar gönderme (SADECE WordPress mode)
     */
    public function ajax_resend_code() {
        if (evf_is_woocommerce_active()) {
            wp_send_json_error('use_woocommerce_handler');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Core: WordPress mode - ajax_resend_code called');
        }

        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error('invalid_email');
        }

        // Rate limiting kontrolü
        if ($this->check_code_resend_limit($email)) {
            wp_send_json_error('rate_limit');
        }

        // Yeni kod gönder
        $database = EVF_Database::instance();
        $email_handler = EVF_Email::instance();

        // Mevcut kayıtları bul
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s AND status = 'pending' ORDER BY id DESC LIMIT 1",
            $email
        ));

        if (!$registration) {
            wp_send_json_error('registration_not_found');
        }

        // Yeni kod oluştur ve kaydet
        $new_code = $database->generate_verification_code();
        $database->save_verification_code($registration->id, $new_code);

        // E-posta gönder
        $result = $email_handler->send_verification_code_email($email, $new_code);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Yeni doğrulama kodu gönderildi!', 'email-verification-forms')
            ));
        } else {
            wp_send_json_error('email_send_failed');
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

        // E-posta var mı kontrol et
        if (email_exists($email)) {
            wp_send_json_error('email_exists');
        }

        wp_send_json_success('email_available');
    }

    /**
     * AJAX: Kullanıcı kaydı
     */
    public function ajax_register_user() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);

        if (!is_email($email) || !$first_name || !$last_name) {
            wp_send_json_error('invalid_data');
        }

        // E-posta var mı kontrol et
        if (email_exists($email)) {
            wp_send_json_error('email_exists');
        }

        // Rate limiting
        if ($this->check_registration_rate_limit($email)) {
            wp_send_json_error('rate_limit');
        }

        $database = EVF_Database::instance();
        $email_handler = EVF_Email::instance();

        // Verification type'ı belirle
        $verification_type = get_option('evf_verification_method', 'link');

        // Registration kaydı oluştur
        $registration_data = array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'verification_type' => $verification_type,
            'status' => 'pending',
            'token' => wp_generate_uuid4(),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+24 hours')),
            'created_at' => current_time('mysql')
        );

        $registration_id = $database->create_registration($registration_data);

        if (!$registration_id) {
            wp_send_json_error('registration_failed');
        }

        // E-posta gönder
        if ($verification_type === 'code') {
            $code = $database->generate_verification_code();
            $database->save_verification_code($registration_id, $code);
            $result = $email_handler->send_verification_code_email($email, $code);
        } else {
            $verification_url = home_url('/email-verification/verify/' . $registration_data['token']);
            $result = $email_handler->send_verification_email($email, $verification_url);
        }

        if (!$result) {
            wp_send_json_error('email_send_failed');
        }

        wp_send_json_success(array(
            'message' => __('Doğrulama e-postası gönderildi!', 'email-verification-forms'),
            'verification_type' => $verification_type,
            'email' => $email
        ));
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
        if (!$this->is_password_strong($password)) {
            wp_send_json_error('password_too_weak');
        }

        // Kullanıcı oluştur
        $username = sanitize_user($registration->email);
        $user_id = wp_create_user($username, $password, $registration->email);

        if (is_wp_error($user_id)) {
            wp_send_json_error('user_creation_failed');
        }

        // User meta'ları ekle
        update_user_meta($user_id, 'first_name', $registration->first_name);
        update_user_meta($user_id, 'last_name', $registration->last_name);
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
     * Parola güçlülük kontrolü
     */
    private function is_password_strong($password) {
        $min_length = get_option('evf_min_password_length', 8);

        if (strlen($password) < $min_length) {
            return false;
        }

        if (!get_option('evf_require_strong_password', true)) {
            return true;
        }

        // Strong password requirements
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (!preg_match('/\d/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Kod tekrar gönderme rate limit kontrolü
     */
    private function check_code_resend_limit($email) {
        $interval = get_option('evf_code_resend_interval', 5) * MINUTE_IN_SECONDS;
        $cache_key = 'evf_resend_limit_' . md5($email);

        $last_sent = get_transient($cache_key);

        if ($last_sent && (time() - $last_sent) < $interval) {
            return true;
        }

        set_transient($cache_key, time(), $interval);
        return false;
    }

    /**
     * Registration rate limit kontrolü
     */
    private function check_registration_rate_limit($email) {
        $cache_key = 'evf_reg_limit_' . md5($email);
        $attempts = get_transient($cache_key);

        if ($attempts >= 3) {
            return true;
        }

        set_transient($cache_key, ($attempts + 1), HOUR_IN_SECONDS);
        return false;
    }

    /**
     * Registration silme işlemi
     */
    private function delete_registration_by_email($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        $wpdb->delete($table_name, array('email' => $email), array('%s'));
    }

    /**
     * Otomatik hesap silme cron job'u
     */
    public function setup_auto_delete_cron() {
        if (!wp_next_scheduled('evf_auto_delete_unverified')) {
            wp_schedule_event(time(), 'hourly', 'evf_auto_delete_unverified');
        }
    }

    /**
     * Otomatik hesap silme işlemi
     */
    public function auto_delete_unverified_accounts() {
        if (!get_option('evf_auto_delete_unverified', false)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $delete_hours = get_option('evf_auto_delete_hours', 24);

        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$delete_hours} hours"));

        // Silinecek kayıtları bul
        $expired_registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = 'pending' 
             AND created_at < %s",
            $cutoff_date
        ));

        foreach ($expired_registrations as $registration) {
            // Kullanıcıyı sil (eğer varsa)
            if ($registration->user_id) {
                wp_delete_user($registration->user_id);
            }

            // Kayıt kaydını sil
            $wpdb->delete($table_name, array('id' => $registration->id), array('%d'));
        }

        // Log
        if (defined('WP_DEBUG') && WP_DEBUG && !empty($expired_registrations)) {
            error_log('EVF: Auto-deleted ' . count($expired_registrations) . ' unverified accounts');
        }
    }

    /**
     * Kod doğrulama sayfasını göster
     */
    public function show_code_verification_page($email, $last_code_sent = null) {
        include EVF_TEMPLATES_PATH . 'code-verification.php';
        exit;
    }

    /**
     * Hata sayfası göster
     */
    private function show_error_page($message) {
        include EVF_TEMPLATES_PATH . 'error-page.php';
        exit;
    }

    /**
     * Parola belirleme sayfası göster
     */
    private function show_password_setup_page($token, $email) {
        include EVF_TEMPLATES_PATH . 'password-setup.php';
        exit;
    }

    /**
     * Zaten doğrulanmış sayfası göster
     */
    private function show_already_verified_page($email) {
        include EVF_TEMPLATES_PATH . 'already-verified.php';
        exit;
    }

    /**
     * Registration sayfası göster
     */
    private function show_registration_page() {
        include EVF_TEMPLATES_PATH . 'registration-form.php';
        exit;
    }

    /**
     * WordPress registration redirect
     */
    public function redirect_registration() {
        wp_redirect(home_url('/email-verification/register/'));
        exit;
    }
}