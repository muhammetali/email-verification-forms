<?php
/**
 * EVF WooCommerce Core Class - Part 1/4
 * Ana sınıf ve hook'lar
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce {

    private static $instance = null;

    /**
     * Alt sınıf referansları
     */
    private $ajax_handler = null;
    private $email_handler = null;
    private $display_handler = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // WooCommerce'in tam yüklenmesini bekle
        add_action('woocommerce_loaded', array($this, 'init_hooks'));

        // Eğer WooCommerce zaten yüklenmişse direkt init et
        if (did_action('woocommerce_loaded')) {
            $this->init_hooks();
        }
    }

    /**
     * Alt sınıfları yükle
     */
    private function load_sub_classes() {
        // AJAX Handler
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

        // UI sınıfını yükle
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-ui.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-ui.php';
            EVF_WooCommerce_UI::instance();
        }

        // Password handler sınıfını yükle
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-password.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-password.php';
            EVF_WooCommerce_Password::instance();
        }
    }

    /**
     * WooCommerce hook'larını başlat
     */
    public function init_hooks() {
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Initializing hooks');
        }

        // Müşteri oluşturma hook'ları
        add_action('woocommerce_created_customer', array($this, 'handle_customer_registration'), 10, 3);
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_checkout_registration'), 10, 3);

        // wp_loaded sonrası hook'lar
        add_action('wp_loaded', array($this, 'init_late_hooks'), 5);

        // Alt sınıfları yükle
        $this->load_sub_classes();
    }

    /**
     * wp_loaded sonrası çalışacak hook'lar
     */
    public function init_late_hooks() {
        // WooCommerce email'lerini deaktif et
        $this->disable_woocommerce_customer_emails();

        // Custom verification endpoint
        $this->add_verification_endpoint();

        // Verification token handling - wp_loaded sonrası çalışacak
        add_action('template_redirect', array($this, 'handle_verification_redirect'), 1);

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Late hooks initialized');
        }
    }

    /**
     * WooCommerce müşteri email'lerini deaktif et
     */
    public function disable_woocommerce_customer_emails() {
        // Customer New Account email'ini kapat
        remove_action('woocommerce_created_customer_notification', array('WC_Emails', 'customer_new_account'), 10, 3);

        // Email sınıfları üzerinden deaktif et
        add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');

        // Email class'ını hook'dan çıkar
        if (class_exists('WC_Email_Customer_New_Account')) {
            remove_action('woocommerce_created_customer_notification', array('WC_Email_Customer_New_Account', 'trigger'));
        }

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Customer emails disabled');
        }
    }

    /**
     * WooCommerce müşteri kaydını handle et
     */
    public function handle_customer_registration($customer_id, $new_customer_data, $password_generated) {
        // Müşteri email'ini al
        $customer_email = $new_customer_data['user_email'];

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EVF WooCommerce: New customer registered - ID: $customer_id, Email: $customer_email, Password generated: " . ($password_generated ? 'YES' : 'NO'));
        }

        // Geçici güçlü parola oluştur ve kaydet
        $temp_password = wp_generate_password(16, true);
        wp_set_password($temp_password, $customer_id);

        // Kullanıcıyı unverified + password_change_required olarak işaretle
        update_user_meta($customer_id, 'evf_email_verified', 0);
        update_user_meta($customer_id, 'evf_password_change_required', 1);
        update_user_meta($customer_id, 'evf_temp_password_set', current_time('mysql'));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EVF WooCommerce: Temporary password set for user $customer_id");
        }

        // Email verification başlat
        $this->start_email_verification($customer_id, $customer_email);

        // WooCommerce'in kendi welcome email'ini biraz geciktir
        $this->delay_woocommerce_emails($customer_id);
    }

    /**
     * Checkout sırasında yapılan kayıtları handle et
     */
    public function handle_checkout_registration($order_id, $posted_data, $order) {
        // Checkout sırasında hesap oluşturuldu mu kontrol et
        if (!isset($posted_data['createaccount']) || !$posted_data['createaccount']) {
            return;
        }

        // Müşteri ID'sini al
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }

        $customer_email = $order->get_billing_email();

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EVF WooCommerce: Checkout registration - Order: $order_id, Customer: $customer_id, Email: $customer_email");
        }

        // Email verification başlat
        $this->start_email_verification($customer_id, $customer_email, array(
            'context' => 'checkout',
            'order_id' => $order_id
        ));
    }

    /**
     * Email verification sürecini başlat
     */
    public function start_email_verification($user_id, $email, $context = array()) {
        // User'ı unverified olarak işaretle
        update_user_meta($user_id, 'evf_email_verified', 0);
        update_user_meta($user_id, 'evf_verification_sent_at', current_time('timestamp'));
        update_user_meta($user_id, 'evf_verification_context', $context);

        // Doğrulama türünü kontrol et
        $verification_type = get_option('evf_verification_type', 'link');

        // Pending registrations tablosuna ekle
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        $token = wp_generate_password(32, false);
        $expires_at = gmdate('Y-m-d H:i:s', strtotime('+' . get_option('evf_token_expiry', 24) . ' hours'));

        // IP ve User Agent güvenli şekilde al
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Using verification type: ' . $verification_type);
        }

        // Kod doğrulama için ek veriler
        $verification_code = null;
        $code_expires_at = null;

        if ($verification_type === 'code') {
            if (class_exists('EVF_Database')) {
                $verification_code = EVF_Database::instance()->generate_verification_code();
            } else {
                $verification_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            }
            $code_expires_at = gmdate('Y-m-d H:i:s', strtotime('+30 minutes'));
        }

        // DÜZELTME: Tüm field'ları kontrol et
        $insert_data = array(
            'email' => $email,
            'token' => $token,
            'status' => 'pending',
            'user_id' => $user_id,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql'),
            'verification_type' => $verification_type,
            'verification_code' => $verification_code,
            'code_expires_at' => $code_expires_at,
            'last_code_sent' => $verification_type === 'code' ? current_time('mysql') : null,
            'code_attempts' => 0
        );

        $insert_format = array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert($table_name, $insert_data, $insert_format);

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($result === false) {
                error_log('EVF WooCommerce: Database insert failed: ' . $wpdb->last_error);
                error_log('EVF WooCommerce: Insert data: ' . print_r($insert_data, true));
            } else {
                error_log('EVF WooCommerce: Database insert successful, ID: ' . $wpdb->insert_id);
            }
        }

        // Email gönder
        if ($this->email_handler) {
            if ($verification_type === 'code') {
                $this->email_handler->send_verification_code_email($email, $verification_code, $user_id, $context);
            } else {
                $this->email_handler->send_verification_email($email, $token, $user_id, $context);
            }
        }

        // Admin'e bildirim gönder (eğer enabled ise)
        if (get_option('evf_admin_notifications', true) && $this->email_handler) {
            $this->email_handler->send_admin_notification($user_id, $email, $context);
        }
    }

    /**
     * Verification redirect'ini handle et
     */
    public function handle_verification_redirect() {
        // Sadece frontend'de çalış
        if (is_admin()) {
            return;
        }

        // URL'de evf_action var mı kontrol et
        if (!isset($_GET['evf_action'])) {
            return;
        }

        // WooCommerce'in yüklenmesini bekle
        if (!function_exists('WC') || !WC()) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_GET['evf_action']));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Verification redirect triggered with action: ' . $action);
        }

        switch ($action) {
            case 'wc_verify':
                if (isset($_GET['evf_token'])) {
                    $token = sanitize_text_field(wp_unslash($_GET['evf_token']));
                    $this->process_verification_token($token);
                }
                break;

            case 'set_password':
                if (isset($_GET['evf_token'])) {
                    $token = sanitize_text_field(wp_unslash($_GET['evf_token']));
                    // Password handler sınıfına yönlendir
                    if (class_exists('EVF_WooCommerce_Password')) {
                        EVF_WooCommerce_Password::instance()->handle_password_setup($token);
                    }
                }
                break;

            case 'wc_code_verify':
                // Magic link kod doğrulama sayfası
                if (isset($_GET['evf_email']) && $this->display_handler) {
                    $email = sanitize_email(wp_unslash($_GET['evf_email']));
                    $this->display_handler->show_code_verification_page($email);
                }
                break;
        }
    }

    /**
     * Verification token'ını işle
     */
    private function process_verification_token($token) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // Debug log - başlangıç
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Processing verification token: ' . substr($token, 0, 8) . '...');
        }

        // Token'ı kontrol et
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pending_verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status IN ('wc_pending', 'pending')",
            $token
        ));

        if (!$pending_verification) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Invalid token - not found in pending status');
            }
            wp_redirect(add_query_arg('evf_error', 'invalid_token', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Debug log - token bulundu
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Token found for user ID: ' . $pending_verification->user_id);
        }

        // Token süresini kontrol et
        if (strtotime($pending_verification->expires_at) < time()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Token expired');
            }
            wp_redirect(add_query_arg('evf_error', 'expired_token', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Verification'ı tamamla
        $user_id = $pending_verification->user_id;

        if ($user_id) {
            // User meta güncelle
            update_user_meta($user_id, 'evf_email_verified', 1);
            update_user_meta($user_id, 'evf_verified_at', current_time('mysql'));

            // Pending table güncelle
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'email_verified_at' => current_time('mysql')
                ),
                array('id' => $pending_verification->id),
                array('%s', '%s'),
                array('%d')
            );

            // Debug log - verification tamamlandı
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Verification completed for user ID: ' . $user_id);
            }

            // Parola değiştirme kontrolü
            $password_change_required = get_user_meta($user_id, 'evf_password_change_required', true);

            // Debug log - parola kontrolü
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Password change required check for user ' . $user_id . ': ' . ($password_change_required ? 'YES' : 'NO'));
            }

            // String '1' veya integer 1 kontrolü
            if ($password_change_required == 1 || $password_change_required === '1') {
                // Debug log - parola sayfasına yönlendiriliyor
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EVF WooCommerce: Redirecting to password setup page');
                }

                // Parola belirleme sayfasına yönlendir
                $redirect_url = add_query_arg(array(
                    'evf_action' => 'set_password',
                    'evf_token' => $token
                ), wc_get_page_permalink('myaccount'));

                wp_redirect($redirect_url);
                exit;
            } else {
                // Debug log - normal başarı sayfası
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EVF WooCommerce: No password change required, redirecting to success page');
                }

                // Normal başarı sayfasına yönlendir
                wp_redirect(add_query_arg('evf_success', 'verified', wc_get_page_permalink('myaccount')));
                exit;
            }
        } else {
            // Bu durumda user_id olmalı WooCommerce mode'da
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: No user ID found for token');
            }
            wp_redirect(add_query_arg('evf_error', 'invalid_state', wc_get_page_permalink('myaccount')));
            exit;
        }
    }

    /**
     * WooCommerce verification endpoint ekle
     */
    public function add_verification_endpoint() {
        add_rewrite_endpoint('email-verification', EP_PAGES);

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email verification endpoint added');
        }

        // Endpoint'leri flush et (sadece ilk seferde)
        if (get_option('evf_endpoints_flushed') !== 'yes') {
            flush_rewrite_rules();
            update_option('evf_endpoints_flushed', 'yes');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Rewrite rules flushed');
            }
        }
    }

    /**
     * WooCommerce email'lerini geciktir (çakışma olmasın)
     */
    private function delay_woocommerce_emails($customer_id) {
        // WooCommerce'in customer email'ini 5 dakika geciktir
        wp_schedule_single_event(
            time() + (5 * 60), // 5 dakika sonra
            'evf_delayed_wc_customer_email',
            array($customer_id)
        );
    }

    /**
     * Alt sınıflara erişim
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
}