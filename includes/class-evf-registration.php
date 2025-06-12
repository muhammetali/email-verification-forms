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
            if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce.php')) {
                require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce.php';
            }
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
                if (class_exists('EVF_WooCommerce')) {
                    EVF_WooCommerce::instance();
                }
            } else {
                add_action('woocommerce_loaded', function() {
                    if (class_exists('EVF_WooCommerce')) {
                        EVF_WooCommerce::instance();
                    }
                });
            }
        } else {
            if (class_exists('EVF_Registration')) {
                EVF_Registration::instance();
            }
        }

        // Admin paneli (sadece admin'de)
        if (is_admin()) {
            if (file_exists(EVF_INCLUDES_PATH . 'class-evf-admin.php')) {
                require_once EVF_INCLUDES_PATH . 'class-evf-admin.php';
                if (class_exists('EVF_Admin')) {
                    EVF_Admin::instance();
                }
            }
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
        EVF_Database::create_tables();

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
            // WordPress API kullanarak güvenli silme işlemi
            self::cleanup_plugin_data();
        }
    }

    /**
     * Plugin verilerini güvenli şekilde temizle
     */
    private static function cleanup_plugin_data() {
        global $wpdb;

        // Custom tablolar için direkt sorgu kaçınılmaz
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}evf_pending_registrations");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}evf_email_logs");

        // User meta'ları WordPress API ile temizle
        $users = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'evf_email_verified',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'evf_registration_date',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'evf_registration_ip',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'evf_verified_at',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ID'
        ));

        foreach ($users as $user_id) {
            delete_user_meta($user_id, 'evf_email_verified');
            delete_user_meta($user_id, 'evf_registration_date');
            delete_user_meta($user_id, 'evf_registration_ip');
            delete_user_meta($user_id, 'evf_verified_at');
        }

        // Options'ları WordPress API ile temizle
        $evf_options = array(
            'evf_token_expiry',
            'evf_rate_limit',
            'evf_min_password_length',
            'evf_require_strong_password',
            'evf_admin_notifications',
            'evf_brand_color',
            'evf_plugin_mode',
            'evf_integration_mode',
            'evf_remove_data_on_uninstall',
            'evf_version',
            'evf_db_version',
            'evf_email_from_email',
            'evf_email_from_name',
            'evf_redirect_after_login',
            'evf_endpoints_flushed'
        );

        foreach ($evf_options as $option) {
            delete_option($option);
        }

        // Cron jobs'ları temizle
        wp_clear_scheduled_hook('evf_cleanup_expired_registrations');
        wp_clear_scheduled_hook('evf_cleanup_expired_tokens');
        wp_clear_scheduled_hook('evf_cleanup_old_logs');
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

                // Debug log
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'EVF Environment Change: %s -> %s',
                        $old_mode,
                        $new_mode
                    ));
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

        // Rewrite rules'ları flush et
        add_action('wp_loaded', 'flush_rewrite_rules', 999);

        // WooCommerce hooks'larını aktif et
        do_action('evf_woocommerce_mode_activated');
    }

    /**
     * WordPress modunu ayarla
     */
    private function setup_wordpress_mode() {
        // WordPress spesifik ayarları
        update_option('evf_integration_mode', 'wordpress');

        // Rewrite rules'ları flush et
        add_action('wp_loaded', 'flush_rewrite_rules', 999);

        // WordPress hooks'larını aktif et
        do_action('evf_wordpress_mode_activated');
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
     * Plugin versiyon kontrolü ve güncelleme
     */
    public function check_version_update() {
        $installed_version = get_option('evf_version', '0.0.0');

        if (version_compare($installed_version, EVF_VERSION, '<')) {
            $this->upgrade_plugin($installed_version);
            update_option('evf_version', EVF_VERSION);
        }
    }

    /**
     * Plugin güncelleme işlemleri
     */
    private function upgrade_plugin($from_version) {
        // Gelecekteki versiyon güncellemeleri için
        if (version_compare($from_version, '1.0.0', '<')) {
            // İlk kurulum veya major upgrade
            $this->set_default_options();

            // Database güncellemesi gerekiyorsa
            if (class_exists('EVF_Database')) {
                EVF_Database::create_tables();
            }
        }

        // Cache'leri temizle
        wp_cache_flush();

        do_action('evf_plugin_upgraded', $from_version, EVF_VERSION);
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

    /**
     * Plugin bilgilerini al
     */
    public function get_plugin_info() {
        return array(
            'version' => EVF_VERSION,
            'mode' => $this->plugin_mode,
            'woocommerce_active' => $this->is_woocommerce_active,
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        );
    }

    /**
     * Plugin durumu kontrolü
     */
    public function is_plugin_ready() {
        // Minimum gereksinimler kontrolü
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            return false;
        }

        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            return false;
        }

        // Database tabloları var mı?
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