<?php
/**
 * Plugin Name: Email Verification Forms
 * Plugin URI: https://your-website.com
 * Description: Modern email doğrulama sistemi ile WordPress kullanıcı kaydı
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: email-verification-forms
 * Domain Path: /languages
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('EVF_VERSION', '1.0.0');
define('EVF_PLUGIN_FILE', __FILE__);
define('EVF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EVF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EVF_INCLUDES_PATH', EVF_PLUGIN_PATH . 'includes/');
define('EVF_ASSETS_URL', EVF_PLUGIN_URL . 'assets/');
define('EVF_TEMPLATES_PATH', EVF_PLUGIN_PATH . 'templates/');

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
     * Plugin modu
     */
    private $plugin_mode = 'wordpress'; // 'wordpress' or 'woocommerce'
    
    /**
     * Singleton pattern
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
            in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ||
            (is_multisite() && array_key_exists('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins', array())))
        );
        
        // Plugin modunu belirle
        $this->plugin_mode = $this->is_woocommerce_active ? 'woocommerce' : 'wordpress';
        
        // Debug için - detaylı log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: Environment detected - Mode: ' . $this->plugin_mode . 
                     ', WooCommerce class: ' . (class_exists('WooCommerce') ? 'YES' : 'NO') . 
                     ', WooCommerce in active plugins: ' . (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ? 'YES' : 'NO'));
        }
    }
    
    /**
     * WooCommerce aktif mi?
     */
    public function is_woocommerce_active() {
        return $this->is_woocommerce_active;
    }
    
    /**
     * Plugin modunu getir
     */
    public function get_plugin_mode() {
        return $this->plugin_mode;
    }
    
    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        register_activation_hook(EVF_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(EVF_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Gerekli dosyaları include et
     */
    private function includes() {
        // Core sınıfları
        require_once EVF_INCLUDES_PATH . 'class-evf-database.php';
        require_once EVF_INCLUDES_PATH . 'class-evf-email.php';
        require_once EVF_INCLUDES_PATH . 'class-evf-registration.php';
        require_once EVF_INCLUDES_PATH . 'class-evf-admin.php';
        require_once EVF_INCLUDES_PATH . 'class-evf-core.php';
        
        // WooCommerce entegrasyonu (sadece WooCommerce aktifse)
        if ($this->is_woocommerce_active) {
            // WooCommerce'in yüklenmesini bekle
            if (did_action('woocommerce_loaded')) {
                require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce.php';
            } else {
                add_action('woocommerce_loaded', function() {
                    require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce.php';
                });
            }
        }
    }
    
    /**
     * Plugin'i başlat
     */
    public function init() {
        // Sınıfları başlat
        EVF_Database::instance();
        EVF_Email::instance();
        
        // Moda göre farklı registration handler'ları
        if ($this->is_woocommerce_active) {
            // WooCommerce modu
            EVF_WooCommerce::instance();
            EVF_Registration::instance(); // Yedek olarak
        } else {
            // Pure WordPress modu
            EVF_Registration::instance();
        }
        
        EVF_Core::instance();
        
        // Admin paneli (sadece admin'de)
        if (is_admin()) {
            EVF_Admin::instance();
        }
    }
    
    /**
     * Dil dosyalarını yükle
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'email-verification-forms',
            false,
            dirname(plugin_basename(EVF_PLUGIN_FILE)) . '/languages'
        );
    }
    
    /**
     * Plugin aktivasyonu
     */
    public function activate() {
        // Veritabanı tablolarını oluştur
        EVF_Database::create_tables();
        
        // Rewrite rules'ları flush et
        flush_rewrite_rules();
        
        // Varsayılan ayarları kaydet
        $this->set_default_options();
    }
    
    /**
     * Plugin deaktivasyonu
     */
    public function deactivate() {
        flush_rewrite_rules();
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
            'evf_redirect_after_login' => home_url(),
            'evf_brand_color' => '#3b82f6',
            'evf_email_from_name' => get_bloginfo('name'),
            'evf_email_from_email' => get_option('admin_email'),
            // Hibrit sistem ayarları
            'evf_plugin_mode' => $this->plugin_mode,
            'evf_woocommerce_integration' => $this->is_woocommerce_active,
            'evf_restrict_unverified_users' => true,
            'evf_verification_reminder_interval' => 7, // 7 gün
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Global helper functions tanımla
     */
    private function define_global_functions() {
        // Global EVF instance fonksiyonu
        if (!function_exists('EVF')) {
            function EVF() {
                return EmailVerificationForms::instance();
            }
        }
        
        // User verification check
        if (!function_exists('evf_is_user_verified')) {
            function evf_is_user_verified($user_id = null) {
                if (!$user_id) {
                    $user_id = get_current_user_id();
                }
                
                if (!$user_id) {
                    return false;
                }
                
                return (bool) get_user_meta($user_id, 'evf_email_verified', true);
            }
        }
        
        // Plugin mode check
        if (!function_exists('evf_get_plugin_mode')) {
            function evf_get_plugin_mode() {
                return EVF()->get_plugin_mode();
            }
        }
        
        // WooCommerce check
        if (!function_exists('evf_is_woocommerce_active')) {
            function evf_is_woocommerce_active() {
                return EVF()->is_woocommerce_active();
            }
        }
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
    // Çevreyi yeniden kontrol et
    $evf = EVF();
    update_option('evf_plugin_mode', $evf->get_plugin_mode());
    update_option('evf_woocommerce_integration', $evf->is_woocommerce_active());
    
    // Debug log
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('EVF Activated: Mode=' . $evf->get_plugin_mode() . ', WooCommerce=' . ($evf->is_woocommerce_active() ? 'Yes' : 'No'));
    }
});

/**
 * WooCommerce aktivasyon/deaktivasyon durumlarını takip et
 */
add_action('activated_plugin', function($plugin) {
    if ($plugin === 'woocommerce/woocommerce.php') {
        // WooCommerce yeni aktifleşti, ayarları güncelle
        update_option('evf_plugin_mode', 'woocommerce');
        update_option('evf_woocommerce_integration', true);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: WooCommerce activated, switching to WooCommerce mode');
        }
    }
});

add_action('deactivated_plugin', function($plugin) {
    if ($plugin === 'woocommerce/woocommerce.php') {
        // WooCommerce deaktifleşti, ayarları güncelle
        update_option('evf_plugin_mode', 'wordpress');
        update_option('evf_woocommerce_integration', false);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: WooCommerce deactivated, switching to WordPress mode');
        }
    }
});