<?php
/**
 * EVF Database Class
 * Veritabanı işlemleri sınıfı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Database
{

    private static $instance = null;
    private $db_version = '1.0.0';
    private $cache_group = 'evf_database';
    private $cache_expiration = 3600; // 1 hour

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks()
    {
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
    public static function create_tables()
    {
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
    public function check_database_version()
    {
        $installed_version = get_option('evf_db_version', '0.0.0');

        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->upgrade_database($installed_version);
        }
    }

    /**
     * Veritabanını güncelle
     */
    private function upgrade_database($from_version)
    {
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
    public function cleanup_expired_registrations()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // Süresi dolmuş kayıtları expired olarak işaretle
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated_count = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET status = 'expired' 
             WHERE expires_at < %s 
             AND status IN ('pending', 'email_verified')",
            current_time('mysql')
        ));

        // 30 günden eski expired kayıtları sil
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted_count = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE status = 'expired' 
             AND expires_at < %s",
            gmdate('Y-m-d H:i:s', strtotime('-30 days'))
        ));

        // Email log'larını temizle (90 günden eski)
        $log_table = $wpdb->prefix . 'evf_email_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $log_table 
             WHERE created_at < %s",
            gmdate('Y-m-d H:i:s', strtotime('-90 days'))
        ));

        // Cache'leri temizle
        $this->clear_all_caches();

        // Cleanup log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'EVF Cleanup: Updated %d expired records, deleted %d old records, deleted %d log entries',
                $updated_count,
                $deleted_count,
                $log_deleted
            ));
        }
    }

    /**
     * Email log kaydet
     */
    public function log_email($email, $type, $status, $error_message = null, $user_id = null)
    {
        global $wpdb;

        $log_table = $wpdb->prefix . 'evf_email_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert(
            $log_table,
            array(
                'email' => $email,
                'email_type' => $type,
                'status' => $status,
                'error_message' => $error_message,
                'user_id' => $user_id,
                'ip_address' => $this->get_client_ip(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        // Email stats cache'ini temizle
        wp_cache_delete('email_stats_30', $this->cache_group);
        wp_cache_delete('email_stats_7', $this->cache_group);

        return $result;
    }

    /**
     * Bekleyen kayıt istatistikleri (cache ile)
     */
    public function get_registration_stats($days = 30)
    {
        $cache_key = 'registration_stats_' . $days;
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if (false === $stats) {
            $stats = $this->fetch_registration_stats($days);
            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_expiration);
        }

        return $stats;
    }

    /**
     * Registration stats'ları veritabanından çek
     */
    private function fetch_registration_stats($days)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = array();

        // Toplam deneme
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['total_attempts'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s",
            $date_from
        ));

        // Email doğrulanmış
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['email_verified'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at >= %s 
             AND status IN ('email_verified', 'completed')",
            $date_from
        ));

        // Tamamlanmış
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['completed'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at >= %s 
             AND status = 'completed'",
            $date_from
        ));

        // Süresi dolmuş
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['expired'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE created_at >= %s 
             AND status = 'expired'",
            $date_from
        ));

        // Bekleyen
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['pending'] = (int)$wpdb->get_var($wpdb->prepare(
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
     * Email log istatistikleri (cache ile)
     */
    public function get_email_stats($days = 30)
    {
        $cache_key = 'email_stats_' . $days;
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if (false === $stats) {
            $stats = $this->fetch_email_stats($days);
            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_expiration);
        }

        return $stats;
    }

    /**
     * Email stats'ları veritabanından çek
     */
    private function fetch_email_stats($days)
    {
        global $wpdb;

        $log_table = $wpdb->prefix . 'evf_email_logs';
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = array();

        // Toplam email
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['total_emails'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table WHERE created_at >= %s",
            $date_from
        ));

        // Başarılı email
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['successful_emails'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table 
             WHERE created_at >= %s AND status = 'sent'",
            $date_from
        ));

        // Başarısız email
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $stats['failed_emails'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $log_table 
             WHERE created_at >= %s AND status = 'failed'",
            $date_from
        ));

        // Email türlerine göre
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $email_types = $wpdb->get_results($wpdb->prepare(
            "SELECT email_type, COUNT(*) as count 
             FROM $log_table 
             WHERE created_at >= %s 
             GROUP BY email_type",
            $date_from
        ), ARRAY_A);

        $stats['by_type'] = array();
        if ($email_types) {
            foreach ($email_types as $type) {
                $stats['by_type'][$type['email_type']] = (int)$type['count'];
            }
        }

        // Başarı oranı
        $stats['success_rate'] = $stats['total_emails'] > 0
            ? round(($stats['successful_emails'] / $stats['total_emails']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Belirli bir email için kayıt geçmişi (cache ile)
     */
    public function get_email_history($email)
    {
        $cache_key = 'email_history_' . md5($email);
        $history = wp_cache_get($cache_key, $this->cache_group);

        if (false === $history) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'evf_pending_registrations';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE email = %s 
                 ORDER BY created_at DESC 
                 LIMIT 10",
                $email
            ));

            // 15 dakika cache
            wp_cache_set($cache_key, $history, $this->cache_group, 900);
        }

        return $history;
    }

    /**
     * Son kayıt denemeleri (cache ile)
     */
    public function get_recent_registrations($limit = 50)
    {
        $cache_key = 'recent_registrations_' . $limit;
        $registrations = wp_cache_get($cache_key, $this->cache_group);

        if (false === $registrations) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'evf_pending_registrations';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $registrations = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $limit
            ));

            // 5 dakika cache
            wp_cache_set($cache_key, $registrations, $this->cache_group, 300);
        }

        return $registrations;
    }

    /**
     * IP bazlı istatistikler (cache ile)
     */
    public function get_ip_stats($days = 7)
    {
        $cache_key = 'ip_stats_' . $days;
        $stats = wp_cache_get($cache_key, $this->cache_group);

        if (false === $stats) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'evf_pending_registrations';
            $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats = $wpdb->get_results($wpdb->prepare(
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

            wp_cache_set($cache_key, $stats, $this->cache_group, $this->cache_expiration);
        }

        return $stats;
    }

    /**
     * Günlük kayıt trendi (cache ile)
     */
    public function get_daily_registration_trend($days = 30)
    {
        $cache_key = 'daily_trend_' . $days;
        $trend = wp_cache_get($cache_key, $this->cache_group);

        if (false === $trend) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'evf_pending_registrations';
            $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $trend = $wpdb->get_results($wpdb->prepare(
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

            // 2 saat cache
            wp_cache_set($cache_key, $trend, $this->cache_group, 7200);
        }

        return $trend;
    }

    /**
     * Güvenli IP adresi alma
     */
    private function get_client_ip()
    {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', sanitize_text_field(wp_unslash($_SERVER[$key]))) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
    }

    /**
     * Tüm cache'leri temizle
     */
    public function clear_all_caches()
    {
        $cache_keys = array(
            'registration_stats_30',
            'registration_stats_7',
            'email_stats_30',
            'email_stats_7',
            'daily_trend_30',
            'daily_trend_7',
            'ip_stats_7',
            'recent_registrations_50'
        );

        foreach ($cache_keys as $key) {
            wp_cache_delete($key, $this->cache_group);
        }
    }

    /**
     * Email history cache'ini temizle
     */
    public function clear_email_cache($email)
    {
        $cache_key = 'email_history_' . md5($email);
        wp_cache_delete($cache_key, $this->cache_group);
    }

    /**
     * Tabloları sil (uninstall için)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'evf_pending_registrations',
            $wpdb->prefix . 'evf_email_logs'
        );

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
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
    public function optimize_tables()
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'evf_pending_registrations',
            $wpdb->prefix . 'evf_email_logs'
        );

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query("OPTIMIZE TABLE $table");
        }

        // Optimizasyon sonrası cache'leri temizle
        $this->clear_all_caches();
    }
}