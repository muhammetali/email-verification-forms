<?php
/**
 * EVF Core Class
 * Ana işlevsellik sınıfı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Core {

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
        // Scripts ve styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('login_enqueue_scripts', array($this, 'enqueue_login_scripts'));

        // Custom endpoints
        add_action('wp_loaded', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_custom_endpoints'));

        // Koşullu WordPress login override (sadece WooCommerce yoksa)
        if (!evf_is_woocommerce_active()) {
            add_action('login_form_register', array($this, 'redirect_registration'));
        }

        // WooCommerce verification handling
        if (evf_is_woocommerce_active()) {
            add_action('init', array($this, 'handle_woocommerce_verification'));
        }

        // AJAX handlers
        add_action('wp_ajax_evf_check_email', array($this, 'ajax_check_email'));
        add_action('wp_ajax_nopriv_evf_check_email', array($this, 'ajax_check_email'));

        add_action('wp_ajax_evf_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_nopriv_evf_register_user', array($this, 'ajax_register_user'));

        add_action('wp_ajax_evf_set_password', array($this, 'ajax_set_password'));
        add_action('wp_ajax_nopriv_evf_set_password', array($this, 'ajax_set_password'));

        // Global verification check hooks
        add_action('wp_loaded', array($this, 'check_user_verification_status'));
    }

    /**
     * Frontend scripts ve styles
     */
    public function enqueue_frontend_scripts() {
        // CSS
        wp_enqueue_style(
            'evf-frontend-style',
            EVF_ASSETS_URL . 'css/evf-frontend.css',
            array(),
            EVF_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'evf-frontend-script',
            EVF_ASSETS_URL . 'js/evf-frontend.js',
            array('jquery'),
            EVF_VERSION,
            true
        );

        // Localize script
        wp_localize_script('evf-frontend-script', 'evf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('evf_nonce'),
            'plugin_mode' => evf_get_plugin_mode(),
            'is_woocommerce_active' => evf_is_woocommerce_active(),
            'messages' => array(
                'checking_email' => __('E-posta kontrol ediliyor...', 'email-verification-forms'),
                'sending_verification' => __('Doğrulama e-postası gönderiliyor...', 'email-verification-forms'),
                'email_sent' => __('Doğrulama e-postası gönderildi! Lütfen e-postanızı kontrol edin.', 'email-verification-forms'),
                'email_exists' => __('Bu e-posta adresi zaten kayıtlı.', 'email-verification-forms'),
                'invalid_email' => __('Geçerli bir e-posta adresi girin.', 'email-verification-forms'),
                'email_required' => __('E-posta adresi gerekli.', 'email-verification-forms'),
                'password_required' => __('Parola gerekli.', 'email-verification-forms'),
                'passwords_not_match' => __('Parolalar eşleşmiyor.', 'email-verification-forms'),
                'password_weak' => __('Parola çok zayıf. En az 8 karakter, büyük harf, küçük harf ve rakam içermelidir.', 'email-verification-forms'),
                'setting_password' => __('Parola kaydediliyor...', 'email-verification-forms'),
                'password_set' => __('Parola başarıyla kaydedildi! Giriş sayfasına yönlendiriliyorsunuz...', 'email-verification-forms'),
                'error' => __('Bir hata oluştu. Lütfen tekrar deneyin.', 'email-verification-forms'),
                'rate_limit' => __('Çok hızlı istekte bulunuyorsunuz. Lütfen bekleyin.', 'email-verification-forms'),
                'token_expired' => __('Doğrulama bağlantısının süresi dolmuş. Lütfen tekrar kayıt olmayı deneyin.', 'email-verification-forms'),
                'token_invalid' => __('Geçersiz doğrulama bağlantısı.', 'email-verification-forms'),
                'wc_verification_success' => __('E-posta doğrulandı! WooCommerce hesabınız aktif.', 'email-verification-forms')
            ),
            'settings' => array(
                'min_password_length' => get_option('evf_min_password_length', 8),
                'require_strong_password' => get_option('evf_require_strong_password', true),
                'login_url' => wp_login_url(),
                'wc_account_url' => evf_is_woocommerce_active() ? wc_get_page_permalink('myaccount') : ''
            )
        ));
    }

    /**
     * Login page scripts
     */
    public function enqueue_login_scripts() {
        $this->enqueue_frontend_scripts();
    }

    /**
     * Rewrite rules ekle
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^email-verification/verify/([^/]+)/?$',
            'index.php?evf_action=verify&evf_token=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^email-verification/set-password/([^/]+)/?$',
            'index.php?evf_action=set_password&evf_token=$matches[1]',
            'top'
        );

        // Query vars ekle
        add_filter('query_vars', function($vars) {
            $vars[] = 'evf_action';
            $vars[] = 'evf_token';
            return $vars;
        });
    }

    /**
     * Custom endpoints'leri handle et
     */
    public function handle_custom_endpoints() {
        $action = get_query_var('evf_action');
        $token = get_query_var('evf_token');

        if (!$action || !$token) {
            return;
        }

        switch ($action) {
            case 'verify':
                // WordPress mode verification
                if (!evf_is_woocommerce_active()) {
                    $this->handle_email_verification($token);
                }
                break;

            case 'set_password':
                // WordPress mode password setup
                if (!evf_is_woocommerce_active()) {
                    $this->handle_password_setup($token);
                }
                break;

            case 'wc_verify':
                // WooCommerce mode verification
                if (evf_is_woocommerce_active()) {
                    $this->handle_woocommerce_verification_direct($token);
                }
                break;
        }
    }

    /**
     * WooCommerce verification handling
     */
    public function handle_woocommerce_verification() {
        if (!isset($_GET['evf_action']) || $_GET['evf_action'] !== 'wc_verify') {
            return;
        }

        if (!isset($_GET['evf_token'])) {
            return;
        }

        $token = sanitize_text_field($_GET['evf_token']);
        $this->handle_woocommerce_verification_direct($token);
    }

    /**
     * WooCommerce verification token handle et
     */
    private function handle_woocommerce_verification_direct($token) {
        // Cache key for token validation
        $cache_key = 'evf_token_' . md5($token);
        $pending_verification = wp_cache_get($cache_key);

        if (false === $pending_verification) {
            // Cache miss - query database
            $pending_verification = $this->get_pending_verification_by_token($token);

            if ($pending_verification) {
                // Cache for 5 minutes
                wp_cache_set($cache_key, $pending_verification, '', 300);
            }
        }

        if (!$pending_verification) {
            if (evf_is_woocommerce_active()) {
                wp_redirect(add_query_arg('evf_error', 'invalid_token', wc_get_page_permalink('myaccount')));
            } else {
                $this->show_error_page(__('Geçersiz doğrulama bağlantısı.', 'email-verification-forms'));
            }
            exit;
        }

        // Token süresini kontrol et
        if (strtotime($pending_verification->expires_at) < time()) {
            // Clear cache for expired token
            wp_cache_delete($cache_key);

            if (evf_is_woocommerce_active()) {
                wp_redirect(add_query_arg('evf_error', 'expired_token', wc_get_page_permalink('myaccount')));
            } else {
                $this->show_error_page(__('Doğrulama bağlantısının süresi dolmuş.', 'email-verification-forms'));
            }
            exit;
        }

        // Verification'ı tamamla
        $user_id = $pending_verification->user_id;

        if ($user_id) {
            // Mevcut kullanıcı - sadece verification flag'ini güncelle
            update_user_meta($user_id, 'evf_email_verified', 1);
            update_user_meta($user_id, 'evf_verified_at', current_time('mysql'));

            // Update pending verification status
            $this->update_pending_verification_status($pending_verification->id, 'completed');

            // Clear cache after update
            wp_cache_delete($cache_key);
            wp_cache_delete('evf_user_verification_' . $user_id);

            // WooCommerce varsa My Account'a yönlendir
            if (evf_is_woocommerce_active()) {
                wp_redirect(add_query_arg('evf_success', 'verified', wc_get_page_permalink('myaccount')));
            } else {
                wp_redirect(add_query_arg('verified', '1', wp_login_url()));
            }

        } else {
            // WordPress mode - password setup gerekli
            if (!evf_is_woocommerce_active()) {
                // WordPress mode password setup sayfasına yönlendir
                wp_redirect(home_url('/email-verification/set-password/' . $token));
            } else {
                // WooCommerce mode'da bu durum olmamalı
                wp_redirect(add_query_arg('evf_error', 'invalid_state', wc_get_page_permalink('myaccount')));
            }
        }

        exit;
    }

    /**
     * Pending verification'ı token ile getir
     */
    private function get_pending_verification_by_token($token) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status IN ('wc_pending', 'pending')",
            $token
        ));
    }

    /**
     * Pending verification status'ını güncelle
     */
    private function update_pending_verification_status($id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update(
            $table_name,
            array(
                'status' => $status,
                'email_verified_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Kullanıcı verification durumunu kontrol et
     */
    public function check_user_verification_status() {
        // Sadece giriş yapmış kullanıcılar için
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Admin'leri atla
        if (current_user_can('manage_options')) {
            return;
        }

        // Verification durumunu kontrol et (cache ile)
        $is_verified = $this->is_user_verified_cached($user_id);

        if (!$is_verified) {
            // Unverified kullanıcı için gentle reminder
            $this->maybe_show_verification_reminder($user_id);
        }
    }

    /**
     * Cache'li kullanıcı verification kontrolü
     */
    private function is_user_verified_cached($user_id) {
        $cache_key = 'evf_user_verification_' . $user_id;
        $is_verified = wp_cache_get($cache_key);

        if (false === $is_verified) {
            // Cache miss - check user meta
            $is_verified = get_user_meta($user_id, 'evf_email_verified', true);
            $is_verified = $is_verified ? 'yes' : 'no';

            // Cache for 1 hour
            wp_cache_set($cache_key, $is_verified, '', 3600);
        }

        return $is_verified === 'yes';
    }

    /**
     * Verification reminder göster
     */
    private function maybe_show_verification_reminder($user_id) {
        // Sadece belirli sayfalarda göster
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // WooCommerce varsa kendi notification sistemini kullan
        if (evf_is_woocommerce_active()) {
            return;
        }

        // Frontend'de gentle reminder ekle
        add_action('wp_footer', array($this, 'show_frontend_verification_reminder'));
    }

    /**
     * Frontend verification reminder
     */
    public function show_frontend_verification_reminder() {
        $user = wp_get_current_user();
        ?>
        <div id="evf-verification-reminder" style="position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #f39c12, #e67e22); color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; max-width: 350px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="font-size: 20px;">🛡️</div>
                <div style="flex: 1;">
                    <strong style="display: block; margin-bottom: 5px;"><?php _e('E-posta Doğrulaması', 'email-verification-forms'); ?></strong>
                    <div style="font-size: 13px; opacity: 0.9;">
                        <?php
                        /* translators: %s: User email address (wrapped in <br><strong> tags) */
                        printf(__('Hesabınızı güvence altına almak için %s adresini doğrulayın.', 'email-verification-forms'), '<br><strong>' . esc_html($user->user_email) . '</strong>');
                        ?>
                    </div>
                </div>
                <button onclick="document.getElementById('evf-verification-reminder').style.display='none'" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 5px 8px; border-radius: 4px; cursor: pointer; font-size: 16px;">×</button>
            </div>
            <div style="margin-top: 10px; text-align: center;">
                <a href="<?php echo esc_url(wp_login_url() . '?action=register'); ?>" style="background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-size: 12px; display: inline-block;">
                    📧 <?php _e('Şimdi Doğrula', 'email-verification-forms'); ?>
                </a>
            </div>
        </div>

        <script>
            // Auto-hide after 10 seconds
            setTimeout(function() {
                var reminder = document.getElementById('evf-verification-reminder');
                if (reminder) {
                    reminder.style.opacity = '0';
                    reminder.style.transform = 'translateX(100%)';
                    reminder.style.transition = 'all 0.3s ease';
                    setTimeout(function() {
                        reminder.style.display = 'none';
                    }, 300);
                }
            }, 10000);
        </script>
        <?php
    }

    /**
     * WordPress kayıt sayfasını yönlendir
     */
    public function redirect_registration() {
        // Custom registration page'e yönlendir
        wp_redirect(home_url('/email-verification/register/'));
        exit;
    }

    /**
     * Email verification handle
     */
    private function handle_email_verification($token) {
        $registration = EVF_Registration::instance();
        $result = $registration->verify_email($token);

        if ($result['success']) {
            // Password setup sayfasına yönlendir
            wp_redirect(home_url('/email-verification/set-password/' . $token));
            exit;
        } else {
            // Hata sayfası göster
            $this->show_error_page($result['message']);
        }
    }

    /**
     * Password setup handle
     */
    private function handle_password_setup($token) {
        $registration = EVF_Registration::instance();
        $pending_user = $registration->get_pending_user_by_token($token);

        if (!$pending_user || $registration->is_token_expired($pending_user->created_at)) {
            $this->show_error_page(__('Geçersiz veya süresi dolmuş bağlantı.', 'email-verification-forms'));
            return;
        }

        // Password setup template'ini göster
        $this->show_password_setup_page($token, $pending_user->email);
    }

    /**
     * AJAX: Email kontrolü
     */
    public function ajax_check_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error('invalid_email');
        }

        // Email'in mevcut olup olmadığını kontrol et
        if (email_exists($email)) {
            wp_send_json_error('email_exists');
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Kullanıcı kaydı
     */
    public function ajax_register_user() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);

        if (!is_email($email)) {
            wp_send_json_error('invalid_email');
        }

        // Rate limiting kontrolü
        if ($this->check_rate_limit($email)) {
            wp_send_json_error('rate_limit');
        }

        // Plugin moduna göre farklı handling
        if (evf_is_woocommerce_active()) {
            // WooCommerce mode - direkt email gönder, kullanıcı oluşturma WooCommerce'e bırak
            wp_send_json_error('wc_mode_not_supported');
        } else {
            // WordPress mode - normal registration flow
            $registration = EVF_Registration::instance();
            $result = $registration->start_registration($email);

            if ($result['success']) {
                wp_send_json_success();
            } else {
                wp_send_json_error($result['error']);
            }
        }
    }

    /**
     * AJAX: Parola belirleme
     */
    public function ajax_set_password() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $token = sanitize_text_field($_POST['token']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        // Parola validasyonu
        if (empty($password) || empty($password_confirm)) {
            wp_send_json_error('password_required');
        }

        if ($password !== $password_confirm) {
            wp_send_json_error('passwords_not_match');
        }

        // Parola güçlülük kontrolü
        if (!$this->is_password_strong($password)) {
            wp_send_json_error('password_weak');
        }

        $registration = EVF_Registration::instance();
        $result = $registration->complete_registration($token, $password);

        if ($result['success']) {
            wp_send_json_success(array(
                'login_url' => wp_login_url(get_option('evf_redirect_after_login', home_url()))
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }

    /**
     * Rate limiting kontrolü (cache ile optimize edildi)
     */
    private function check_rate_limit($email) {
        $cache_key = 'evf_rate_limit_' . md5($email . $_SERVER['REMOTE_ADDR']);
        $cache_group = 'evf_rate_limits';

        $last_attempt = wp_cache_get($cache_key, $cache_group);
        $rate_limit_minutes = get_option('evf_rate_limit', 15);

        if ($last_attempt && (time() - $last_attempt) < ($rate_limit_minutes * 60)) {
            return true;
        }

        // Set cache with expiration
        wp_cache_set($cache_key, time(), $cache_group, $rate_limit_minutes * 60);
        return false;
    }

    /**
     * Parola güçlülük kontrolü
     */
    private function is_password_strong($password) {
        $min_length = get_option('evf_min_password_length', 8);

        if (strlen($password) < $min_length) {
            return false;
        }

        if (!get_option('evf_require_strong_password', true)) {
            return true;
        }

        // En az bir büyük harf, bir küçük harf ve bir rakam
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Hata sayfası göster
     */
    private function show_error_page($message) {
        include EVF_TEMPLATES_PATH . 'error-page.php';
        exit;
    }

    /**
     * Parola belirleme sayfası göster
     */
    private function show_password_setup_page($token, $email) {
        include EVF_TEMPLATES_PATH . 'password-setup.php';
        exit;
    }
}