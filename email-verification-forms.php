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

/**
 * Ana Plugin Sınıfı
 */
final class EmailVerificationForms {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * WooCommerce aktif mi?
     */
    private $is_woocommerce_active = false;

    /**
     * Plugin modu (wordpress/woocommerce)
     */
    private $plugin_mode = 'wordpress';

    /**
     * Singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->detect_environment();
        $this->define_global_functions(); // Global functions'ları erken tanımla
        $this->init_hooks();
        $this->includes();
    }

    /**
     * Çevreyi tespit et (WooCommerce var mı?)
     */
    private function detect_environment() {
        // WooCommerce detection - daha güvenli yöntem
        $this->is_woocommerce_active = (
            class_exists('WooCommerce') ||
            in_array('woocommerce/woocommerce.php', get_option('active_plugins', array())) ||
            is_plugin_active_for_network('woocommerce/woocommerce.php')
        );

        $this->plugin_mode = $this->is_woocommerce_active ? 'woocommerce' : 'wordpress';

        // Debug log (sadece WP_DEBUG aktifse)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'EVF: Environment detected - Mode: %s, WooCommerce class: %s, WooCommerce in active plugins: %s',
                $this->plugin_mode,
                class_exists('WooCommerce') ? 'YES' : 'NO',
                in_array('woocommerce/woocommerce.php', get_option('active_plugins', array())) ? 'YES' : 'NO'
            ));
        }
    }

    /**
     * Global helper functions'ları tanımla
     */
    private function define_global_functions() {
        // WooCommerce aktif mi kontrol
        if (!function_exists('evf_is_woocommerce_active')) {
            function evf_is_woocommerce_active() {
                return EVF()->is_woocommerce_active;
            }
        }

        // Plugin modunu al
        if (!function_exists('evf_get_plugin_mode')) {
            function evf_get_plugin_mode() {
                return EVF()->plugin_mode;
            }
        }

        // Kullanıcı doğrulandı mı?
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

        // Plugin instance'ını al
        if (!function_exists('EVF')) {
            function EVF() {
                return EmailVerificationForms::instance();
            }
        }
    }

    /**
     * Ana hook'ları kaydet
     */
    private function init_hooks() {
        // Plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, 'EmailVerificationForms::uninstall');

        // Internationalization
        add_action('plugins_loaded', array($this, 'load_textdomain'));

        // Plugin init
        add_action('init', array($this, 'init'), 0);

        // WooCommerce environment change tracking
        add_action('activated_plugin', array($this, 'check_environment_change'));
        add_action('deactivated_plugin', array($this, 'check_environment_change'));
    }

    /**
     * Gerekli dosyaları include et
     */
    private function includes() {
        // Core sınıfları
        require_once EVF_INCLUDES_PATH . 'class-evf-database.php';
        require_once EVF_INCLUDES_PATH . 'class-evf-email.php';
        require_once EVF_INCLUDES_PATH . 'class-evf-core.php';

        // Mode'a göre sınıfları yükle
        if ($this->is_woocommerce_active) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce.php';
        } else {
            require_once EVF_INCLUDES_PATH . 'class-evf-registration.php';
        }
    }

    /**
     * Plugin'i başlat
     */
    public function init() {
        // Database'i hazırla
        EVF_Database::instance();

        // Email sınıfını başlat
        EVF_Email::instance();

        // Core'u başlat
        EVF_Core::instance();

        // Mode'a göre başlat
        if ($this->is_woocommerce_active) {
            // WooCommerce entegrasyonu (sadece WooCommerce aktifse)
            if (did_action('woocommerce_loaded')) {
                require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce.php';
                EVF_WooCommerce::instance();
            } else {
                add_action('woocommerce_loaded', function() {
                    require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce.php';
                    EVF_WooCommerce::instance();
                });
            }
        } else {
            EVF_Registration::instance();
        }

        // Admin paneli (sadece admin'de)
        if (is_admin()) {
            require_once EVF_INCLUDES_PATH . 'class-evf-admin.php';
            EVF_Admin::instance();
        }
    }

    /**
     * Plugin aktivasyonu
     */
    public function activate() {
        // Çevreyi tespit et
        $this->detect_environment();

        // Database tabloları oluştur
        require_once EVF_INCLUDES_PATH . 'class-evf-database.php';
        EVF_Database::instance()->create_tables();

        // Varsayılan ayarları kaydet
        $this->set_default_options();

        // Rewrite rules'ları flush et
        flush_rewrite_rules();

        // Aktivasyon log'u
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Activated: Mode=' . $this->plugin_mode . ', WooCommerce=' . ($this->is_woocommerce_active ? 'Yes' : 'No'));
        }
    }

    /**
     * Plugin deaktivasyonu
     */
    public function deactivate() {
        // Scheduled events'leri temizle
        wp_clear_scheduled_hook('evf_cleanup_expired_tokens');
        wp_clear_scheduled_hook('evf_cleanup_old_logs');

        // Rewrite rules'ları temizle
        flush_rewrite_rules();

        // Geçici option'ları temizle
        delete_option('evf_endpoints_flushed');
    }

    /**
     * Plugin kaldırma (uninstall)
     */
    public static function uninstall() {
        // Database tabloları kaldırma seçeneği
        if (get_option('evf_remove_data_on_uninstall', false)) {
            global $wpdb;

            // Tabloları kaldır
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}evf_pending_registrations");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}evf_email_logs");

            // User meta'ları temizle
            $wpdb->query("DELETE FROM {$wpdb->prefix}usermeta WHERE meta_key LIKE 'evf_%'");

            // Options'ları temizle
            $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'evf_%'");
        }
    }

    /**
     * Çevre değişikliğini kontrol et (WooCommerce aktif/deaktif)
     */
    public function check_environment_change($plugin) {
        if ($plugin === 'woocommerce/woocommerce.php') {
            $old_mode = get_option('evf_plugin_mode', 'wordpress');
            $this->detect_environment();
            $new_mode = $this->plugin_mode;

            if ($old_mode !== $new_mode) {
                update_option('evf_plugin_mode', $new_mode);

                // Mode değişikliği sonrası gerekli ayarlamaları yap
                if ($new_mode === 'woocommerce') {
                    // WooCommerce mode'a geçiş
                    $this->setup_woocommerce_mode();
                } else {
                    // WordPress mode'a geçiş
                    $this->setup_wordpress_mode();
                }
            }
        }
    }

    /**
     * WooCommerce modunu ayarla
     */
    private function setup_woocommerce_mode() {
        // WooCommerce spesifik ayarları
        update_option('evf_integration_mode', 'woocommerce');
        flush_rewrite_rules();
    }

    /**
     * WordPress modunu ayarla
     */
    private function setup_wordpress_mode() {
        // WordPress spesifik ayarları
        update_option('evf_integration_mode', 'wordpress');
        flush_rewrite_rules();
    }

    /**
     * Text domain yükle (çoklu dil desteği)
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'email-verification-forms',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Varsayılan ayarları kaydet
     */
    private function set_default_options() {
        $defaults = array(
            'evf_token_expiry' => 24, // 24 saat
            'evf_rate_limit' => 15, // 15 dakika
            'evf_min_password_length' => 8,
            'evf_require_strong_password' => true,
            'evf_admin_notifications' => true,
            'evf_brand_color' => '#96588a',
            'evf_plugin_mode' => $this->plugin_mode,
            'evf_integration_mode' => $this->plugin_mode,
            'evf_remove_data_on_uninstall' => false,
            'evf_version' => EVF_VERSION
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * WooCommerce aktif mi? (Public getter)
     */
    public function __get($property) {
        if ($property === 'is_woocommerce_active') {
            return $this->is_woocommerce_active;
        }

        if ($property === 'plugin_mode') {
            return $this->plugin_mode;
        }

        return null;
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