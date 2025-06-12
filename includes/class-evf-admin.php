<?php
/**
 * EVF Admin Class
 * Admin panel işlemleri sınıfı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Admin {
    
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
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_evf_test_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_evf_export_data', array($this, 'ajax_export_data'));
    }
    
    /**
     * Admin menü
     */
    public function admin_menu() {
        // Ana menü
        add_menu_page(
            __('Email Verification', 'email-verification-forms'),
            __('Email Verification', 'email-verification-forms'),
            'manage_options',
            'evf-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-email-alt',
            30
        );
        
        // Alt menüler
        add_submenu_page(
            'evf-dashboard',
            __('Dashboard', 'email-verification-forms'),
            __('Dashboard', 'email-verification-forms'),
            'manage_options',
            'evf-dashboard',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'evf-dashboard',
            __('Kayıtlar', 'email-verification-forms'),
            __('Kayıtlar', 'email-verification-forms'),
            'manage_options',
            'evf-registrations',
            array($this, 'registrations_page')
        );
        
        add_submenu_page(
            'evf-dashboard',
            __('Email Logları', 'email-verification-forms'),
            __('Email Logları', 'email-verification-forms'),
            'manage_options',
            'evf-email-logs',
            array($this, 'email_logs_page')
        );
        
        add_submenu_page(
            'evf-dashboard',
            __('Ayarlar', 'email-verification-forms'),
            __('Ayarlar', 'email-verification-forms'),
            'manage_options',
            'evf-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'evf-dashboard',
            __('Araçlar', 'email-verification-forms'),
            __('Araçlar', 'email-verification-forms'),
            'manage_options',
            'evf-tools',
            array($this, 'tools_page')
        );
    }
    
    /**
     * Admin scripts
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'evf-') === false) {
            return;
        }
        
        wp_enqueue_style('evf-admin-style', EVF_ASSETS_URL . 'css/evf-admin.css', array(), EVF_VERSION);
        wp_enqueue_script('evf-admin-script', EVF_ASSETS_URL . 'js/evf-admin.js', array('jquery', 'chart-js'), EVF_VERSION, true);
        
        // Chart.js
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
        
        wp_localize_script('evf-admin-script', 'evf_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('evf_admin_nonce'),
            'messages' => array(
                'test_email_sent' => __('Test e-postası gönderildi!', 'email-verification-forms'),
                'test_email_failed' => __('Test e-postası gönderilemedi.', 'email-verification-forms'),
                'export_started' => __('Dışa aktarma başlatıldı...', 'email-verification-forms'),
                'confirm_delete' => __('Bu işlemi geri alamazsınız. Emin misiniz?', 'email-verification-forms')
            )
        ));
    }
    
    /**
     * Admin init
     */
    public function admin_init() {
        // Ayarları kaydet - sanitization callback'leri ile
        register_setting('evf_settings', 'evf_token_expiry', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_token_expiry'),
            'default' => 24
        ));

        register_setting('evf_settings', 'evf_rate_limit', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_rate_limit'),
            'default' => 15
        ));

        register_setting('evf_settings', 'evf_min_password_length', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_password_length'),
            'default' => 8
        ));

        register_setting('evf_settings', 'evf_require_strong_password', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'default' => true
        ));

        register_setting('evf_settings', 'evf_admin_notifications', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'default' => true
        ));

        register_setting('evf_settings', 'evf_redirect_after_login', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_url'),
            'default' => home_url()
        ));

        register_setting('evf_settings', 'evf_brand_color', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_hex_color'),
            'default' => '#3b82f6'
        ));

        register_setting('evf_settings', 'evf_email_from_name', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_text_field'),
            'default' => get_bloginfo('name')
        ));

        register_setting('evf_settings', 'evf_email_from_email', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_email'),
            'default' => get_option('admin_email')
        ));
    }

    /**
     * Sanitization callback functions
     */

    /**
     * Token expiry sanitization
     */
    public function sanitize_token_expiry($value) {
        $value = intval($value);
        // 1 saat ile 168 saat (7 gün) arası
        return ($value >= 1 && $value <= 168) ? $value : 24;
    }

    /**
     * Rate limit sanitization
     */
    public function sanitize_rate_limit($value) {
        $value = intval($value);
        // 1 dakika ile 60 dakika arası
        return ($value >= 1 && $value <= 60) ? $value : 15;
    }

    /**
     * Password length sanitization
     */
    public function sanitize_password_length($value) {
        $value = intval($value);
        // 6 ile 50 karakter arası
        return ($value >= 6 && $value <= 50) ? $value : 8;
    }

    /**
     * Boolean sanitization
     */
    public function sanitize_boolean($value) {
        return (bool) $value;
    }

    /**
     * URL sanitization
     */
    public function sanitize_url($value) {
        $sanitized = esc_url_raw($value);
        // Eğer geçerli URL değilse home_url() döndür
        return filter_var($sanitized, FILTER_VALIDATE_URL) ? $sanitized : home_url();
    }

    /**
     * Hex color sanitization
     */
    public function sanitize_hex_color($value) {
        $sanitized = sanitize_hex_color($value);
        // Eğer geçerli hex renk değilse varsayılan rengi döndür
        return $sanitized ? $sanitized : '#3b82f6';
    }

    /**
     * Text field sanitization
     */
    public function sanitize_text_field($value) {
        return sanitize_text_field($value);
    }

    /**
     * Email sanitization
     */
    public function sanitize_email($value) {
        $sanitized = sanitize_email($value);
        // Eğer geçerli email değilse admin email'i döndür
        return is_email($sanitized) ? $sanitized : get_option('admin_email');
    }
    
    /**
     * Dashboard sayfası
     */
    public function dashboard_page() {
        $database = EVF_Database::instance();
        $stats = $database->get_registration_stats(30);
        $email_stats = $database->get_email_stats(30);
        $daily_trend = $database->get_daily_registration_trend(30);
        ?>
        <div class="wrap evf-admin-wrap">
            <h1><?php _e('Email Verification Dashboard', 'email-verification-forms'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="evf-stats-grid">
                <div class="evf-stat-card">
                    <div class="evf-stat-icon">📊</div>
                    <div class="evf-stat-content">
                        <h3><?php echo number_format($stats['total_attempts']); ?></h3>
                        <p><?php _e('Toplam Deneme', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php _e('Son 30 gün', 'email-verification-forms'); ?></span>
                    </div>
                </div>
                
                <div class="evf-stat-card">
                    <div class="evf-stat-icon">✅</div>
                    <div class="evf-stat-content">
                        <h3><?php echo number_format($stats['completed']); ?></h3>
                        <p><?php _e('Tamamlanan Kayıt', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php echo $stats['success_rate']; ?>% başarı oranı</span>
                    </div>
                </div>
                
                <div class="evf-stat-card">
                    <div class="evf-stat-icon">📧</div>
                    <div class="evf-stat-content">
                        <h3><?php echo number_format($stats['email_verified']); ?></h3>
                        <p><?php _e('Email Doğrulandı', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php echo $stats['email_verification_rate']; ?>% doğrulama oranı</span>
                    </div>
                </div>
                
                <div class="evf-stat-card">
                    <div class="evf-stat-icon">⏳</div>
                    <div class="evf-stat-content">
                        <h3><?php echo number_format($stats['pending']); ?></h3>
                        <p><?php _e('Bekleyen', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php _e('Doğrulama bekliyor', 'email-verification-forms'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="evf-charts-grid">
                <div class="evf-chart-container">
                    <h3><?php _e('Kayıt Trendi (Son 30 Gün)', 'email-verification-forms'); ?></h3>
                    <canvas id="evf-trend-chart"></canvas>
                </div>
                
                <div class="evf-chart-container">
                    <h3><?php _e('Email Durumu', 'email-verification-forms'); ?></h3>
                    <canvas id="evf-email-chart"></canvas>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="evf-recent-activity">
                <h3><?php _e('Son Aktiviteler', 'email-verification-forms'); ?></h3>
                <?php $this->render_recent_activity(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Trend Chart
            const trendData = <?php echo json_encode($daily_trend); ?>;
            const trendCtx = document.getElementById('evf-trend-chart').getContext('2d');
            
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendData.map(item => item.date),
                    datasets: [{
                        label: '<?php _e('Toplam Deneme', 'email-verification-forms'); ?>',
                        data: trendData.map(item => item.total_attempts),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3
                    }, {
                        label: '<?php _e('Tamamlanan', 'email-verification-forms'); ?>',
                        data: trendData.map(item => item.completed),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Email Status Chart
            const emailCtx = document.getElementById('evf-email-chart').getContext('2d');
            
            new Chart(emailCtx, {
                type: 'doughnut',
                data: {
                    labels: ['<?php _e('Başarılı', 'email-verification-forms'); ?>', '<?php _e('Başarısız', 'email-verification-forms'); ?>'],
                    datasets: [{
                        data: [<?php echo $email_stats['successful_emails']; ?>, <?php echo $email_stats['failed_emails']; ?>],
                        backgroundColor: ['#10b981', '#ef4444']
                    }]
                },
                options: {
                    responsive: true
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Kayıtlar sayfası
     */
    public function registrations_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        // Sayfalama
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Filtreleme
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $where_clause = '';
        $where_values = array();
        
        if ($status_filter && in_array($status_filter, array('pending', 'email_verified', 'completed', 'expired'))) {
            $where_clause = 'WHERE status = %s';
            $where_values[] = $status_filter;
        }
        
        // Toplam kayıt sayısı
        $total_query = "SELECT COUNT(*) FROM $table_name $where_clause";
        $total_items = $where_values ? $wpdb->get_var($wpdb->prepare($total_query, $where_values)) : $wpdb->get_var($total_query);
        $total_pages = ceil($total_items / $per_page);
        
        // Verileri getir
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $registrations = $wpdb->get_results($wpdb->prepare($query, $query_values));
        ?>
        
        <div class="wrap evf-admin-wrap">
            <h1><?php _e('Kayıt Denemeleri', 'email-verification-forms'); ?></h1>
            
            <!-- Filters -->
            <div class="evf-filters">
                <form method="get">
                    <input type="hidden" name="page" value="evf-registrations">
                    <select name="status" onchange="this.form.submit()">
                        <option value=""><?php _e('Tüm Durumlar', 'email-verification-forms'); ?></option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Bekleyen', 'email-verification-forms'); ?></option>
                        <option value="email_verified" <?php selected($status_filter, 'email_verified'); ?>><?php _e('Email Doğrulandı', 'email-verification-forms'); ?></option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Tamamlandı', 'email-verification-forms'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php _e('Süresi Doldu', 'email-verification-forms'); ?></option>
                    </select>
                </form>
            </div>
            
            <!-- Table -->
            <table class="wp-list-table widefat fixed striped evf-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php _e('E-posta', 'email-verification-forms'); ?></th>
                        <th><?php _e('Durum', 'email-verification-forms'); ?></th>
                        <th><?php _e('IP Adresi', 'email-verification-forms'); ?></th>
                        <th><?php _e('Oluşturulma', 'email-verification-forms'); ?></th>
                        <th><?php _e('Son Tarih', 'email-verification-forms'); ?></th>
                        <th><?php _e('İşlemler', 'email-verification-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registrations)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">
                                <?php _e('Kayıt bulunamadı.', 'email-verification-forms'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registrations as $registration): ?>
                            <tr>
                                <td><?php echo $registration->id; ?></td>
                                <td>
                                    <strong><?php echo esc_html($registration->email); ?></strong>
                                    <?php if ($registration->user_id): ?>
                                        <br><small>User ID: #<?php echo $registration->user_id; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="evf-status evf-status-<?php echo $registration->status; ?>">
                                        <?php 
                                        switch ($registration->status) {
                                            case 'pending':
                                                _e('Bekleyen', 'email-verification-forms');
                                                break;
                                            case 'email_verified':
                                                _e('Email Doğrulandı', 'email-verification-forms');
                                                break;
                                            case 'completed':
                                                _e('Tamamlandı', 'email-verification-forms');
                                                break;
                                            case 'expired':
                                                _e('Süresi Doldu', 'email-verification-forms');
                                                break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($registration->ip_address); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($registration->created_at)); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($registration->expires_at)); ?></td>
                                <td>
                                    <div class="evf-actions">
                                        <?php if ($registration->status === 'pending'): ?>
                                            <button class="button button-small evf-resend-email" data-email="<?php echo esc_attr($registration->email); ?>">
                                                <?php _e('Yeniden Gönder', 'email-verification-forms'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <button class="button button-small evf-view-details" data-id="<?php echo $registration->id; ?>">
                                            <?php _e('Detay', 'email-verification-forms'); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = admin_url('admin.php?page=evf-registrations');
                        if ($status_filter) {
                            $base_url = add_query_arg('status', $status_filter, $base_url);
                        }
                        
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%', $base_url),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Email logs sayfası
     */
    public function email_logs_page() {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'evf_email_logs';
        
        // Sayfalama
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Toplam log sayısı
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
        $total_pages = ceil($total_items / $per_page);
        
        // Logları getir
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $log_table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        ?>
        
        <div class="wrap evf-admin-wrap">
            <h1><?php _e('Email Logları', 'email-verification-forms'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped evf-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php _e('E-posta', 'email-verification-forms'); ?></th>
                        <th><?php _e('Tür', 'email-verification-forms'); ?></th>
                        <th><?php _e('Durum', 'email-verification-forms'); ?></th>
                        <th><?php _e('Tarih', 'email-verification-forms'); ?></th>
                        <th><?php _e('Hata', 'email-verification-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">
                                <?php _e('Log bulunamadı.', 'email-verification-forms'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log->id; ?></td>
                                <td><?php echo esc_html($log->email); ?></td>
                                <td>
                                    <span class="evf-email-type evf-type-<?php echo $log->email_type; ?>">
                                        <?php 
                                        switch ($log->email_type) {
                                            case 'verification':
                                                _e('Doğrulama', 'email-verification-forms');
                                                break;
                                            case 'welcome':
                                                _e('Hoş Geldin', 'email-verification-forms');
                                                break;
                                            case 'admin_notification':
                                                _e('Admin Bildirimi', 'email-verification-forms');
                                                break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="evf-email-status evf-status-<?php echo $log->status; ?>">
                                        <?php echo $log->status === 'sent' ? __('Gönderildi', 'email-verification-forms') : __('Başarısız', 'email-verification-forms'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y H:i:s', strtotime($log->created_at)); ?></td>
                                <td>
                                    <?php if ($log->error_message): ?>
                                        <span class="evf-error-message" title="<?php echo esc_attr($log->error_message); ?>">
                                            <?php _e('Hata var', 'email-verification-forms'); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Ayarlar sayfası
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('evf_settings_nonce');
            
            // Ayarları kaydet
            update_option('evf_token_expiry', intval($_POST['evf_token_expiry']));
            update_option('evf_rate_limit', intval($_POST['evf_rate_limit']));
            update_option('evf_min_password_length', intval($_POST['evf_min_password_length']));
            update_option('evf_require_strong_password', isset($_POST['evf_require_strong_password']));
            update_option('evf_admin_notifications', isset($_POST['evf_admin_notifications']));
            update_option('evf_redirect_after_login', esc_url_raw($_POST['evf_redirect_after_login']));
            update_option('evf_brand_color', sanitize_hex_color($_POST['evf_brand_color']));
            update_option('evf_email_from_name', sanitize_text_field($_POST['evf_email_from_name']));
            update_option('evf_email_from_email', sanitize_email($_POST['evf_email_from_email']));
            
            echo '<div class="notice notice-success"><p>' . __('Ayarlar kaydedildi!', 'email-verification-forms') . '</p></div>';
        }
        
        // Mevcut ayarları getir
        $token_expiry = get_option('evf_token_expiry', 24);
        $rate_limit = get_option('evf_rate_limit', 15);
        $min_password_length = get_option('evf_min_password_length', 8);
        $require_strong_password = get_option('evf_require_strong_password', true);
        $admin_notifications = get_option('evf_admin_notifications', true);
        $redirect_after_login = get_option('evf_redirect_after_login', home_url());
        $brand_color = get_option('evf_brand_color', '#3b82f6');
        $email_from_name = get_option('evf_email_from_name', get_bloginfo('name'));
        $email_from_email = get_option('evf_email_from_email', get_option('admin_email'));
        ?>
        
        <div class="wrap evf-admin-wrap">
            <h1><?php _e('Email Verification Ayarları', 'email-verification-forms'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('evf_settings_nonce'); ?>
                
                <div class="evf-settings-tabs">
                    <div class="evf-tab-nav">
                        <button type="button" class="evf-tab-btn active" data-tab="general"><?php _e('Genel', 'email-verification-forms'); ?></button>
                        <button type="button" class="evf-tab-btn" data-tab="email"><?php _e('E-posta', 'email-verification-forms'); ?></button>
                        <button type="button" class="evf-tab-btn" data-tab="security"><?php _e('Güvenlik', 'email-verification-forms'); ?></button>
                        <button type="button" class="evf-tab-btn" data-tab="design"><?php _e('Tasarım', 'email-verification-forms'); ?></button>
                    </div>
                    
                    <!-- General Tab -->
                    <div class="evf-tab-content active" id="tab-general">
                        <h3><?php _e('Genel Ayarlar', 'email-verification-forms'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Token Geçerlilik Süresi', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_token_expiry" value="<?php echo esc_attr($token_expiry); ?>" min="1" max="168" />
                                    <p class="description"><?php _e('Doğrulama bağlantısının geçerlilik süresi (saat)', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Kayıt Sonrası Yönlendirme', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="url" name="evf_redirect_after_login" value="<?php echo esc_attr($redirect_after_login); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Kullanıcı başarıyla kayıt olduktan sonra yönlendirilecek sayfa', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Admin Bildirimleri', 'email-verification-forms'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="evf_admin_notifications" <?php checked($admin_notifications); ?> />
                                        <?php _e('Yeni kayıtlar için admin\'e e-posta gönder', 'email-verification-forms'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Email Tab -->
                    <div class="evf-tab-content" id="tab-email">
                        <h3><?php _e('E-posta Ayarları', 'email-verification-forms'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Gönderen Adı', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="text" name="evf_email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Gönderen E-posta', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="email" name="evf_email_from_email" value="<?php echo esc_attr($email_from_email); ?>" class="regular-text" />
                                </td>
                            </tr>
                        </table>
                        
                        <div class="evf-test-email-section">
                            <h4><?php _e('E-posta Testi', 'email-verification-forms'); ?></h4>
                            <p><?php _e('E-posta şablonlarını test etmek için bir e-posta adresi girin:', 'email-verification-forms'); ?></p>
                            <input type="email" id="test-email-address" placeholder="test@example.com" />
                            <select id="test-email-type">
                                <option value="verification"><?php _e('Doğrulama E-postası', 'email-verification-forms'); ?></option>
                                <option value="welcome"><?php _e('Hoş Geldin E-postası', 'email-verification-forms'); ?></option>
                                <option value="admin-notification"><?php _e('Admin Bildirimi', 'email-verification-forms'); ?></option>
                            </select>
                            <button type="button" class="button" id="send-test-email"><?php _e('Test E-postası Gönder', 'email-verification-forms'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Security Tab -->
                    <div class="evf-tab-content" id="tab-security">
                        <h3><?php _e('Güvenlik Ayarları', 'email-verification-forms'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Rate Limiting', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="60" />
                                    <p class="description"><?php _e('Aynı kullanıcının tekrar kayıt denemesi için bekleme süresi (dakika)', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Minimum Parola Uzunluğu', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_min_password_length" value="<?php echo esc_attr($min_password_length); ?>" min="6" max="50" />
                                    <p class="description"><?php _e('Kullanıcıların seçebileceği minimum parola uzunluğu', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Güçlü Parola Zorunluluğu', 'email-verification-forms'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="evf_require_strong_password" <?php checked($require_strong_password); ?> />
                                        <?php _e('Parolada büyük harf, küçük harf ve rakam zorunlu olsun', 'email-verification-forms'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Design Tab -->
                    <div class="evf-tab-content" id="tab-design">
                        <h3><?php _e('Tasarım Ayarları', 'email-verification-forms'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Marka Rengi', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="color" name="evf_brand_color" value="<?php echo esc_attr($brand_color); ?>" />
                                    <p class="description"><?php _e('E-posta şablonları ve formlar için ana renk', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="evf-color-preview">
                            <h4><?php _e('Renk Önizleme', 'email-verification-forms'); ?></h4>
                            <div class="evf-preview-button" style="background-color: <?php echo esc_attr($brand_color); ?>;">
                                <?php _e('Örnek Buton', 'email-verification-forms'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="<?php _e('Ayarları Kaydet', 'email-verification-forms'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Araçlar sayfası
     */
    public function tools_page() {
        ?>
        <div class="wrap evf-admin-wrap">
            <h1><?php _e('Araçlar', 'email-verification-forms'); ?></h1>
            
            <div class="evf-tools-grid">
                <!-- Export Tool -->
                <div class="evf-tool-card">
                    <h3><?php _e('Veri Dışa Aktarma', 'email-verification-forms'); ?></h3>
                    <p><?php _e('Kayıt verilerini CSV formatında dışa aktarın.', 'email-verification-forms'); ?></p>
                    <form method="post" class="evf-export-form">
                        <label>
                            <input type="radio" name="export_type" value="all" checked>
                            <?php _e('Tüm kayıtlar', 'email-verification-forms'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="export_type" value="completed">
                            <?php _e('Sadece tamamlanan kayıtlar', 'email-verification-forms'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="export_type" value="pending">
                            <?php _e('Sadece bekleyen kayıtlar', 'email-verification-forms'); ?>
                        </label><br><br>
                        <button type="button" class="button button-primary" id="export-data">
                            <?php _e('Dışa Aktar', 'email-verification-forms'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Cleanup Tool -->
                <div class="evf-tool-card">
                    <h3><?php _e('Veritabanı Temizliği', 'email-verification-forms'); ?></h3>
                    <p><?php _e('Süresi dolmuş kayıtları ve eski logları temizleyin.', 'email-verification-forms'); ?></p>
                    <button type="button" class="button" id="cleanup-database">
                        <?php _e('Temizlemeyi Çalıştır', 'email-verification-forms'); ?>
                    </button>
                </div>
                
                <!-- Stats Reset -->
                <div class="evf-tool-card">
                    <h3><?php _e('İstatistik Sıfırlama', 'email-verification-forms'); ?></h3>
                    <p><?php _e('Tüm istatistikleri ve logları sıfırlayın.', 'email-verification-forms'); ?></p>
                    <button type="button" class="button button-secondary" id="reset-stats">
                        <?php _e('İstatistikleri Sıfırla', 'email-verification-forms'); ?>
                    </button>
                </div>
                
                <!-- System Info -->
                <div class="evf-tool-card evf-full-width">
                    <h3><?php _e('Sistem Bilgileri', 'email-verification-forms'); ?></h3>
                    <?php $this->render_system_info(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Son aktiviteleri render et
     */
    private function render_recent_activity() {
        $database = EVF_Database::instance();
        $recent = $database->get_recent_registrations(10);
        
        if (empty($recent)) {
            echo '<p>' . __('Henüz aktivite yok.', 'email-verification-forms') . '</p>';
            return;
        }
        
        echo '<div class="evf-activity-list">';
        foreach ($recent as $activity) {
            $status_class = 'evf-activity-' . $activity->status;
            $status_text = '';
            
            switch ($activity->status) {
                case 'pending':
                    $status_text = __('kayıt denemesi başlattı', 'email-verification-forms');
                    break;
                case 'email_verified':
                    $status_text = __('e-postasını doğruladı', 'email-verification-forms');
                    break;
                case 'completed':
                    $status_text = __('kaydını tamamladı', 'email-verification-forms');
                    break;
                case 'expired':
                    $status_text = __('kaydının süresi doldu', 'email-verification-forms');
                    break;
            }
            
            echo '<div class="evf-activity-item ' . $status_class . '">';
            echo '<div class="evf-activity-content">';
            echo '<strong>' . esc_html($activity->email) . '</strong> ' . $status_text;
            echo '<span class="evf-activity-time">' . human_time_diff(strtotime($activity->created_at)) . ' ' . __('önce', 'email-verification-forms') . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * Sistem bilgilerini render et
     */
    private function render_system_info() {
        global $wpdb;
        
        $info = array(
            'WordPress Version' => get_bloginfo('version'),
            'PHP Version' => PHP_VERSION,
            'MySQL Version' => $wpdb->db_version(),
            'Plugin Version' => EVF_VERSION,
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
            'Mail Function' => function_exists('mail') ? 'Available' : 'Not Available',
            'WP Mail Function' => function_exists('wp_mail') ? 'Available' : 'Not Available'
        );
        
        echo '<table class="evf-system-info">';
        foreach ($info as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($key) . ':</strong></td>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    /**
     * AJAX: Test e-postası gönder
     */
    public function ajax_test_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_admin_nonce')) {
            wp_send_json_error('invalid_nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('insufficient_permissions');
        }
        
        $email = sanitize_email($_POST['email']);
        $type = sanitize_text_field($_POST['type']);
        
        if (!is_email($email)) {
            wp_send_json_error('invalid_email');
        }
        
        $email_handler = EVF_Email::instance();
        $result = $email_handler->test_email($email, $type);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('send_failed');
        }
    }
    
    /**
     * AJAX: Veri dışa aktarma
     */
    public function ajax_export_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_admin_nonce')) {
            wp_send_json_error('invalid_nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('insufficient_permissions');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        $export_type = sanitize_text_field($_POST['export_type']);
        
        $where_clause = '';
        switch ($export_type) {
            case 'completed':
                $where_clause = "WHERE status = 'completed'";
                break;
            case 'pending':
                $where_clause = "WHERE status = 'pending'";
                break;
        }
        
        $data = $wpdb->get_results("SELECT * FROM $table_name $where_clause ORDER BY created_at DESC", ARRAY_A);
        
        // CSV header oluştur
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="evf-registrations-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV başlıkları
        fputcsv($output, array(
            'ID', 'Email', 'Status', 'IP Address', 'Created At', 'Email Verified At', 'Completed At', 'Expires At'
        ));
        
        // Veri satırları
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}