<?php
/**
 * EVF Admin Class
 * Admin panel ana işlemleri sınıfı
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

        // Alt sınıfları yükle
        $this->load_sub_classes();
    }

    /**
     * Alt sınıfları yükle
     */
    private function load_sub_classes() {
        // Dashboard sınıfını yükle
        require_once EVF_INCLUDES_PATH . 'class-evf-admin-dashboard.php';
        EVF_Admin_Dashboard::instance();

        // Pages sınıfını yükle
        require_once EVF_INCLUDES_PATH . 'class-evf-admin-pages.php';
        EVF_Admin_Pages::instance();
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
            array('EVF_Admin_Dashboard', 'dashboard_page'),
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
            array('EVF_Admin_Dashboard', 'dashboard_page')
        );

        add_submenu_page(
            'evf-dashboard',
            __('Kayıtlar', 'email-verification-forms'),
            __('Kayıtlar', 'email-verification-forms'),
            'manage_options',
            'evf-registrations',
            array('EVF_Admin_Pages', 'registrations_page')
        );

        add_submenu_page(
            'evf-dashboard',
            __('Email Logları', 'email-verification-forms'),
            __('Email Logları', 'email-verification-forms'),
            'manage_options',
            'evf-email-logs',
            array('EVF_Admin_Pages', 'email_logs_page')
        );

        add_submenu_page(
            'evf-dashboard',
            __('Ayarlar', 'email-verification-forms'),
            __('Ayarlar', 'email-verification-forms'),
            'manage_options',
            'evf-settings',
            array('EVF_Admin_Pages', 'settings_page')
        );

        add_submenu_page(
            'evf-dashboard',
            __('Araçlar', 'email-verification-forms'),
            __('Araçlar', 'email-verification-forms'),
            'manage_options',
            'evf-tools',
            array('EVF_Admin_Pages', 'tools_page')
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