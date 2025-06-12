<?php
/**
 * EVF Database Class
 * Veritabanı işlemleri sınıfı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Database {
    
    private static $instance = null;
    private $db_version = '1.0.0';
    
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
        // Cronjob temizlik işlemi
        add_action('evf_cleanup_expired_registrations', array($this, 'cleanup_expired_registrations'));
        
        // Plugin güncellendiğinde tablo güncelleme
        add_action('plugins_loaded', array($this, 'check_database_version'));
        
        // Cronjob schedule
        if (!wp_next_scheduled('evf_cleanup_expired_registrations')) {
            wp_schedule_event(time(), 'daily', 'evf_cleanup_expired_registrations');
        }
    }
    
    /**
     * Veritabanı tablolarını oluştur
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bekleyen kayıtlar tablosu
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            token varchar(64) NOT NULL,
            status enum('pending', 'email_verified', 'completed', 'expired') DEFAULT 'pending',
            user_id bigint(20) UNSIGNED NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            created_at datetime NOT NULL,
            email_verified_at datetime NULL,
            completed_at datetime NULL,
            expires_at datetime NOT NULL,
            attempts int(11) DEFAULT 0,
            last_attempt_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_token (token),
            KEY email_index (email),
            KEY status_index (status),
            KEY expires_index (expires_at),
            KEY created_index (created_at),
            KEY ip_index (ip_address)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Email log tablosu
        $log_table = $wpdb->prefix . 'evf_email_logs';
        
        $sql_log = "CREATE TABLE $log_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            email_type enum('verification', 'welcome', 'admin_notification') NOT NULL,
            status enum('sent', 'failed') NOT NULL,
            error_message text NULL,
            user_id bigint(20) UNSIGNED NULL,
            ip_address varchar(45) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY email_index (email),
            KEY type_index (email_type),
            KEY status_index (status),
            KEY created_index (created_at),
            KEY user_index (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_log);
        
        // Veritabanı versiyonunu kaydet
        update_option('evf_db_version', '1.0.0');
    }
    
    /**
     * Veritabanı versiyonunu kontrol et
     */
    public function check_database_version() {
        $installed_version = get_option('evf_db_version', '0.0.0');
        
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->upgrade_database($installed_version);
        }
    }
    
    /**
     * Veritabanını güncelle
     */
    private function upgrade_database($from_version) {
        // Gelecekteki güncellemeler için
        if (version_compare($from_version, '1.0.0', '<')) {
            self::create_tables();
        }
        
        // Versiyon numarasını güncelle
        update_option('evf_db_version', $this->db_version);
    }
    
    /**
     * Süresi dolmuş kayıtları temizle
     */
    public function cleanup_expired_registrations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        // Süresi dolmuş kayıtları expired olarak işaretle
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET status = 'expired' 
             WHERE expires_at < %s 
             AND status IN ('pending', 'email_verified')",
            current_time('mysql')
        ));
        
        // 30 günden eski expired kayıtları sil
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE status = 'expired' 
             AND expires_at < %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Email log'larını temizle (90 günden eski)
        $log_table = $wpdb->prefix . 'evf_email_logs';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $log_table 
             WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
    }
    
    /**
     * Email log kaydet
     */
    public function log_email($email, $type, $status, $error_message = null, $user_id = null) {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'evf_email_logs';
        
        return $wpdb->insert(
            $log_table,
            array(
                'email' => $email,
                'email_type' => $type,
                'status' => $status,
                'error_message' => $error_message,
                'user_id' => $user_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Bekleyen kayıt istatistikleri
     */
    public function get_registration_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Toplam deneme
        $stats['total_attempts'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s",
            $date_from
        ));
        
        // Email doğrulanmış
        $stats['email_verified'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at >= %s 
             AND status IN ('email_verified', 'completed')",
            $date_from
        ));
        
        // Tamamlanmış
        $stats['completed'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at >= %s 
             AND status = 'completed'",
            $date_from
        ));
        
        // Süresi dolmuş
        $stats['expired'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at >= %s 
             AND status = 'expired'",
            $date_from
        ));
        
        // Bekleyen
        $stats['pending'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at >= %s 
             AND status = 'pending'",
            $date_from
        ));
        
        // Oranlar
        $stats['email_verification_rate'] = $stats['total_attempts'] > 0 
            ? round(($stats['email_verified'] / $stats['total_attempts']) * 100, 2) 
            : 0;
            
        $stats['completion_rate'] = $stats['email_verified'] > 0 
            ? round(($stats['completed'] / $stats['email_verified']) * 100, 2) 
            : 0;
            
        $stats['success_rate'] = $stats['total_attempts'] > 0 
            ? round(($stats['completed'] / $stats['total_attempts']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Email log istatistikleri
     */
    public function get_email_stats($days = 30) {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'evf_email_logs';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array();
        
        // Toplam email
        $stats['total_emails'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table WHERE created_at >= %s",
            $date_from
        ));
        
        // Başarılı email
        $stats['successful_emails'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table 
             WHERE created_at >= %s AND status = 'sent'",
            $date_from
        ));
        
        // Başarısız email
        $stats['failed_emails'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table 
             WHERE created_at >= %s AND status = 'failed'",
            $date_from
        ));
        
        // Email türlerine göre
        $email_types = $wpdb->get_results($wpdb->prepare(
            "SELECT email_type, COUNT(*) as count 
             FROM $log_table 
             WHERE created_at >= %s 
             GROUP BY email_type",
            $date_from
        ), ARRAY_A);
        
        foreach ($email_types as $type) {
            $stats['by_type'][$type['email_type']] = $type['count'];
        }
        
        // Başarı oranı
        $stats['success_rate'] = $stats['total_emails'] > 0 
            ? round(($stats['successful_emails'] / $stats['total_emails']) * 100, 2) 
            : 0;
        
        return $stats;
    }
    
    /**
     * Belirli bir email için kayıt geçmişi
     */
    public function get_email_history($email) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE email = %s 
             ORDER BY created_at DESC 
             LIMIT 10",
            $email
        ));
    }
    
    /**
     * Son kayıt denemeleri
     */
    public function get_recent_registrations($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * IP bazlı istatistikler
     */
    public function get_ip_stats($days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as attempts,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
             FROM $table_name 
             WHERE created_at >= %s 
             GROUP BY ip_address 
             HAVING attempts > 1
             ORDER BY attempts DESC 
             LIMIT 20",
            $date_from
        ));
    }
    
    /**
     * Günlük kayıt trendi
     */
    public function get_daily_registration_trend($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status IN ('email_verified', 'completed') THEN 1 ELSE 0 END) as verified
             FROM $table_name 
             WHERE DATE(created_at) >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date ASC",
            $date_from
        ));
    }
    
    /**
     * Tabloları sil (uninstall için)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'evf_pending_registrations',
            $wpdb->prefix . 'evf_email_logs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Cronjob'u temizle
        wp_clear_scheduled_hook('evf_cleanup_expired_registrations');
        
        // Ayarları sil
        delete_option('evf_db_version');
    }
    
    /**
     * Tablo optimizasyonu
     */
    public function optimize_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'evf_pending_registrations',
            $wpdb->prefix . 'evf_email_logs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }
}