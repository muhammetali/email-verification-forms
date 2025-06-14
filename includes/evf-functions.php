<?php
/**
 * EVF Helper Functions
 * Global helper fonksiyonları
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce aktif mi kontrol et
 */
if (!function_exists('evf_is_woocommerce_active')) {
    function evf_is_woocommerce_active() {
        return class_exists('WooCommerce') && in_array('woocommerce/woocommerce.php', get_option('active_plugins', array()));
    }
}

/**
 * Kullanıcı verified mi kontrol et
 */
if (!function_exists('evf_is_user_verified')) {
    function evf_is_user_verified($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // Admin'ler her zaman verified
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        return (bool) get_user_meta($user_id, 'evf_email_verified', true);
    }
}

/**
 * Email validation
 */
if (!function_exists('evf_is_valid_email')) {
    function evf_is_valid_email($email) {
        return is_email($email) && !empty($email);
    }
}

/**
 * Güvenli redirect
 */
if (!function_exists('evf_safe_redirect')) {
    function evf_safe_redirect($url, $status = 302) {
        if (!$url) {
            return false;
        }

        wp_redirect(esc_url_raw($url), $status);
        exit;
    }
}

/**
 * Debug log
 */
if (!function_exists('evf_log')) {
    function evf_log($message, $data = null) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = 'EVF: ' . $message;
        if ($data) {
            $log_message .= ' - Data: ' . print_r($data, true);
        }

        error_log($log_message);
    }
}