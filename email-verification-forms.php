<?php
/**
 * Plugin Name: Email Verification Forms
 * Plugin URI: https://yourwebsite.com
 * Description: E-posta doğrulama sistemi - WordPress ve WooCommerce entegrasyonu
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: email-verification-forms
 * Domain Path: /languages
 *
 * DÜZELTİLMİŞ VERSİYON - Çoklu init sorunu giderildi
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('EVF_VERSION', '2.0.0');
define('EVF_PLUGIN_FILE', __FILE__);
define('EVF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EVF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EVF_INCLUDES_PATH', EVF_PLUGIN_DIR . 'includes/');
define('EVF_TEMPLATES_PATH', EVF_PLUGIN_DIR . 'templates/');
define('EVF_ASSETS_URL', EVF_PLUGIN_URL . 'assets/');

/**
 * Main Plugin Class - DÜZELTİLMİŞ VERSİYON
 */

final class EmailVerificationForms {

    private static $instance = null;
    private static $initialized = false; // Çoklu init engelleyici
    private $is_woocommerce_active = false;

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
     * Constructor - Çoklu init engelleyici eklendi
     */
    private function __construct() {
        // Çoklu init'i engelle
        if (self::$initialized) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF: Already initialized, skipping...');
            }
            return;
        }

        $this->define_constants();
        $this->detect_environment();
        $this->init_hooks();

        self::$initialized = true;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: Main plugin initialized for the first time');
        }
    }

    /**
     * Define additional constants
     */
    private function define_constants() {
        if (!defined('EVF_MIN_PHP_VERSION')) {
            define('EVF_MIN_PHP_VERSION', '7.4');
        }

        if (!defined('EVF_MIN_WP_VERSION')) {
            define('EVF_MIN_WP_VERSION', '5.0');
        }
    }

    /**
     * Environment detection - Düzeltilmiş
     */
    private function detect_environment() {
        // WooCommerce aktif mi kontrol et
        $this->is_woocommerce_active = $this->is_woocommerce_available();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: Environment detected - Mode: ' . ($this->is_woocommerce_active ? 'woocommerce' : 'wordpress') .
                ', WooCommerce class: ' . (class_exists('WooCommerce') ? 'YES' : 'NO') .
                ', WooCommerce in active plugins: ' . (in_array('woocommerce/woocommerce.php', get_option('active_plugins', array())) ? 'YES' : 'NO'));
        }
    }

    /**
     * WooCommerce availability kontrolü
     */
    private function is_woocommerce_available() {
        // Plugin aktif mi
        if (!in_array('woocommerce/woocommerce.php', get_option('active_plugins', array()))) {
            return false;
        }

        // Class mevcut mu
        if (!class_exists('WooCommerce')) {
            return false;
        }

        return true;
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

        // Plugin init - TEK SEFER
        add_action('init', array($this, 'init'), 0);

        // WooCommerce environment change tracking
        add_action('activated_plugin', array($this, 'check_environment_change'));
        add_action('deactivated_plugin', array($this, 'check_environment_change'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
    }

    /**
     * Gerekli dosyaları include et
     */
    private function includes() {
        // Önce helper fonksiyonları yükle
        require_once EVF_INCLUDES_PATH . 'evf-functions.php';

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

        // Admin sınıfları
        if (is_admin()) {
            require_once EVF_INCLUDES_PATH . 'class-evf-admin.php';
        }
    }

    /**
     * Plugin'i başlat - TEK SEFER ÇALIŞIR
     */
    public function init() {
        // Zaten init edilmişse skip
        if (did_action('evf_initialized')) {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: init() called - Mode: ' . ($this->is_woocommerce_active ? 'woocommerce' : 'wordpress'));
        }

        // Dosyaları include et
        $this->includes();

        // Database'i hazırla
        EVF_Database::instance();

        // Email sınıfını başlat
        EVF_Email::instance();

        // Core'u başlat
        EVF_Core::instance();

        // Mode'a göre başlat
        if ($this->is_woocommerce_active) {
            // WooCommerce entegrasyonu
            if (did_action('woocommerce_loaded')) {
                EVF_WooCommerce::instance();
            } else {
                add_action('woocommerce_loaded', function() {
                    EVF_WooCommerce::instance();
                }, 1);
            }
        } else {
            // WordPress mode
            EVF_Registration::instance();
        }

        // Admin başlat
        if (is_admin()) {
            EVF_Admin::instance();
        }

        // Init tamamlandı sinyali
        do_action('evf_initialized');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: Initialization completed');
        }
    }

    /**
     * Textdomain yükleme
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'email-verification-forms',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Activated: Mode=' . ($this->is_woocommerce_active ? 'woocommerce' : 'wordpress') .
                ', WooCommerce=' . ($this->is_woocommerce_active ? 'Yes' : 'No'));
        }

        // Minimum requirements check
        if (!$this->check_requirements()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Email Verification Forms requires PHP 7.4+ and WordPress 5.0+', 'email-verification-forms'));
        }

        // Includes - activation için gerekli
        $this->includes();

        // Database setup
        EVF_Database::instance()->create_tables();

        // Default options
        $this->set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('evf_activated', time());
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cron jobs'ları temizle
        wp_clear_scheduled_hook('evf_auto_delete_unverified');

        // Rewrite rules flush
        flush_rewrite_rules();

        // Temporary cache'leri temizle
        delete_transient('evf_environment_check');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: Plugin deactivated');
        }
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Only run if user has proper permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Clean database
        global $wpdb;

        // Drop tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}evf_pending_registrations");

        // Remove options
        delete_option('evf_db_version');
        delete_option('evf_verification_method');
        delete_option('evf_min_password_length');
        delete_option('evf_require_strong_password');
        delete_option('evf_admin_notifications');
        delete_option('evf_code_resend_interval');
        delete_option('evf_max_code_attempts');
        delete_option('evf_auto_delete_unverified');
        delete_option('evf_auto_delete_hours');
        delete_option('evf_activated');
        delete_option('evf_wc_rewrite_rules_flushed');

        // Clear cron jobs
        wp_clear_scheduled_hook('evf_auto_delete_unverified');
    }

    /**
     * Minimum requirements check
     */
    private function check_requirements() {
        if (version_compare(PHP_VERSION, EVF_MIN_PHP_VERSION, '<')) {
            return false;
        }

        if (version_compare(get_bloginfo('version'), EVF_MIN_WP_VERSION, '<')) {
            return false;
        }

        return true;
    }

    /**
     * Default options
     */
    private function set_default_options() {
        $defaults = array(
            'evf_verification_method' => 'code',
            'evf_min_password_length' => 8,
            'evf_require_strong_password' => true,
            'evf_admin_notifications' => true,
            'evf_code_resend_interval' => 5,
            'evf_max_code_attempts' => 5,
            'evf_auto_delete_unverified' => false,
            'evf_auto_delete_hours' => 24,
        );

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }

    /**
     * Environment change detection
     */
    public function check_environment_change($plugin) {
        if ($plugin === 'woocommerce/woocommerce.php') {
            delete_transient('evf_environment_check');

            // Environment'ı yeniden tespit et
            $this->detect_environment();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF: Environment change detected - WooCommerce ' . (is_plugin_active($plugin) ? 'activated' : 'deactivated'));
            }
        }
    }

    /**
     * Admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Email Verification', 'email-verification-forms'),
            __('Email Verification', 'email-verification-forms'),
            'manage_options',
            'evf-dashboard',
            array('EVF_Admin_Pages', 'dashboard_page'),
            'dashicons-email-alt',
            30
        );

        add_submenu_page(
            'evf-dashboard',
            __('Dashboard', 'email-verification-forms'),
            __('Dashboard', 'email-verification-forms'),
            'manage_options',
            'evf-dashboard',
            array('EVF_Admin_Pages', 'dashboard_page')
        );

        add_submenu_page(
            'evf-dashboard',
            __('Settings', 'email-verification-forms'),
            __('Settings', 'email-verification-forms'),
            'manage_options',
            'evf-settings',
            array('EVF_Admin_Pages', 'settings_page')
        );

        add_submenu_page(
            'evf-dashboard',
            __('Tools', 'email-verification-forms'),
            __('Tools', 'email-verification-forms'),
            'manage_options',
            'evf-tools',
            array('EVF_Admin_Pages', 'tools_page')
        );
    }

    /**
     * Admin init
     */
    public function admin_init() {
        // Settings registration
        register_setting('evf_settings', 'evf_verification_method');
        register_setting('evf_settings', 'evf_min_password_length');
        register_setting('evf_settings', 'evf_require_strong_password');
        register_setting('evf_settings', 'evf_admin_notifications');
        register_setting('evf_settings', 'evf_code_resend_interval');
        register_setting('evf_settings', 'evf_max_code_attempts');
        register_setting('evf_settings', 'evf_auto_delete_unverified');
        register_setting('evf_settings', 'evf_auto_delete_hours');
    }

    /**
     * Admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'evf-') === false) {
            return;
        }

        wp_enqueue_style(
            'evf-admin-style',
            EVF_ASSETS_URL . 'css/evf-admin.css',
            array(),
            EVF_VERSION
        );

        wp_enqueue_script(
            'evf-admin-script',
            EVF_ASSETS_URL . 'js/evf-admin.js',
            array('jquery'),
            EVF_VERSION,
            true
        );

        wp_localize_script('evf-admin-script', 'evf_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('evf_admin_nonce'),
            'messages' => array(
                'loading' => __('Loading...', 'email-verification-forms'),
                'error' => __('An error occurred.', 'email-verification-forms'),
                'success' => __('Operation completed successfully.', 'email-verification-forms'),
            ),
        ));
    }

    /**
     * Get WooCommerce status
     */
    public function is_woocommerce_active() {
        return $this->is_woocommerce_active;
    }
}

/**
 * Global helper functions
 */
if (!function_exists('evf_is_woocommerce_active')) {
    function evf_is_woocommerce_active() {
        return EmailVerificationForms::instance()->is_woocommerce_active();
    }
}

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

// Plugin'i başlat
EmailVerificationForms::instance();