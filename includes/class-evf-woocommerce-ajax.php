<?php
/**
 * EVF WooCommerce AJAX Handler - Part 2/4
 * AJAX işlemleri - DÜZELTİLMİŞ VERSİYON
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
     * AJAX hook'larını başlat
     */
    private function init_hooks() {
        // WooCommerce AJAX handlers
        add_action('wp_ajax_evf_verify_code', array($this, 'ajax_verify_code'));
        add_action('wp_ajax_nopriv_evf_verify_code', array($this, 'ajax_verify_code'));
        add_action('wp_ajax_evf_resend_code', array($this, 'ajax_resend_code'));
        add_action('wp_ajax_nopriv_evf_resend_code', array($this, 'ajax_resend_code'));
    }

    /**
     * DÜZELTME: WooCommerce için AJAX kod doğrulama handler'ı
     * Tüm hatalar giderildi
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

        // DÜZELTME: Kayıtlı kodu bul - Sadece pending status
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
                error_log('EVF WooCommerce AJAX: DB Code: "' . $registration->verification_code . '", Input Code: "' . $code . '"');
                error_log('EVF WooCommerce AJAX: Code expires at: ' . $registration->code_expires_at . ', Current time: ' . gmdate('Y-m-d H:i:s'));
            } else {
                error_log('EVF WooCommerce AJAX: No registration found for email: ' . $email);
            }
        }

        if (!$registration) {
            wp_send_json_error('registration_not_found');
        }

        // Kod süresini kontrol et - DÜZELTME: GMT kullan
        if ($registration->code_expires_at && strtotime($registration->code_expires_at) < time()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Code expired - Expires: ' . $registration->code_expires_at . ', Now: ' . gmdate('Y-m-d H:i:s'));
            }
            wp_send_json_error('code_expired');
        }

        // DÜZELTME: Kodu kontrol et - String comparison + trim
        $db_code = trim($registration->verification_code);
        $input_code = trim($code);

        if ($db_code !== $input_code) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Code mismatch - DB: "' . $db_code . '" (len:' . strlen($db_code) . '), Input: "' . $input_code . '" (len:' . strlen($input_code) . ')');
            }

            // Deneme sayısını artır
            $attempts = (int) ($registration->code_attempts ?? 0) + 1;
            $max_attempts = (int) get_option('evf_max_code_attempts', 5);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Attempt ' . $attempts . ' of ' . $max_attempts);
            }

            // DÜZELTME: Attempts update - Error handling ekle
            $update_result = $wpdb->update(
                $table_name,
                array('code_attempts' => $attempts),
                array('id' => $registration->id),
                array('%d'),
                array('%d')
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Attempts update result: ' . ($update_result !== false ? 'SUCCESS' : 'FAILED'));
            }

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
            $status_update = $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'email_verified_at' => current_time('mysql')
                ),
                array('id' => $registration->id),
                array('%s', '%s'),
                array('%d')
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce AJAX: Status update result: ' . ($status_update !== false ? 'SUCCESS' : 'FAILED'));
            }

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
     * DÜZELTME: WooCommerce için AJAX kod tekrar gönderme handler'ı
     * Rate limiting düzeltildi
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
        $wc_main = EVF_WooCommerce::instance();
        $email_handler = $wc_main->get_email_handler();

        if ($email_handler) {
            $result = $email_handler->send_verification_code_email(
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
        } else {
            wp_send_json_error('email_handler_not_found');
        }
    }

    /**
     * DÜZELTME: Rate limiting kontrolü - GMT time kullan
     */
    private function check_code_resend_limit($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $interval_minutes = (int) get_option('evf_code_resend_interval', 2);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Checking resend limit for: ' . $email . ', Interval: ' . $interval_minutes . ' minutes');
        }

        // DÜZELTME: GMT time kullan ve karşılaştırma doğru yap
        $cutoff_time = gmdate('Y-m-d H:i:s', strtotime("-{$interval_minutes} minutes"));

        $recent_send = $wpdb->get_var($wpdb->prepare(
            "SELECT last_code_sent FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code'
             AND status = 'pending'
             AND last_code_sent > %s
             ORDER BY last_code_sent DESC
             LIMIT 1",
            $email,
            $cutoff_time
        ));

        $has_limit = !empty($recent_send);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Rate limit check - Cutoff: ' . $cutoff_time . ', Recent send: ' . ($recent_send ?: 'NONE'));
            error_log('EVF WooCommerce: Rate limit result: ' . ($has_limit ? 'BLOCKED' : 'ALLOWED'));
        }

        return $has_limit;
    }

    /**
     * Countdown için remaining seconds hesapla
     */
    public function get_remaining_seconds($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $interval_minutes = (int) get_option('evf_code_resend_interval', 2);

        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT last_code_sent FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code'
             AND status = 'pending'
             ORDER BY created_at DESC 
             LIMIT 1",
            $email
        ));

        if (!$registration || !$registration->last_code_sent) {
            return 0;
        }

        // DÜZELTME: Timestamp calculation
        $last_sent_timestamp = strtotime($registration->last_code_sent);
        $current_timestamp = time();
        $interval_seconds = $interval_minutes * 60;

        $elapsed = $current_timestamp - $last_sent_timestamp;
        $remaining = max(0, $interval_seconds - $elapsed);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Remaining seconds calculation:');
            error_log('  Last sent: ' . $registration->last_code_sent . ' (timestamp: ' . $last_sent_timestamp . ')');
            error_log('  Current: ' . gmdate('Y-m-d H:i:s') . ' (timestamp: ' . $current_timestamp . ')');
            error_log('  Elapsed: ' . $elapsed . ' seconds');
            error_log('  Interval: ' . $interval_seconds . ' seconds');
            error_log('  Remaining: ' . $remaining . ' seconds');
        }

        return $remaining;
    }
}