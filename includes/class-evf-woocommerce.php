<?php
/**
 * EVF WooCommerce Integration Class - TAM DÜZELTİLMİŞ VERSİYON + AJAX HANDLERS
 * WooCommerce entegrasyon sınıfı - Ana işlevsellik
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce {

    private static $instance = null;

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

        // YENİ: WooCommerce AJAX handlers ekle
        add_action('wp_ajax_evf_verify_code', array($this, 'ajax_verify_code'));
        add_action('wp_ajax_nopriv_evf_verify_code', array($this, 'ajax_verify_code'));
        add_action('wp_ajax_evf_resend_code', array($this, 'ajax_resend_code'));
        add_action('wp_ajax_nopriv_evf_resend_code', array($this, 'ajax_resend_code'));

        // DÜZELTME: wp_loaded sonrası hook'lar
        add_action('wp_loaded', array($this, 'init_late_hooks'), 5);

        // Alt sınıfları yükle
        $this->load_sub_classes();
    }

    /**
     * DÜZELTME: wp_loaded sonrası çalışacak hook'lar
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
            $verification_code = EVF_Database::instance()->generate_verification_code();
            $code_expires_at = gmdate('Y-m-d H:i:s', strtotime('+30 minutes'));
        }

        // DÜZELTME: code_attempts field'ını 0 olarak başlat
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert(
            $table_name,
            array(
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
                'code_attempts' => 0 // DÜZELTME: code_attempts field'ını 0 olarak başlat
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($result === false) {
                error_log('EVF WooCommerce: Database insert failed: ' . $wpdb->last_error);
            } else {
                error_log('EVF WooCommerce: Database insert successful, ID: ' . $wpdb->insert_id);
            }
        }

        // Email gönder
        if ($verification_type === 'code') {
            $this->send_woocommerce_verification_code_email($email, $verification_code, $user_id, $context);
        } else {
            $this->send_woocommerce_verification_email($email, $token, $user_id, $context);
        }

        // Admin'e bildirim gönder (eğer enabled ise)
        if (get_option('evf_admin_notifications', true)) {
            $this->send_admin_notification($user_id, $email, $context);
        }
    }

    /**
     * YENİ: WooCommerce için AJAX kod doğrulama handler'ı
     */
    public function ajax_verify_code() {
        // AJAX HANDLER DEBUG - BAŞLANGIÇ
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: verify_code handler called');
            error_log('EVF WooCommerce AJAX: POST data: ' . print_r($_POST, true));
        }

        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Nonce verification failed');
            }
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);
        $code = sanitize_text_field($_POST['verification_code']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Processing - Email: ' . $email . ', Code: ' . $code);
        }

        if (!is_email($email) || !$code) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Invalid data - Email valid: ' . (is_email($email) ? 'YES' : 'NO') . ', Code: ' . ($code ? 'YES' : 'NO'));
            }
            wp_send_json_error('invalid_data');
        }

        // Kod formatı kontrolü
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Invalid code format - Code: ' . $code);
            }
            wp_send_json_error('invalid_code_format');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // Kayıtlı kodu bul
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code' 
             AND status = 'pending'
             ORDER BY created_at DESC 
             LIMIT 1",
            $email
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($registration) {
                error_log('EVF WooCommerce AJAX: Registration found - ID: ' . $registration->id . ', Status: ' . $registration->status);
                error_log('EVF WooCommerce AJAX: Expected code: ' . $registration->verification_code . ', Got: ' . $code);
            } else {
                error_log('EVF WooCommerce AJAX: No registration found for email: ' . $email);
            }
        }

        if (!$registration) {
            wp_send_json_error('registration_not_found');
        }

        // Kod süresini kontrol et
        if ($registration->code_expires_at && strtotime($registration->code_expires_at) < time()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Code expired - Expires: ' . $registration->code_expires_at . ', Now: ' . gmdate('Y-m-d H:i:s'));
            }
            wp_send_json_error('code_expired');
        }

        // Kodu kontrol et
        if ($registration->verification_code !== $code) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Code mismatch - Expected: ' . $registration->verification_code . ', Got: ' . $code);
            }

            // Deneme sayısını artır
            $attempts = (int) ($registration->code_attempts ?? 0) + 1;
            $max_attempts = get_option('evf_max_code_attempts', 5);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Attempt ' . $attempts . ' of ' . $max_attempts);
            }

            $wpdb->update(
                $table_name,
                array('code_attempts' => $attempts),
                array('id' => $registration->id),
                array('%d'),
                array('%d')
            );

            if ($attempts >= $max_attempts) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EVF WooCommerce AJAX: Max attempts reached - Deleting registration and user');
                }
                // Maksimum deneme aşıldı - kayıtları sil
                $wpdb->delete($table_name, array('id' => $registration->id), array('%d'));
                if ($registration->user_id) {
                    wp_delete_user($registration->user_id);
                }
                wp_send_json_error('max_attempts');
            }

            wp_send_json_error('invalid_code');
        }

        // Kod doğru - verification'ı tamamla
        $user_id = $registration->user_id;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Code verified successfully for user: ' . $user_id);
        }

        if ($user_id) {
            // User meta güncelle
            update_user_meta($user_id, 'evf_email_verified', 1);
            update_user_meta($user_id, 'evf_verified_at', current_time('mysql'));

            // Pending table güncelle
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'email_verified_at' => current_time('mysql')
                ),
                array('id' => $registration->id),
                array('%s', '%s'),
                array('%d')
            );

            // Parola değiştirme kontrolü
            $password_change_required = get_user_meta($user_id, 'evf_password_change_required', true);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Password change required: ' . ($password_change_required ? 'YES' : 'NO'));
            }

            if ($password_change_required == 1 || $password_change_required === '1') {
                // Parola belirleme sayfasına yönlendir
                $redirect_url = add_query_arg(array(
                    'evf_action' => 'set_password',
                    'evf_token' => $registration->token
                ), wc_get_page_permalink('myaccount'));
            } else {
                // Normal başarı sayfasına yönlendir
                $redirect_url = add_query_arg('evf_success', 'verified', wc_get_page_permalink('myaccount'));
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Sending success response with redirect: ' . $redirect_url);
            }

            wp_send_json_success(array(
                'redirect_url' => $redirect_url
            ));
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Invalid state - no user_id');
            }
            wp_send_json_error('invalid_state');
        }
    }

    /**
     * YENİ: WooCommerce için AJAX kod tekrar gönderme handler'ı
     */
    public function ajax_resend_code() {
        // AJAX HANDLER DEBUG - BAŞLANGIÇ
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: resend_code handler called');
            error_log('EVF WooCommerce AJAX: POST data: ' . print_r($_POST, true));
        }

        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Nonce verification failed');
            }
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Resend code for email: ' . $email);
        }

        if (!is_email($email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Invalid email format');
            }
            wp_send_json_error('invalid_email');
        }

        // Rate limiting kontrolü
        if ($this->check_code_resend_limit($email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Rate limit exceeded for email: ' . $email);
            }
            wp_send_json_error('rate_limit');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // Mevcut kayıtları bul
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code' 
             AND status = 'pending'
             ORDER BY created_at DESC 
             LIMIT 1",
            $email
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($registration) {
                error_log('EVF WooCommerce AJAX: Registration found for resend - ID: ' . $registration->id);
            } else {
                error_log('EVF WooCommerce AJAX: No registration found for resend');
            }
        }

        if (!$registration) {
            wp_send_json_error('registration_not_found');
        }

        // Yeni kod oluştur
        if (class_exists('EVF_Database')) {
            $new_code = EVF_Database::instance()->generate_verification_code();
        } else {
            $new_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        $code_expires_at = gmdate('Y-m-d H:i:s', strtotime('+30 minutes'));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Generated new code: ' . $new_code . ', Expires: ' . $code_expires_at);
        }

        // Veritabanını güncelle
        $update_result = $wpdb->update(
            $table_name,
            array(
                'verification_code' => $new_code,
                'code_expires_at' => $code_expires_at,
                'last_code_sent' => current_time('mysql'),
                'code_attempts' => 0 // Reset attempts
            ),
            array('id' => $registration->id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Database update result: ' . ($update_result !== false ? 'SUCCESS' : 'FAILED'));
        }

        // E-posta gönder
        $result = $this->send_woocommerce_verification_code_email(
            $email,
            $new_code,
            $registration->user_id,
            json_decode($registration->context ?? '[]', true) ?: array()
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Resend email result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('send_failed');
        }
    }

    /**
     * YENİ: Rate limiting kontrolü
     */
    private function check_code_resend_limit($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $interval_minutes = get_option('evf_code_resend_interval', 2);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Checking resend limit for: ' . $email . ', Interval: ' . $interval_minutes . ' minutes');
        }

        $recent_send = $wpdb->get_var($wpdb->prepare(
            "SELECT last_code_sent FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code'
             AND status = 'pending'
             AND last_code_sent > %s",
            $email,
            gmdate('Y-m-d H:i:s', strtotime("-{$interval_minutes} minutes"))
        ));

        $has_limit = !empty($recent_send);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Rate limit check result: ' . ($has_limit ? 'BLOCKED' : 'ALLOWED'));
        }

        return $has_limit;
    }

    /**
     * WooCommerce verification email'i gönder
     */
    private function send_woocommerce_verification_email($email, $token, $user_id, $context = array()) {
        // WooCommerce My Account URL'ini kullan
        $verification_url = add_query_arg(array(
            'evf_action' => 'wc_verify',
            'evf_token' => $token
        ), wc_get_page_permalink('myaccount'));

        $user = get_userdata($user_id);
        $user_name = $user->display_name ?: $user->user_login;

        /* translators: %s: Site name */
        $subject = sprintf(__('[%s] E-posta Adresinizi Doğrulayın', 'email-verification-forms'), get_bloginfo('name'));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Sending verification email to ' . $email . ' with URL: ' . $verification_url);
        }

        // WooCommerce email template yapısını kullan
        $email_content = $this->get_woocommerce_email_template($verification_url, $user_name, $email, $context);

        // WooCommerce email class'ını kullan
        $mailer = WC()->mailer();
        $wrapped_message = $mailer->wrap_message($subject, $email_content);

        $result = $mailer->send($email, $subject, $wrapped_message);

        // Email log'a kaydet
        if (class_exists('EVF_Database')) {
            EVF_Database::instance()->log_email($email, 'wc_verification', $result ? 'sent' : 'failed', null, $user_id);
        }

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email sent result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }

        return $result;
    }

    /**
     * WooCommerce email template'i
     */
    private function get_woocommerce_email_template($verification_url, $user_name, $email, $context) {
        $site_name = get_bloginfo('name');
        $brand_color = get_option('evf_brand_color', '#96588a');

        $context_message = '';
        if (isset($context['context']) && $context['context'] === 'checkout') {
            $context_message = '<p style="margin-bottom: 20px; color: #666;">' .
                __('Siparişiniz başarıyla alındı. Hesabınızın güvenliği için e-posta adresinizi doğrulamanız gerekmektedir.', 'email-verification-forms') .
                '</p>';
        }

        return sprintf('
            <div style="background-color: #f7f7f7; margin: 0; padding: 70px 0; width: 100%%;">
                <table border="0" cellpadding="0" cellspacing="0" height="100%%" width="100%%">
                    <tr>
                        <td align="center" valign="top">
                            <div style="max-width: 600px; background-color: #ffffff; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin: 0 auto;">
                                <!-- Header -->
                                <div style="background: linear-gradient(135deg, %s, %s); padding: 30px; text-align: center; border-radius: 6px 6px 0 0;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 300;">
                                        🛡️ E-posta Doğrulaması
                                    </h1>
                                </div>
                                
                                <!-- Content -->
                                <div style="padding: 40px 30px;">
                                    <h2 style="color: #333; margin: 0 0 20px 0; font-size: 18px;">
                                        Merhaba %s,
                                    </h2>
                                    
                                    %s
                                    
                                    <p style="margin-bottom: 30px; color: #666; line-height: 1.6;">
                                        <strong>%s</strong> hesabınızın güvenliği için e-posta adresinizi doğrulamanız gerekmektedir. 
                                        Bu işlem sadece birkaç saniye sürer ve hesabınızı güvence altına alır.
                                    </p>
                                    
                                    <!-- CTA Button -->
                                    <div style="text-align: center; margin: 30px 0;">
                                        <a href="%s" style="background: linear-gradient(135deg, %s, %s); color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: 600; display: inline-block; box-shadow: 0 2px 8px rgba(150,88,138,0.3);">
                                            ✅ E-postamı Doğrula
                                        </a>
                                    </div>
                                    
                                    <!-- Alternative Link -->
                                    <div style="background-color: #f9f9f9; padding: 20px; border-radius: 4px; margin: 20px 0; border-left: 4px solid %s;">
                                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                            Butona tıklayamıyorsanız, aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:
                                        </p>
                                        <p style="margin: 0; font-size: 12px; color: #999; word-break: break-all;">
                                            %s
                                        </p>
                                    </div>
                                    
                                    <!-- Benefits -->
                                    <div style="background-color: #f0f8ff; padding: 20px; border-radius: 4px; margin: 20px 0;">
                                        <h3 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">
                                            ✨ Doğrulama Sonrası Avantajlarınız:
                                        </h3>
                                        <ul style="margin: 0; padding-left: 20px; color: #666;">
                                            <li style="margin-bottom: 8px;">Hesabınız tam güvenlik altında</li>
                                            <li style="margin-bottom: 8px;">Tüm özellikler aktif</li>
                                            <li style="margin-bottom: 8px;">Önemli bildirimleri kaçırmayın</li>
                                            <li style="margin-bottom: 0;">VIP müşteri desteği</li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Warning -->
                                    <div style="border-left: 4px solid #f39c12; background-color: #fef9e7; padding: 15px; margin: 20px 0;">
                                        <p style="margin: 0; font-size: 14px; color: #8a6d3b;">
                                            <strong>⏰ Önemli:</strong> Bu bağlantı %d saat geçerlidir. 
                                            Süre dolmadan önce doğrulamayı tamamlayın.
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Footer -->
                                <div style="background-color: #f8f8f8; padding: 20px 30px; text-align: center; border-radius: 0 0 6px 6px; border-top: 1px solid #eee;">
                                    <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                        Bu e-posta <strong>%s</strong> tarafından gönderilmiştir.
                                    </p>
                                    <p style="margin: 0; font-size: 12px; color: #999;">
                                        <a href="%s" style="color: %s; text-decoration: none;">%s</a>
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>',
            esc_attr($brand_color),
            esc_attr($brand_color),
            esc_html($user_name),
            $context_message,
            esc_html($site_name),
            esc_url($verification_url),
            esc_attr($brand_color),
            esc_attr($brand_color),
            esc_attr($brand_color),
            esc_url($verification_url),
            get_option('evf_token_expiry', 24),
            esc_html($site_name),
            esc_url(home_url()),
            esc_attr($brand_color),
            esc_html(home_url())
        );
    }

    /**
     * DÜZELTME: Verification redirect'ini handle et - template_redirect kullan
     */
    public function handle_verification_redirect() {
        // Sadlade frontend'de çalış
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
                if (isset($_GET['evf_email'])) {
                    $email = sanitize_email(wp_unslash($_GET['evf_email']));
                    $this->show_code_verification_page($email);
                }
                break;
        }
    }

    /**
     * DÜZELTME: Kod doğrulama sayfasını göster - Status kontrolü eklendi
     */
    private function show_code_verification_page($email) {
        // Email'in pending registrations'da olup olmadığını kontrol et
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
         WHERE email = %s 
         AND verification_type = 'code' 
         ORDER BY created_at DESC 
         LIMIT 1",
            $email
        ));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($registration) {
                error_log('EVF WooCommerce: Magic link clicked - Email: ' . $email . ', Status: ' . $registration->status);
            } else {
                error_log('EVF WooCommerce: Magic link clicked - No registration found for: ' . $email);
            }
        }

        if (!$registration) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: No registration found, redirecting with error');
            }
            wp_redirect(add_query_arg('evf_error', 'registration_not_found', wc_get_page_permalink('myaccount')));
            exit;
        }

        // DÜZELTME: Status kontrolü ekle
        if ($registration->status === 'completed') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Registration already completed, showing already verified page');
            }
            $this->show_already_verified_page($email);
            return;
        }

        if ($registration->status !== 'pending') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Invalid registration status: ' . $registration->status);
            }
            wp_redirect(add_query_arg('evf_error', 'invalid_status', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Template variables
        $email = $registration->email;
        $last_code_sent = $registration->last_code_sent;

        // Template'i include et
        $template_path = EVF_TEMPLATES_PATH . 'code-verification.php';

        if (file_exists($template_path)) {
            // WP head/footer'ı deaktif ederek sadece template'i göster
            define('EVF_CODE_VERIFICATION_PAGE', true);
            include $template_path;
            exit;
        } else {
            // Fallback - basit HTML sayfası göster
            $this->show_simple_code_verification_page($email);
        }
    }

    /**
     * YENİ: Zaten doğrulanmış sayfasını göster
     */
    private function show_already_verified_page($email) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Zaten Doğrulanmış</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
        <div style="max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; text-align: center;">
            <h2>✅ E-posta Zaten Doğrulanmış</h2>
            <p><strong><?php echo esc_html($email); ?></strong> e-posta adresi zaten doğrulanmış durumda.</p>

            <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 0;">🎉 Hesabınız aktif ve kullanıma hazır!</p>
            </div>

            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
               style="display: inline-block; background: #0073aa; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px;">
                Hesabıma Git
            </a>
        </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Basit kod doğrulama sayfası (fallback)
     */
    private function show_simple_code_verification_page($email) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Kod Doğrulama</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
        <div style="max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;">
            <h2>🔐 Doğrulama Kodu Girin</h2>
            <p><strong><?php echo esc_html($email); ?></strong> adresine gönderilen 6 haneli kodu girin:</p>

            <form id="code-form" style="margin: 20px 0;">
                <input type="text"
                       id="code-input"
                       placeholder="123456"
                       maxlength="6"
                       style="width: 100%; padding: 15px; font-size: 18px; text-align: center; border: 2px solid #ddd; border-radius: 5px;">
                <button type="submit" style="width: 100%; padding: 15px; background: #0073aa; color: white; border: none; border-radius: 5px; font-size: 16px; margin-top: 15px;">
                    Doğrula
                </button>
            </form>

            <div id="message" style="padding: 10px; margin: 10px 0; border-radius: 5px; display: none;"></div>

            <button id="resend-btn" style="width: 100%; padding: 10px; background: #666; color: white; border: none; border-radius: 5px; margin-top: 10px; display: none;">
                Yeni Kod Gönder
            </button>
        </div>

        <script>
            const codeForm = document.getElementById('code-form');
            const codeInput = document.getElementById('code-input');
            const messageDiv = document.getElementById('message');
            const resendBtn = document.getElementById('resend-btn');

            // AJAX DEBUG - Console log ekle
            console.log('EVF Code Verification: Page loaded for email:', '<?php echo esc_js($email); ?>');

            codeForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const code = codeInput.value;
                console.log('EVF Code Verification: Form submitted with code:', code);

                if (code.length !== 6) {
                    showMessage('Lütfen 6 haneli kodu girin.', 'error');
                    return;
                }

                // AJAX request to verify code
                console.log('EVF Code Verification: Sending AJAX request...');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'evf_verify_code',
                        nonce: '<?php echo wp_create_nonce('evf_nonce'); ?>',
                        email: '<?php echo esc_js($email); ?>',
                        verification_code: code
                    })
                })
                    .then(response => {
                        console.log('EVF Code Verification: AJAX response received:', response);
                        return response.json();
                    })
                    .then(data => {
                        console.log('EVF Code Verification: AJAX data:', data);

                        if (data.success) {
                            showMessage('Kod doğrulandı! Yönlendiriliyor...', 'success');

                            setTimeout(() => {
                                window.location.href = data.data.redirect_url || '<?php echo wc_get_page_permalink('myaccount'); ?>';
                            }, 2000);
                        } else {
                            console.log('EVF Code Verification: AJAX error:', data.data);

                            let errorMessage = 'Geçersiz kod. Lütfen tekrar deneyin.';
                            if (data.data === 'code_expired') {
                                errorMessage = 'Kod süresi dolmuş. Yeni kod isteyiniz.';
                                resendBtn.style.display = 'block';
                            } else if (data.data === 'max_attempts') {
                                errorMessage = 'Çok fazla yanlış deneme. Kayıt iptal edildi.';
                            }

                            showMessage(errorMessage, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('EVF Code Verification: AJAX error:', error);
                        showMessage('Bir hata oluştu.', 'error');
                    });
            });

            resendBtn.addEventListener('click', function() {
                console.log('EVF Code Verification: Resend button clicked');

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'evf_resend_code',
                        nonce: '<?php echo wp_create_nonce('evf_nonce'); ?>',
                        email: '<?php echo esc_js($email); ?>'
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('EVF Code Verification: Resend response:', data);

                        if (data.success) {
                            showMessage('Yeni kod gönderildi!', 'success');
                            resendBtn.style.display = 'none';
                        } else {
                            showMessage('Kod gönderilemedi. Lütfen tekrar deneyin.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('EVF Code Verification: Resend error:', error);
                        showMessage('Bir hata oluştu.', 'error');
                    });
            });

            function showMessage(text, type) {
                messageDiv.style.display = 'block';
                messageDiv.textContent = text;

                if (type === 'success') {
                    messageDiv.style.background = '#d1fae5';
                    messageDiv.style.color = '#065f46';
                } else {
                    messageDiv.style.background = '#fee2e2';
                    messageDiv.style.color = '#991b1b';
                }
            }
        </script>
        </body>
        </html>
        <?php
        exit;
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
     * Admin'e WooCommerce notification gönder
     */
    private function send_admin_notification($user_id, $email, $context) {
        $user = get_userdata($user_id);
        $admin_email = get_option('admin_email');

        /* translators: %s: Site name */
        $subject = sprintf(__('[%s] Yeni WooCommerce Müşteri Kaydı', 'email-verification-forms'), get_bloginfo('name'));

        $context_info = '';
        if (isset($context['context']) && $context['context'] === 'checkout') {
            /* translators: %s: Order ID */
            $context_info = sprintf(__('Sipariş ID: %s<br>', 'email-verification-forms'), $context['order_id']);
        }

        /* translators: 1: Site name, 2: User name, 3: Email, 4: Date, 5: User ID, 6: Context info, 7: Profile URL */
        $message = sprintf('
            <h2>Yeni WooCommerce Müşteri Kaydı</h2>
            <p><strong>%1$s</strong> mağazanıza yeni bir müşteri kaydoldu:</p>
            
            <table style="border-collapse: collapse; width: 100%%; margin: 20px 0;">
                <tr style="background: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Müşteri Adı:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%2$s</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>E-posta:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%3$s</td>
                </tr>
                <tr style="background: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Kayıt Tarihi:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%4$s</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Kullanıcı ID:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">#%5$d</td>
                </tr>
            </table>
            
            %6$s
            
            <p><strong>🛡️ E-posta doğrulama bağlantısı müşteriye gönderildi.</strong></p>
            
            <p><a href="%7$s" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Müşteri Profilini Görüntüle</a></p>',
            get_bloginfo('name'),
            esc_html($user->display_name ?: $user->user_login),
            esc_html($email),
            current_time('d.m.Y H:i'),
            $user_id,
            $context_info,
            admin_url('user-edit.php?user_id=' . $user_id)
        );

        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * WooCommerce verification code email'i gönder
     */
    private function send_woocommerce_verification_code_email($email, $code, $user_id, $context = array()) {
        $user = get_userdata($user_id);
        $user_name = $user->display_name ?: $user->user_login;

        /* translators: %s: Site name */
        $subject = sprintf(__('[%s] E-posta Doğrulama Kodu', 'email-verification-forms'), get_bloginfo('name'));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Sending verification code email to ' . $email . ' with code: ' . $code);
        }

        // WooCommerce email template yapısını kullan
        $email_content = $this->get_woocommerce_code_email_template($code, $user_name, $email, $context);

        // WooCommerce email class'ını kullan
        $mailer = WC()->mailer();
        $wrapped_message = $mailer->wrap_message($subject, $email_content);

        $result = $mailer->send($email, $subject, $wrapped_message);

        // Email log'a kaydet
        if (class_exists('EVF_Database')) {
            EVF_Database::instance()->log_email($email, 'wc_code_verification', $result ? 'sent' : 'failed', null, $user_id);
        }

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Code email sent result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }

        return $result;
    }

    /**
     * DÜZELTME: WooCommerce kod email template'i - Net işleyiş
     */
    private function get_woocommerce_code_email_template($code, $user_name, $email, $context) {
        $site_name = get_bloginfo('name');
        $brand_color = get_option('evf_brand_color', '#96588a');

        // Magic link oluştur - kod doğrulama sayfasına direkt gider
        $code_verification_url = add_query_arg(array(
            'evf_action' => 'wc_code_verify',
            'evf_email' => urlencode($email)
        ), wc_get_page_permalink('myaccount'));

        $context_message = '';
        if (isset($context['context']) && $context['context'] === 'checkout') {
            $context_message = '<p style="margin-bottom: 20px; color: #666;">' .
                __('Siparişiniz başarıyla alındı. Hesabınızın güvenliği için e-posta adresinizi doğrulamanız gerekmektedir.', 'email-verification-forms') .
                '</p>';
        }

        // Net işleyiş: Magic Link → Kod girme sayfası → Kod doğrulama
        return sprintf('
        <div style="background-color: #f7f7f7; margin: 0; padding: 70px 0; width: 100%%;">
            <table border="0" cellpadding="0" cellspacing="0" height="100%%" width="100%%">
                <tr>
                    <td align="center" valign="top">
                        <div style="max-width: 600px; background-color: #ffffff; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin: 0 auto;">
                            <!-- Header -->
                            <div style="background: linear-gradient(135deg, %s, %s); padding: 30px; text-align: center; border-radius: 6px 6px 0 0;">
                                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 300;">
                                    🛡️ E-posta Doğrulaması
                                </h1>
                            </div>
                            
                            <!-- Content -->
                            <div style="padding: 40px 30px;">
                                <h2 style="color: #333; margin: 0 0 20px 0; font-size: 18px;">
                                    Merhaba %s,
                                </h2>
                                
                                %s
                                
                                <p style="margin-bottom: 30px; color: #666; line-height: 1.6;">
                                    <strong>%s</strong> hesabınızın güvenliği için e-posta adresinizi doğrulamanız gerekmektedir. 
                                    Aşağıdaki butona tıklayarak doğrulama sayfasına gidin ve size gönderilen kodu girin.
                                </p>
      
                                <!-- Magic Link Button -->
                                <div style="text-align: center; margin: 40px 0;">
                                    <a href="%s" 
                                       style="background: linear-gradient(135deg, %s, %s); 
                                              color: #ffffff; 
                                              padding: 15px 40px; 
                                              text-decoration: none; 
                                              border-radius: 6px; 
                                              font-weight: 600; 
                                              display: inline-block; 
                                              box-shadow: 0 4px 12px rgba(150,88,138,0.3);
                                              font-size: 16px;">
                                        🔓 Doğrulama Sayfasına Git
                                    </a>
                                </div>
                                
                                <!-- Verification Code Display -->
                                <div style="background: #f8f9fa; border: 2px solid %s; border-radius: 8px; padding: 25px; margin: 30px 0; text-align: center;">
                                    <h3 style="color: #333; margin: 0 0 15px 0; font-size: 16px;">
                                        📧 Doğrulama Kodunuz:
                                    </h3>
                                    <div style="font-size: 32px; font-weight: bold; color: %s; letter-spacing: 8px; font-family: monospace; background: white; padding: 15px; border-radius: 6px; border: 1px solid #e9ecef;">
                                        %s
                                    </div>
                                    <p style="margin: 15px 0 0 0; font-size: 14px; color: #666;">
                                        Bu kodu doğrulama sayfasında girin
                                    </p>
                                </div>
                                
                                <!-- Instructions -->
                                <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 30px 0;">
                                    <h4 style="color: #1976d2; margin: 0 0 10px 0; font-size: 16px;">
                                        📋 Doğrulama Adımları:
                                    </h4>
                                    <ol style="color: #424242; line-height: 1.6; margin: 0; padding-left: 20px;">
                                        <li>Yukarıdaki butona tıklayın</li>
                                        <li>Açılan sayfada <strong>%s</strong> kodunu girin</li>
                                        <li>"Doğrula" butonuna basın</li>
                                    </ol>
                                </div>
                                
                                <!-- Important Notice -->
                                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 20px 0;">
                                    <p style="margin: 0; font-size: 14px; color: #856404;">
                                        <strong>⚠️ Önemli:</strong> Bu kod 30 dakika geçerlidir. Süre dolmadan önce doğrulama işlemini tamamlayın.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Footer -->
                            <div style="background-color: #f8f8f8; padding: 20px 30px; text-align: center; border-radius: 0 0 6px 6px; border-top: 1px solid #eee;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                    Bu e-posta <strong>%s</strong> tarafından gönderilmiştir.
                                </p>
                                <p style="margin: 0; font-size: 12px; color: #999;">
                                    <a href="%s" style="color: %s; text-decoration: none;">%s</a>
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>',
            // Parametreler:
            esc_attr($brand_color), // 1. Header gradient 1
            esc_attr($brand_color), // 2. Header gradient 2
            esc_html($user_name), // 3. User name
            $context_message, // 4. Context message
            esc_html($site_name), // 5. Site name
            esc_url($code_verification_url), // 6. Magic Link URL
            esc_attr($brand_color), // 7. Button gradient 1
            esc_attr($brand_color), // 8. Button gradient 2
            esc_attr($brand_color), // 9. Code box border
            esc_attr($brand_color), // 10. Code color
            esc_html($code), // 11. Verification code
            esc_html($code), // 12. Code in instructions
            esc_html($site_name), // 13. Footer site name
            esc_url(home_url()), // 14. Footer home URL
            esc_attr($brand_color), // 15. Footer link color
            esc_html(home_url()) // 16. Footer home URL text
        );
    }
}