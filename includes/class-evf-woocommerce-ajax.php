<?php
/**
 * EVF WooCommerce AJAX Handler
 * WooCommerce AJAX işlemleri - DÜZELTİLMİŞ VERSİYON
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_AJAX {

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
     * AJAX hook'larını başlat - SADECE WooCommerce mode'da
     */
    private function init_hooks() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Initializing AJAX handlers');
        }

        // WooCommerce için özel AJAX handlers
        add_action('wp_ajax_evf_verify_code', array($this, 'ajax_verify_code'));
        add_action('wp_ajax_nopriv_evf_verify_code', array($this, 'ajax_verify_code'));
        add_action('wp_ajax_evf_resend_code', array($this, 'ajax_resend_code'));
        add_action('wp_ajax_nopriv_evf_resend_code', array($this, 'ajax_resend_code'));
    }

    /**
     * AJAX: WooCommerce kod doğrulama handler'ı
     */
    public function ajax_verify_code() {
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

        // Maksimum deneme kontrolü
        $max_attempts = get_option('evf_max_code_attempts', 5);

        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code'
             AND status = 'pending'
             ORDER BY id DESC LIMIT 1",
            $email
        ));

        if (!$registration) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Registration not found for email: ' . $email);
            }
            wp_send_json_error('registration_not_found');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Registration found - ID: ' . $registration->id . ', Code attempts: ' . $registration->code_attempts);
        }

        // Max attempts kontrolü
        if ($registration->code_attempts >= $max_attempts) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Max attempts exceeded for email: ' . $email);
            }

            // Registration ve kullanıcıyı sil
            if ($registration->user_id) {
                wp_delete_user($registration->user_id);
            }
            $wpdb->delete($table_name, array('id' => $registration->id), array('%d'));

            wp_send_json_error('max_attempts');
        }

        // Kod doğrulama
        $is_valid_code = false;

        // Kod ve süre kontrolü
        if ($registration->verification_code === $code) {
            if ($registration->code_expires_at && strtotime($registration->code_expires_at) > time()) {
                $is_valid_code = true;
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EVF WooCommerce AJAX: Code expired - Expires at: ' . $registration->code_expires_at . ', Current: ' . current_time('mysql'));
                }
            }
        }

        if (!$is_valid_code) {
            // Yanlış kod - deneme sayısını artır
            $wpdb->update(
                $table_name,
                array(
                    'code_attempts' => $registration->code_attempts + 1,
                    'last_attempt_at' => current_time('mysql')
                ),
                array('id' => $registration->id),
                array('%d', '%s'),
                array('%d')
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Invalid code - Attempts incremented to: ' . ($registration->code_attempts + 1));
            }

            // Kod süresi dolmuşsa özel mesaj
            if ($registration->verification_code === $code && $registration->code_expires_at && strtotime($registration->code_expires_at) <= time()) {
                wp_send_json_error('code_expired');
            }

            wp_send_json_error('invalid_code');
        }

        // Başarılı doğrulama
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Code verified successfully for email: ' . $email);
        }

        // Kullanıcıyı verified olarak işaretle
        if ($registration->user_id) {
            update_user_meta($registration->user_id, 'evf_email_verified', 1);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: User ' . $registration->user_id . ' marked as verified');
            }
        }

        // Registration'ı completed olarak işaretle
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'email_verified_at' => current_time('mysql'),
                'code_attempts' => 0
            ),
            array('id' => $registration->id),
            array('%s', '%s', '%d'),
            array('%d')
        );

        // Welcome email gönder
        if ($registration->user_id) {
            $email_handler = EVF_Email::instance();
            $email_handler->send_welcome_email($registration->user_id);

            // Admin bildirimini gönder
            if (get_option('evf_admin_notifications', true)) {
                $email_handler->send_admin_notification($registration->user_id, $email);
            }
        }

        // Başarılı response
        wp_send_json_success(array(
            'redirect_url' => wc_get_page_permalink('myaccount'),
            'message' => __('E-posta doğrulandı! Hesabınıza yönlendiriliyorsunuz...', 'email-verification-forms')
        ));
    }

    /**
     * AJAX: WooCommerce kod tekrar gönderme handler'ı
     */
    public function ajax_resend_code() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: resend_code handler called');
        }

        // Nonce kontrolü
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Resend - Nonce verification failed');
            }
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Resend - Invalid email: ' . $email);
            }
            wp_send_json_error('invalid_email');
        }

        // Rate limiting kontrolü
        $resend_interval = get_option('evf_code_resend_interval', 5) * MINUTE_IN_SECONDS;
        $cache_key = 'evf_resend_limit_' . md5($email);

        $last_sent = get_transient($cache_key);

        if ($last_sent && (time() - $last_sent) < $resend_interval) {
            $remaining = $resend_interval - (time() - $last_sent);
            wp_send_json_error(array(
                'code' => 'rate_limit',
                'remaining_seconds' => $remaining
            ));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // Mevcut registration'ı bul
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code'
             AND status = 'pending'
             ORDER BY id DESC LIMIT 1",
            $email
        ));

        if (!$registration) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Resend - Registration not found for email: ' . $email);
            }
            wp_send_json_error('registration_not_found');
        }

        // Yeni kod oluştur
        $database = EVF_Database::instance();
        $new_code = $database->generate_verification_code();
        $code_expiry = gmdate('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Veritabanını güncelle
        $update_result = $wpdb->update(
            $table_name,
            array(
                'verification_code' => $new_code,
                'code_expires_at' => $code_expiry,
                'last_code_sent' => current_time('mysql')
            ),
            array('id' => $registration->id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($update_result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Resend - Database update failed');
            }
            wp_send_json_error('database_error');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Resend - New code generated: ' . $new_code . ' for email: ' . $email);
        }

        // E-posta gönder
        $email_handler = EVF_Email::instance();
        $result = $email_handler->send_verification_code_email($email, $new_code);

        if (!$result) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Resend - Email send failed');
            }
            wp_send_json_error('email_send_failed');
        }

        // Rate limiting transient'ini set et
        set_transient($cache_key, time(), $resend_interval);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce AJAX: Resend - Code sent successfully');
        }

        wp_send_json_success(array(
            'message' => __('Yeni doğrulama kodu gönderildi!', 'email-verification-forms')
        ));
    }
}