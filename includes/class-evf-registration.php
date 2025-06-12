<?php
/**
 * EVF Registration Class
 * Kullanıcı kayıt işlemleri sınıfı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Registration {
    
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
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Custom registration page
        add_action('wp', array($this, 'handle_registration_page'));
        
        // WordPress login page customization
        add_action('login_head', array($this, 'custom_login_styles'));
        add_action('login_form', array($this, 'custom_registration_form'));
        
        // Shortcode
        add_shortcode('evf_registration_form', array($this, 'registration_form_shortcode'));
    }
    
    /**
     * Kayıt sayfasını handle et
     */
    public function handle_registration_page() {
        if (is_page('email-verification') || $_SERVER['REQUEST_URI'] === '/email-verification/register/') {
            $this->show_registration_page();
        }
    }
    
    /**
     * Kayıt işlemini başlat
     */
    public function start_registration($email) {
        global $wpdb;
        
        // Email'in zaten kayıtlı olup olmadığını kontrol et
        if (email_exists($email)) {
            return array(
                'success' => false,
                'error' => 'email_exists'
            );
        }
        
        // Bekleyen kayıt var mı kontrol et
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE email = %s AND status = 'pending'",
            $email
        ));
        
        // Token oluştur
        $token = wp_generate_password(32, false);
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . get_option('evf_token_expiry', 24) . ' hours'));
        
        if ($existing) {
            // Mevcut kaydı güncelle
            $wpdb->update(
                $table_name,
                array(
                    'token' => $token,
                    'expires_at' => $expires_at,
                    'created_at' => current_time('mysql')
                ),
                array('id' => $existing->id)
            );
        } else {
            // Yeni kayıt oluştur
            $wpdb->insert(
                $table_name,
                array(
                    'email' => $email,
                    'token' => $token,
                    'status' => 'pending',
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'expires_at' => $expires_at,
                    'created_at' => current_time('mysql')
                )
            );
        }
        
        // Doğrulama e-postası gönder
        $email_result = EVF_Email::instance()->send_verification_email($email, $token);
        
        if ($email_result) {
            return array('success' => true);
        } else {
            return array(
                'success' => false,
                'error' => 'email_send_failed'
            );
        }
    }
    
    /**
     * Email doğrulama
     */
    public function verify_email($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $pending_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status = 'pending'",
            $token
        ));
        
        if (!$pending_user) {
            return array(
                'success' => false,
                'message' => __('Geçersiz doğrulama bağlantısı.', 'email-verification-forms')
            );
        }
        
        // Token süresini kontrol et
        if ($this->is_token_expired($pending_user->expires_at)) {
            return array(
                'success' => false,
                'message' => __('Doğrulama bağlantısının süresi dolmuş.', 'email-verification-forms')
            );
        }
        
        // Durumu güncelle
        $wpdb->update(
            $table_name,
            array(
                'status' => 'email_verified',
                'email_verified_at' => current_time('mysql')
            ),
            array('id' => $pending_user->id)
        );
        
        return array('success' => true);
    }
    
    /**
     * Kayıt işlemini tamamla
     */
    public function complete_registration($token, $password) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $pending_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status = 'email_verified'",
            $token
        ));
        
        if (!$pending_user) {
            return array(
                'success' => false,
                'error' => 'token_invalid'
            );
        }
        
        // Token süresini kontrol et
        if ($this->is_token_expired($pending_user->expires_at)) {
            return array(
                'success' => false,
                'error' => 'token_expired'
            );
        }
        
        // Email'in hala müsait olup olmadığını kontrol et
        if (email_exists($pending_user->email)) {
            return array(
                'success' => false,
                'error' => 'email_exists'
            );
        }
        
        // WordPress kullanıcısı oluştur
        $username = $this->generate_username($pending_user->email);
        $user_id = wp_create_user($username, $password, $pending_user->email);
        
        if (is_wp_error($user_id)) {
            return array(
                'success' => false,
                'error' => 'user_creation_failed'
            );
        }
        
        // Kullanıcı meta verilerini ekle
        update_user_meta($user_id, 'evf_registration_date', current_time('mysql'));
        update_user_meta($user_id, 'evf_registration_ip', $pending_user->ip_address);
        update_user_meta($user_id, 'evf_verified_email', true);
        
        // Bekleyen kaydı tamamlandı olarak işaretle
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'user_id' => $user_id,
                'completed_at' => current_time('mysql')
            ),
            array('id' => $pending_user->id)
        );
        
        // Admin'e bildirim gönder
        if (get_option('evf_admin_notifications', true)) {
            EVF_Email::instance()->send_admin_notification($user_id, $pending_user->email);
        }
        
        // Hoş geldin e-postası gönder
        EVF_Email::instance()->send_welcome_email($user_id);
        
        return array('success' => true);
    }
    
    /**
     * Token süresini kontrol et
     */
    public function is_token_expired($expires_at) {
        return strtotime($expires_at) < time();
    }
    
    /**
     * Token'a göre bekleyen kullanıcıyı getir
     */
    public function get_pending_user_by_token($token) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s",
            $token
        ));
    }
    
    /**
     * Username oluştur
     */
    private function generate_username($email) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        
        // Username'in benzersiz olduğundan emin ol
        $original_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Kayıt sayfasını göster
     */
    public function show_registration_page() {
        // WordPress login sayfasında mıyız?
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
            include EVF_TEMPLATES_PATH . 'registration-form-login.php';
        } else {
            include EVF_TEMPLATES_PATH . 'registration-form.php';
        }
        exit;
    }
    
    /**
     * Custom login styles
     */
    public function custom_login_styles() {
        if (isset($_GET['action']) && $_GET['action'] === 'register') {
            echo '<link rel="stylesheet" href="' . EVF_ASSETS_URL . 'css/evf-login.css?v=' . EVF_VERSION . '">';
        }
    }
    
    /**
     * Custom registration form on login page
     */
    public function custom_registration_form() {
        if (isset($_GET['action']) && $_GET['action'] === 'register') {
            include EVF_TEMPLATES_PATH . 'registration-form-fields.php';
        }
    }
    
    /**
     * Registration form shortcode
     */
    public function registration_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Kayıt Ol', 'email-verification-forms'),
            'show_login_link' => true
        ), $atts);
        
        ob_start();
        include EVF_TEMPLATES_PATH . 'registration-form-shortcode.php';
        return ob_get_clean();
    }
    
    /**
     * Bekleyen kayıtları temizle (cronjob için)
     */
    public function cleanup_expired_registrations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE expires_at < %s AND status = 'pending'",
            current_time('mysql')
        ));
    }
    
    /**
     * Kayıt istatistikleri
     */
    public function get_registration_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Toplam kayıt denemeleri
        $stats['total_attempts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s",
            $date_from
        ));
        
        // Email doğrulanmış kayıtlar
        $stats['email_verified'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s AND status IN ('email_verified', 'completed')",
            $date_from
        ));
        
        // Tamamlanmış kayıtlar
        $stats['completed'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s AND status = 'completed'",
            $date_from
        ));
        
        // Dönüşüm oranları
        $stats['email_verification_rate'] = $stats['total_attempts'] > 0 
            ? round(($stats['email_verified'] / $stats['total_attempts']) * 100, 2) 
            : 0;
            
        $stats['completion_rate'] = $stats['email_verified'] > 0 
            ? round(($stats['completed'] / $stats['email_verified']) * 100, 2) 
            : 0;
        
        return $stats;
    }
}