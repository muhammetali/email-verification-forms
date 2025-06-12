<?php
/**
 * EVF WooCommerce Integration Class
 * WooCommerce entegrasyon sınıfı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // WooCommerce'in tam yüklenmesini bekle
        add_action('woocommerce_loaded', array($this, 'init_hooks'));
        
        // Eğer WooCommerce zaten yüklenmişse direkt init et
        if (did_action('woocommerce_loaded')) {
            $this->init_hooks();
        }
    }
    
    /**
     * WooCommerce hook'larını başlat
     */
    private function init_hooks() {
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Initializing hooks');
        }
        
        // Müşteri oluşturma hook'ları
        add_action('woocommerce_created_customer', array($this, 'handle_customer_registration'), 10, 3);
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_checkout_registration'), 10, 3);
        
        // WooCommerce email'lerini deaktif et
        add_action('init', array($this, 'disable_woocommerce_customer_emails'), 20);
        
        // My Account hooks
        add_action('woocommerce_register_form_end', array($this, 'add_registration_notice'));
        add_action('woocommerce_account_dashboard', array($this, 'show_verification_notice'), 5);
        
        // Email verification restrictions
        add_action('woocommerce_account_content', array($this, 'restrict_account_sections'), 5);
        add_filter('woocommerce_account_menu_items', array($this, 'filter_account_menu'), 10, 1);
        
        // Profile update restrictions
        add_action('woocommerce_save_account_details_errors', array($this, 'restrict_profile_updates'), 10, 1);
        
        // Admin hooks
        add_action('show_user_profile', array($this, 'add_verification_status_field'));
        add_action('edit_user_profile', array($this, 'add_verification_status_field'));
        add_action('personal_options_update', array($this, 'save_verification_status_field'));
        add_action('edit_user_profile_update', array($this, 'save_verification_status_field'));
        
        // Custom verification endpoint
        add_action('init', array($this, 'add_verification_endpoint'), 5);
        add_filter('woocommerce_account_menu_items', array($this, 'add_verification_menu_item'), 40);
        add_action('woocommerce_account_email-verification_endpoint', array($this, 'verification_endpoint_content'));
        
        // AJAX handlers
        add_action('wp_ajax_evf_wc_resend_verification', array($this, 'ajax_resend_verification'));
        add_action('wp_ajax_nopriv_evf_wc_resend_verification', array($this, 'ajax_resend_verification'));
        
        // Password setup AJAX
        add_action('wp_ajax_evf_wc_set_password', array($this, 'ajax_set_password'));
        add_action('wp_ajax_nopriv_evf_wc_set_password', array($this, 'ajax_set_password'));
        
        // 🔧 FIX: Verification token handling - Daha geç hook kullan
        add_action('init', array($this, 'handle_verification_redirect'), 1);
    }
    
    /**
     * WooCommerce müşteri email'lerini deaktif et
     */
    public function disable_woocommerce_customer_emails() {
        // Customer New Account email'ini kapat
        remove_action('woocommerce_created_customer_notification', array('WC_Emails', 'customer_new_account'), 10, 3);
        
        // Email sınıfları üzerinden deaktif et
        add_filter('woocommerce_email_enabled_customer_new_account', '__return_false');
        
        // Email class'ını hook'dan çıkar
        if (class_exists('WC_Email_Customer_New_Account')) {
            remove_action('woocommerce_created_customer_notification', array('WC_Email_Customer_New_Account', 'trigger'));
        }
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Customer emails disabled');
        }
    }
    
    /**
     * WooCommerce müşteri kaydını handle et
     */
    public function handle_customer_registration($customer_id, $new_customer_data, $password_generated) {
        // Müşteri email'ini al
        $customer_email = $new_customer_data['user_email'];
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EVF WooCommerce: New customer registered - ID: $customer_id, Email: $customer_email, Password generated: " . ($password_generated ? 'YES' : 'NO'));
        }
        
        // Geçici güçlü parola oluştur ve kaydet
        $temp_password = wp_generate_password(16, true);
        wp_set_password($temp_password, $customer_id);
        
        // Kullanıcıyı unverified + password_change_required olarak işaretle
        update_user_meta($customer_id, 'evf_email_verified', 0);
        update_user_meta($customer_id, 'evf_password_change_required', 1);
        update_user_meta($customer_id, 'evf_temp_password_set', current_time('mysql'));
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EVF WooCommerce: Temporary password set for user $customer_id");
        }
        
        // Email verification başlat
        $this->start_email_verification($customer_id, $customer_email);
        
        // WooCommerce'in kendi welcome email'ini biraz geciktir
        $this->delay_woocommerce_emails($customer_id);
    }
    
    /**
     * Checkout sırasında yapılan kayıtları handle et
     */
    public function handle_checkout_registration($order_id, $posted_data, $order) {
        // Checkout sırasında hesap oluşturuldu mu kontrol et
        if (!isset($posted_data['createaccount']) || !$posted_data['createaccount']) {
            return;
        }
        
        // Müşteri ID'sini al
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        $customer_email = $order->get_billing_email();
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("EVF WooCommerce: Checkout registration - Order: $order_id, Customer: $customer_id, Email: $customer_email");
        }
        
        // Email verification başlat
        $this->start_email_verification($customer_id, $customer_email, array(
            'context' => 'checkout',
            'order_id' => $order_id
        ));
    }
    
    /**
     * Email verification sürecini başlat
     */
    private function start_email_verification($user_id, $email, $context = array()) {
        // User'ı unverified olarak işaretle
        update_user_meta($user_id, 'evf_email_verified', 0);
        update_user_meta($user_id, 'evf_verification_sent_at', current_time('timestamp'));
        update_user_meta($user_id, 'evf_verification_context', $context);
        
        // Pending registrations tablosuna ekle
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        $token = wp_generate_password(32, false);
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . get_option('evf_token_expiry', 24) . ' hours'));
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Inserting token with status: wc_pending');
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'token' => $token,
                'status' => 'pending', // Geçici olarak 'pending' kullan
                'user_id' => $user_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s') // Format array
        );
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($result === false) {
                error_log('EVF WooCommerce: Database insert failed: ' . $wpdb->last_error);
            } else {
                error_log('EVF WooCommerce: Database insert successful, ID: ' . $wpdb->insert_id);
            }
        }
        
        // Verification email gönder
        $this->send_woocommerce_verification_email($email, $token, $user_id, $context);
        
        // Admin'e bildirim gönder (eğer enabled ise)
        if (get_option('evf_admin_notifications', true)) {
            $this->send_admin_notification($user_id, $email, $context);
        }
    }
    
    /**
     * WooCommerce verification email'i gönder
     */
    private function send_woocommerce_verification_email($email, $token, $user_id, $context = array()) {
        // WooCommerce My Account URL'ini kullan
        $verification_url = add_query_arg(array(
            'evf_action' => 'wc_verify',
            'evf_token' => $token
        ), wc_get_page_permalink('myaccount'));
        
        $user = get_userdata($user_id);
        $user_name = $user->display_name ?: $user->user_login;
        
        $subject = sprintf(__('[%s] E-posta Adresinizi Doğrulayın', 'email-verification-forms'), get_bloginfo('name'));
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Sending verification email to ' . $email . ' with URL: ' . $verification_url);
        }
        
        // WooCommerce email template yapısını kullan
        $email_content = $this->get_woocommerce_email_template($verification_url, $user_name, $email, $context);
        
        // WooCommerce email class'ını kullan
        $mailer = WC()->mailer();
        $wrapped_message = $mailer->wrap_message($subject, $email_content);
        
        $result = $mailer->send($email, $subject, $wrapped_message);
        
        // Email log'a kaydet
        EVF_Database::instance()->log_email($email, 'wc_verification', $result ? 'sent' : 'failed', null, $user_id);
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email sent result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }
        
        return $result;
    }
    
    /**
     * WooCommerce email template'i
     */
    private function get_woocommerce_email_template($verification_url, $user_name, $email, $context) {
        $site_name = get_bloginfo('name');
        $brand_color = get_option('evf_brand_color', '#96588a'); // WooCommerce default color
        
        $context_message = '';
        if (isset($context['context']) && $context['context'] === 'checkout') {
            $context_message = '<p style="margin-bottom: 20px; color: #666;">' . 
                __('Siparişiniz başarıyla alındı. Hesabınızın güvenliği için e-posta adresinizi doğrulamanız gerekmektedir.', 'email-verification-forms') . 
                '</p>';
        }
        
        return sprintf('
            <div style="background-color: #f7f7f7; margin: 0; padding: 70px 0; width: 100%%;">
                <table border="0" cellpadding="0" cellspacing="0" height="100%%" width="100%%">
                    <tr>
                        <td align="center" valign="top">
                            <div style="max-width: 600px; background-color: #ffffff; border-radius: 6px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin: 0 auto;">
                                <!-- Header -->
                                <div style="background: linear-gradient(135deg, %s, %s); padding: 30px; text-align: center; border-radius: 6px 6px 0 0;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 300;">
                                        🛡️ E-posta Doğrulaması
                                    </h1>
                                </div>
                                
                                <!-- Content -->
                                <div style="padding: 40px 30px;">
                                    <h2 style="color: #333; margin: 0 0 20px 0; font-size: 18px;">
                                        Merhaba %s,
                                    </h2>
                                    
                                    %s
                                    
                                    <p style="margin-bottom: 30px; color: #666; line-height: 1.6;">
                                        <strong>%s</strong> hesabınızın güvenliği için e-posta adresinizi doğrulamanız gerekmektedir. 
                                        Bu işlem sadece birkaç saniye sürer ve hesabınızı güvence altına alır.
                                    </p>
                                    
                                    <!-- CTA Button -->
                                    <div style="text-align: center; margin: 30px 0;">
                                        <a href="%s" style="background: linear-gradient(135deg, %s, %s); color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: 600; display: inline-block; box-shadow: 0 2px 8px rgba(150,88,138,0.3);">
                                            ✅ E-postamı Doğrula
                                        </a>
                                    </div>
                                    
                                    <!-- Alternative Link -->
                                    <div style="background-color: #f9f9f9; padding: 20px; border-radius: 4px; margin: 20px 0; border-left: 4px solid %s;">
                                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                            Butona tıklayamıyorsanız, aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:
                                        </p>
                                        <p style="margin: 0; font-size: 12px; color: #999; word-break: break-all;">
                                            %s
                                        </p>
                                    </div>
                                    
                                    <!-- Benefits -->
                                    <div style="background-color: #f0f8ff; padding: 20px; border-radius: 4px; margin: 20px 0;">
                                        <h3 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">
                                            ✨ Doğrulama Sonrası Avantajlarınız:
                                        </h3>
                                        <ul style="margin: 0; padding-left: 20px; color: #666;">
                                            <li style="margin-bottom: 8px;">Hesabınız tam güvenlik altında</li>
                                            <li style="margin-bottom: 8px;">Tüm özellikler aktif</li>
                                            <li style="margin-bottom: 8px;">Önemli bildirimleri kaçırmayın</li>
                                            <li style="margin-bottom: 0;">VIP müşteri desteği</li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Warning -->
                                    <div style="border-left: 4px solid #f39c12; background-color: #fef9e7; padding: 15px; margin: 20px 0;">
                                        <p style="margin: 0; font-size: 14px; color: #8a6d3b;">
                                            <strong>⏰ Önemli:</strong> Bu bağlantı %d saat geçerlidir. 
                                            Süre dolmadan önce doğrulamayı tamamlayın.
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Footer -->
                                <div style="background-color: #f8f8f8; padding: 20px 30px; text-align: center; border-radius: 0 0 6px 6px; border-top: 1px solid #eee;">
                                    <p style="margin: 0 0 10px 0; font-size: 14px; color: #666;">
                                        Bu e-posta <strong>%s</strong> tarafından gönderilmiştir.
                                    </p>
                                    <p style="margin: 0; font-size: 12px; color: #999;">
                                        <a href="%s" style="color: %s; text-decoration: none;">%s</a>
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>',
            esc_attr($brand_color),
            esc_attr($brand_color),
            esc_html($user_name),
            $context_message,
            esc_html($site_name),
            esc_url($verification_url),
            esc_attr($brand_color),
            esc_attr($brand_color),
            esc_attr($brand_color),
            esc_url($verification_url),
            get_option('evf_token_expiry', 24),
            esc_html($site_name),
            esc_url(home_url()),
            esc_attr($brand_color),
            esc_html(home_url())
        );
    }
    
    /**
     * WooCommerce verification endpoint ekle
     */
    public function add_verification_endpoint() {
        add_rewrite_endpoint('email-verification', EP_PAGES);
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email verification endpoint added');
        }
        
        // Endpoint'leri flush et (sadece ilk seferde)
        if (get_option('evf_endpoints_flushed') !== 'yes') {
            flush_rewrite_rules();
            update_option('evf_endpoints_flushed', 'yes');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Rewrite rules flushed');
            }
        }
    }
    
    /**
     * Verification redirect'ini handle et
     */
    public function handle_verification_redirect() {
        // Sadece frontend'de çalış
        if (is_admin()) {
            return;
        }
        
        // URL'de evf_action ve evf_token var mı kontrol et
        if (isset($_GET['evf_action']) && isset($_GET['evf_token'])) {
            $action = sanitize_text_field($_GET['evf_action']);
            $token = sanitize_text_field($_GET['evf_token']);
            
            // Debug log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Verification redirect triggered with action: ' . $action . ', token: ' . substr($token, 0, 8) . '...');
            }
            
            switch ($action) {
                case 'wc_verify':
                    $this->process_verification_token($token);
                    break;
                    
                case 'set_password':
                    // Parola sayfası için template_redirect kullan
                    add_action('template_redirect', function() use ($token) {
                        $this->show_password_setup_page($token);
                    });
                    break;
            }
        }
    }
    
    /**
     * Parola belirleme sayfasını göster
     */
    private function show_password_setup_page($token) {
        // Token'ı doğrula
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status = 'completed'",
            $token
        ));
        
        if (!$verification || !$verification->user_id) {
            wp_redirect(add_query_arg('evf_error', 'invalid_token', wc_get_page_permalink('myaccount')));
            exit;
        }
        
        $user = get_userdata($verification->user_id);
        if (!$user) {
            wp_redirect(add_query_arg('evf_error', 'user_not_found', wc_get_page_permalink('myaccount')));
            exit;
        }
        
        // Parola değiştirme gerekli mi?
        $password_change_required = get_user_meta($verification->user_id, 'evf_password_change_required', true);
        if (!$password_change_required) {
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
        
        // Template göster - WooCommerce cart sorununu önlemek için
        $this->render_password_setup_template_safe($token, $user);
    }
    
    /**
     * Parola belirleme template'ini render et
     */
    private function render_password_setup_template_safe($token, $user) {
        // WooCommerce cart sorununu önlemek için custom header kullan
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html(get_bloginfo('name')); ?> - Parola Belirleme</title>
            <?php wp_head(); ?>
        </head>
        <body <?php body_class('woocommerce-page woocommerce-account evf-password-setup-page'); ?>>
        
        <div class="woocommerce">
            <div class="woocommerce-notices-wrapper"></div>
            
            <div class="evf-wc-password-setup" style="max-width: 600px; margin: 40px auto; padding: 40px 20px;">
                
                <!-- Progress Steps -->
                <div class="evf-progress-steps" style="display: flex; justify-content: center; margin-bottom: 30px;">
                    <div class="evf-step completed">
                        <div class="evf-step-circle">✓</div>
                        <span>E-posta</span>
                    </div>
                    <div class="evf-step completed">
                        <div class="evf-step-circle">✓</div>
                        <span>Doğrulama</span>
                    </div>
                    <div class="evf-step active">
                        <div class="evf-step-circle">3</div>
                        <span>Parola</span>
                    </div>
                </div>
                
                <!-- Header -->
                <div class="evf-setup-header" style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #333; margin-bottom: 10px;">🔐 Parolanızı Belirleyin</h1>
                    <p style="color: #666; font-size: 16px;">
                        Merhaba <strong><?php echo esc_html($user->display_name ?: $user->user_login); ?></strong>,<br>
                        Hesabınızın güvenliği için lütfen yeni bir parola belirleyin.
                    </p>
                </div>
                
                <!-- Password Form -->
                <form id="evf-wc-password-form" style="background: #f8f9fa; padding: 30px; border-radius: 8px; border: 1px solid #e9ecef;">
                    <input type="hidden" name="evf_token" value="<?php echo esc_attr($token); ?>">
                    <input type="hidden" name="action" value="evf_wc_set_password">
                    <?php wp_nonce_field('evf_wc_password_nonce', 'evf_nonce'); ?>
                    
                    <div class="evf-form-row" style="margin-bottom: 20px;">
                        <label for="evf_new_password" style="display: block; font-weight: 600; margin-bottom: 8px;">
                            Yeni Parola <span style="color: red;">*</span>
                        </label>
                        <input type="password" id="evf_new_password" name="new_password" 
                               class="input-text" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;"
                               required minlength="<?php echo get_option('evf_min_password_length', 8); ?>">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            En az <?php echo get_option('evf_min_password_length', 8); ?> karakter, büyük harf, küçük harf ve rakam içermelidir.
                        </small>
                    </div>
                    
                    <div class="evf-form-row" style="margin-bottom: 25px;">
                        <label for="evf_confirm_password" style="display: block; font-weight: 600; margin-bottom: 8px;">
                            Parola Tekrar <span style="color: red;">*</span>
                        </label>
                        <input type="password" id="evf_confirm_password" name="confirm_password" 
                               class="input-text" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;"
                               required>
                    </div>
                    
                    <!-- Password Strength -->
                    <div id="evf_password_strength" style="margin-bottom: 20px; display: none;">
                        <div style="height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden; margin-bottom: 8px;">
                            <div id="evf_strength_bar" style="height: 100%; transition: all 0.3s ease; width: 0%;"></div>
                        </div>
                        <small id="evf_strength_text" style="color: #666;"></small>
                    </div>
                    
                    <div class="evf-form-row">
                        <button type="submit" class="button alt wp-element-button" 
                                style="width: 100%; padding: 15px; font-size: 16px; font-weight: 600;">
                            <span class="evf-btn-text">🚀 Hesabımı Aktifleştir</span>
                            <span class="evf-btn-loading" style="display: none;">
                                <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px;"></span>
                                Parola kaydediliyor...
                            </span>
                        </button>
                    </div>
                </form>
                
                <!-- Security Info -->
                <div class="evf-security-notice" style="background: #e8f4fd; border-left: 4px solid #2196f3; padding: 15px; margin-top: 20px; border-radius: 4px;">
                    <p style="margin: 0; color: #1976d2; font-size: 14px;">
                        <strong>🛡️ Güvenlik:</strong> Parolanız şifrelenerek saklanır ve hiçbir zaman görüntülenemez.
                    </p>
                </div>
                
                <!-- Back to Site Link -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo esc_url(home_url()); ?>" style="color: #666; text-decoration: none; font-size: 14px;">
                        ← <?php echo esc_html(get_bloginfo('name')); ?> Ana Sayfaya Dön
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .evf-progress-steps {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .evf-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .evf-step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            background: #e9ecef;
            color: #6c757d;
        }
        
        .evf-step.completed .evf-step-circle {
            background: #28a745;
            color: white;
        }
        
        .evf-step.active .evf-step-circle {
            background: #007cba;
            color: white;
        }
        
        .evf-step span {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .evf-step.active span {
            color: #007cba;
            font-weight: 600;
        }
        
        body.evf-password-setup-page {
            background: #f5f5f5;
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength checker
            const passwordInput = document.getElementById('evf_new_password');
            const confirmInput = document.getElementById('evf_confirm_password');
            const strengthDiv = document.getElementById('evf_password_strength');
            const strengthBar = document.getElementById('evf_strength_bar');
            const strengthText = document.getElementById('evf_strength_text');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                if (password.length === 0) {
                    strengthDiv.style.display = 'none';
                    return;
                }
                
                strengthDiv.style.display = 'block';
                
                const strength = checkPasswordStrength(password);
                strengthBar.style.width = strength.width;
                strengthBar.style.backgroundColor = strength.color;
                strengthText.textContent = strength.text;
                strengthText.style.color = strength.color;
            });
            
            // Password confirmation
            confirmInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirm = this.value;
                
                if (confirm.length > 0) {
                    if (password === confirm) {
                        this.style.borderColor = '#28a745';
                    } else {
                        this.style.borderColor = '#dc3545';
                    }
                } else {
                    this.style.borderColor = '#ddd';
                }
            });
            
            // Form submission
            document.getElementById('evf-wc-password-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const form = this;
                const btn = form.querySelector('button[type="submit"]');
                const btnText = btn.querySelector('.evf-btn-text');
                const btnLoading = btn.querySelector('.evf-btn-loading');
                
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                
                // Validation
                if (!password || !confirm) {
                    alert('Lütfen tüm alanları doldurun.');
                    return;
                }
                
                if (password !== confirm) {
                    alert('Parolalar eşleşmiyor.');
                    return;
                }
                
                if (!isPasswordStrong(password)) {
                    alert('Parola çok zayıf. Lütfen güçlü bir parola seçin.');
                    return;
                }
                
                // Set loading state
                btn.disabled = true;
                btnText.style.display = 'none';
                btnLoading.style.display = 'inline-block';
                
                // AJAX request
                const formData = new FormData(form);
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.data.redirect_url || '<?php echo wc_get_page_permalink('myaccount'); ?>';
                    } else {
                        alert(data.data.message || 'Bir hata oluştu.');
                        btn.disabled = false;
                        btnText.style.display = 'inline-block';
                        btnLoading.style.display = 'none';
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    btn.disabled = false;
                    btnText.style.display = 'inline-block';
                    btnLoading.style.display = 'none';
                });
            });
            
            function checkPasswordStrength(password) {
                let score = 0;
                if (password.length >= 8) score++;
                if (/[a-z]/.test(password)) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                
                switch (score) {
                    case 0:
                    case 1:
                        return {width: '25%', color: '#dc3545', text: 'Çok Zayıf'};
                    case 2:
                        return {width: '50%', color: '#fd7e14', text: 'Zayıf'};
                    case 3:
                        return {width: '75%', color: '#ffc107', text: 'Orta'};
                    case 4:
                        return {width: '100%', color: '#28a745', text: 'Güçlü'};
                    case 5:
                        return {width: '100%', color: '#20c997', text: 'Çok Güçlü'};
                }
            }
            
            function isPasswordStrong(password) {
                return password.length >= <?php echo get_option('evf_min_password_length', 8); ?> && 
                       /[a-z]/.test(password) && 
                       /[A-Z]/.test(password) && 
                       /[0-9]/.test(password);
            }
        });
        </script>
        
        <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Verification token'ını işle
     */
    private function process_verification_token($token) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        // Debug log - başlangıç
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Processing verification token: ' . substr($token, 0, 8) . '...');
        }
        
        // Token'ı kontrol et
        $pending_verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status IN ('wc_pending', 'pending')",
            $token
        ));
        
        if (!$pending_verification) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Invalid token - not found in pending status');
            }
            wp_redirect(add_query_arg('evf_error', 'invalid_token', wc_get_page_permalink('myaccount')));
            exit;
        }
        
        // Debug log - token bulundu
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Token found for user ID: ' . $pending_verification->user_id);
        }
        
        // Token süresini kontrol et
        if (strtotime($pending_verification->expires_at) < time()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Token expired');
            }
            wp_redirect(add_query_arg('evf_error', 'expired_token', wc_get_page_permalink('myaccount')));
            exit;
        }
        
        // Verification'ı tamamla
        $user_id = $pending_verification->user_id;
        
        if ($user_id) {
            // User meta güncelle
            update_user_meta($user_id, 'evf_email_verified', 1);
            update_user_meta($user_id, 'evf_verified_at', current_time('mysql'));
            
            // Pending table güncelle
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'email_verified_at' => current_time('mysql')
                ),
                array('id' => $pending_verification->id)
            );
            
            // Debug log - verification tamamlandı
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Verification completed for user ID: ' . $user_id);
            }
            
            // 🔧 FIX: Parola değiştirme kontrolü - daha detaylı debug
            $password_change_required = get_user_meta($user_id, 'evf_password_change_required', true);
            
            // Debug log - parola kontrolü
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Password change required check for user ' . $user_id . ': ' . ($password_change_required ? 'YES' : 'NO'));
                error_log('EVF WooCommerce: Raw meta value: ' . var_export($password_change_required, true));
            }
            
            // String '1' veya integer 1 kontrolü
            if ($password_change_required == 1 || $password_change_required === '1') {
                // Debug log - parola sayfasına yönlendiriliyor
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EVF WooCommerce: Redirecting to password setup page');
                }
                
                // Parola belirleme sayfasına yönlendir
                $redirect_url = add_query_arg(array(
                    'evf_action' => 'set_password',
                    'evf_token' => $token
                ), wc_get_page_permalink('myaccount'));
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EVF WooCommerce: Password setup URL: ' . $redirect_url);
                }
                
                wp_redirect($redirect_url);
                exit;
            } else {
                // Debug log - normal başarı sayfası
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EVF WooCommerce: No password change required, redirecting to success page');
                }
                
                // Normal başarı sayfasına yönlendir
                wp_redirect(add_query_arg('evf_success', 'verified', wc_get_page_permalink('myaccount')));
                exit;
            }
        } else {
            // Bu durumda user_id olmalı WooCommerce mode'da
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: No user ID found for token');
            }
            wp_redirect(add_query_arg('evf_error', 'invalid_state', wc_get_page_permalink('myaccount')));
            exit;
        }
    }
    
    /**
     * My Account menüsüne verification item ekle
     */
    public function add_verification_menu_item($menu_items) {
        // Sadece unverified kullanıcılar için göster
        if (!evf_is_user_verified()) {
            $menu_items['email-verification'] = __('E-posta Doğrulama', 'email-verification-forms');
        }
        
        return $menu_items;
    }
    
    /**
     * Verification endpoint içeriği
     */
    public function verification_endpoint_content() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return;
        }
        
        $is_verified = evf_is_user_verified($user_id);
        
        if ($is_verified) {
            // Zaten doğrulanmış
            echo '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info">';
            echo '<strong>✅ ' . __('E-posta adresiniz doğrulanmış!', 'email-verification-forms') . '</strong><br>';
            echo __('Hesabınızın tüm özellikleri aktif.', 'email-verification-forms');
            echo '</div>';
            return;
        }
        
        // Verification pending
        $user = wp_get_current_user();
        $last_sent = get_user_meta($user_id, 'evf_verification_sent_at', true);
        $can_resend = true;
        
        if ($last_sent) {
            $time_diff = current_time('timestamp') - $last_sent;
            if ($time_diff < (get_option('evf_rate_limit', 15) * 60)) {
                $can_resend = false;
                $wait_time = ceil(((get_option('evf_rate_limit', 15) * 60) - $time_diff) / 60);
            }
        }
        
        echo '<div class="evf-wc-verification-section">';
        
        // Status box
        echo '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info evf-verification-notice">';
        echo '<h3 style="margin-top: 0;">🛡️ ' . __('E-posta Doğrulaması Gerekli', 'email-verification-forms') . '</h3>';
        echo '<p>' . sprintf(__('Hesabınızın güvenliği için <strong>%s</strong> adresini doğrulamanız gerekmektedir.', 'email-verification-forms'), esc_html($user->user_email)) . '</p>';
        echo '</div>';
        
        // Resend section
        echo '<div class="evf-resend-section" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 4px;">';
        echo '<h4>' . __('Doğrulama E-postası', 'email-verification-forms') . '</h4>';
        
        if ($can_resend) {
            echo '<p>' . __('E-posta gelmedi mi? Yeni bir doğrulama e-postası gönderebilirsiniz.', 'email-verification-forms') . '</p>';
            echo '<button type="button" class="button alt evf-resend-verification" data-user-id="' . $user_id . '">';
            echo '📧 ' . __('Doğrulama E-postası Gönder', 'email-verification-forms');
            echo '</button>';
        } else {
            echo '<p style="color: #666;">' . sprintf(__('Yeni e-posta göndermek için %d dakika beklemeniz gerekiyor.', 'email-verification-forms'), $wait_time) . '</p>';
            echo '<button type="button" class="button" disabled>';
            echo '⏳ ' . sprintf(__('%d dakika bekleyin', 'email-verification-forms'), $wait_time);
            echo '</button>';
        }
        
        echo '</div>';
        
        // Help section
        echo '<div class="evf-help-section" style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
        echo '<h4 style="margin-top: 0;">💡 ' . __('E-posta gelmedi mi?', 'email-verification-forms') . '</h4>';
        echo '<ul style="margin: 10px 0 0 20px;">';
        echo '<li>' . __('Spam/Junk klasörünüzü kontrol edin', 'email-verification-forms') . '</li>';
        echo '<li>' . __('E-posta adresinizi doğru yazdığınızdan emin olun', 'email-verification-forms') . '</li>';
        echo '<li>' . __('Birkaç dakika bekleyin, e-posta gelmesi zaman alabilir', 'email-verification-forms') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div>';
        
        // JavaScript for AJAX
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.evf-resend-verification').on('click', function(e) {
                e.preventDefault();
                
                var $btn = $(this);
                var userId = $btn.data('user-id');
                
                $btn.prop('disabled', true).html('📤 Gönderiliyor...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'evf_wc_resend_verification',
                        user_id: userId,
                        nonce: '<?php echo wp_create_nonce('evf_wc_resend'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.html('✅ Gönderildi!');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $btn.html('❌ Hata oluştu').prop('disabled', false);
                        }
                    },
                    error: function() {
                        $btn.html('❌ Hata oluştu').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * My Account dashboard'da verification notice göster
     */
    public function show_verification_notice() {
        if (evf_is_user_verified()) {
            return;
        }
        
        $user = wp_get_current_user();
        
        echo '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info evf-verification-notice" style="border-left-color: #f39c12; background-color: #fef9e7;">';
        echo '<div style="display: flex; align-items: center; gap: 15px;">';
        echo '<div style="font-size: 24px;">🛡️</div>';
        echo '<div style="flex: 1;">';
        echo '<strong>' . __('E-posta Doğrulaması Gerekli', 'email-verification-forms') . '</strong><br>';
        echo sprintf(__('Hesabınızın güvenliği için %s adresini doğrulamanız gerekmektedir.', 'email-verification-forms'), '<strong>' . esc_html($user->user_email) . '</strong>');
        echo '</div>';
        echo '<div>';
        echo '<a href="' . esc_url(wc_get_account_endpoint_url('email-verification')) . '" class="button alt" style="white-space: nowrap;">';
        echo '📧 ' . __('Doğrula', 'email-verification-forms');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Kayıt formuna notice ekle
     */
    public function add_registration_notice() {
        echo '<div class="evf-registration-notice" style="margin: 15px 0; padding: 15px; background: #e8f4fd; border-left: 4px solid #2196f3; border-radius: 4px;">';
        echo '<p style="margin: 0; color: #1976d2; font-size: 14px;">';
        echo '<strong>🛡️ ' . __('Güvenlik Bildirimi:', 'email-verification-forms') . '</strong> ';
        echo __('Kayıt işlemi sonrasında e-posta adresinize bir doğrulama bağlantısı gönderilecektir.', 'email-verification-forms');
        echo '</p>';
        echo '</div>';
    }
    
    /**
     * Unverified kullanıcılar için hesap bölümlerine kısıtlama
     */
    public function restrict_account_sections() {
        if (evf_is_user_verified()) {
            return;
        }
        
        // Belirli endpoint'lerde restriction göster
        global $wp;
        $current_endpoint = isset($wp->query_vars) ? key($wp->query_vars) : '';
        
        $restricted_endpoints = array('edit-account', 'payment-methods', 'edit-address');
        
        if (in_array($current_endpoint, $restricted_endpoints)) {
            echo '<div class="woocommerce-message woocommerce-message--error woocommerce-Message woocommerce-Message--error">';
            echo '<strong>🔒 ' . __('E-posta Doğrulaması Gerekli', 'email-verification-forms') . '</strong><br>';
            echo __('Bu bölüme erişmek için önce e-posta adresinizi doğrulamanız gerekmektedir.', 'email-verification-forms') . ' ';
            echo '<a href="' . esc_url(wc_get_account_endpoint_url('email-verification')) . '">' . __('Şimdi Doğrula', 'email-verification-forms') . '</a>';
            echo '</div>';
            
            // İçeriği gizle
            echo '<style>.woocommerce-MyAccount-content > *:not(.woocommerce-message):not(.woocommerce-Message) { display: none !important; }</style>';
        }
    }
    
    /**
     * Account menüsünü filtrele
     */
    public function filter_account_menu($menu_items) {
        if (evf_is_user_verified()) {
            return $menu_items;
        }
        
        // Unverified kullanıcılar için menü itemlerini işaretle
        $restricted_items = array('edit-account', 'payment-methods', 'edit-address');
        
        foreach ($restricted_items as $item) {
            if (isset($menu_items[$item])) {
                $menu_items[$item] = $menu_items[$item] . ' 🔒';
            }
        }
        
        return $menu_items;
    }
    
    /**
     * Profil güncellemelerini kısıtla
     */
    public function restrict_profile_updates($errors) {
        if (!evf_is_user_verified()) {
            $errors->add('evf_verification_required', __('Profil bilgilerinizi güncellemek için önce e-posta doğrulaması yapmanız gerekmektedir.', 'email-verification-forms'));
        }
    }
    
    /**
     * Admin'de verification status field ekle
     */
    public function add_verification_status_field($user) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $is_verified = evf_is_user_verified($user->ID);
        $verification_sent = get_user_meta($user->ID, 'evf_verification_sent_at', true);
        ?>
        <h3><?php _e('Email Verification Status', 'email-verification-forms'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php _e('E-posta Doğrulandı', 'email-verification-forms'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="evf_email_verified" value="1" <?php checked($is_verified); ?> />
                        <?php _e('E-posta adresi doğrulanmış', 'email-verification-forms'); ?>
                    </label>
                    <br>
                    <?php if ($verification_sent): ?>
                        <small style="color: #666;">
                            <?php printf(__('Son doğrulama: %s', 'email-verification-forms'), date('d.m.Y H:i', $verification_sent)); ?>
                        </small>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Admin'de verification status field'ını kaydet
     */
    public function save_verification_status_field($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $verified = isset($_POST['evf_email_verified']) ? 1 : 0;
        update_user_meta($user_id, 'evf_email_verified', $verified);
        
        if ($verified) {
            update_user_meta($user_id, 'evf_verified_at', current_time('mysql'));
        }
    }
    
    /**
     * WooCommerce email'lerini geciktir (çakışma olmasın)
     */
    private function delay_woocommerce_emails($customer_id) {
        // WooCommerce'in customer email'ini 5 dakika geciktir
        wp_schedule_single_event(
            time() + (5 * 60), // 5 dakika sonra
            'evf_delayed_wc_customer_email',
            array($customer_id)
        );
    }
    
    /**
     * AJAX: Verification email'i yeniden gönder
     */
    public function ajax_resend_verification() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_wc_resend')) {
            wp_send_json_error('invalid_nonce');
        }
        
        $user_id = intval($_POST['user_id']);
        
        if (!$user_id || $user_id !== get_current_user_id()) {
            wp_send_json_error('invalid_user');
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error('user_not_found');
        }
        
        // Rate limiting check
        $last_sent = get_user_meta($user_id, 'evf_verification_sent_at', true);
        if ($last_sent) {
            $time_diff = current_time('timestamp') - $last_sent;
            if ($time_diff < (get_option('evf_rate_limit', 15) * 60)) {
                wp_send_json_error('rate_limit');
            }
        }
        
        // Yeni verification başlat
        $this->start_email_verification($user_id, $user->user_email, array(
            'context' => 'resend'
        ));
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Parola belirleme
     */
    public function ajax_set_password() {
        if (!wp_verify_nonce($_POST['evf_nonce'], 'evf_wc_password_nonce')) {
            wp_send_json_error(array('message' => 'Güvenlik kontrolü başarısız'));
        }
        
        $token = sanitize_text_field($_POST['evf_token']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(array('message' => 'Lütfen tüm alanları doldurun.'));
        }
        
        if ($new_password !== $confirm_password) {
            wp_send_json_error(array('message' => 'Parolalar eşleşmiyor.'));
        }
        
        // Password strength check
        if (!$this->is_password_strong($new_password)) {
            wp_send_json_error(array('message' => 'Parola çok zayıf. En az 8 karakter, büyük harf, küçük harf ve rakam içermelidir.'));
        }
        
        // Token'ı doğrula
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status = 'completed'",
            $token
        ));
        
        if (!$verification || !$verification->user_id) {
            wp_send_json_error(array('message' => 'Geçersiz token.'));
        }
        
        $user_id = $verification->user_id;
        
        // Parola değiştirme gerekli mi?
        $password_change_required = get_user_meta($user_id, 'evf_password_change_required', true);
        if (!$password_change_required) {
            wp_send_json_error(array('message' => 'Parola değiştirme gerekli değil.'));
        }
        
        // Parolayı güncelle
        wp_set_password($new_password, $user_id);
        
        // Meta'ları temizle
        update_user_meta($user_id, 'evf_password_change_required', 0);
        update_user_meta($user_id, 'evf_password_changed_at', current_time('mysql'));
        
        // Token'ı final olarak işaretle
        $wpdb->update(
            $table_name,
            array('status' => 'final_completed'),
            array('id' => $verification->id)
        );
        
        // Kullanıcıyı otomatik login yap
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Password set and user logged in: ' . $user_id);
        }
        
        wp_send_json_success(array(
            'redirect_url' => wc_get_page_permalink('myaccount')
        ));
    }
    
    /**
     * Password strength check
     */
    private function is_password_strong($password) {
        $min_length = get_option('evf_min_password_length', 8);
        
        if (strlen($password) < $min_length) {
            return false;
        }
        
        if (!get_option('evf_require_strong_password', true)) {
            return true;
        }
        
        // Strong password requirements
        $has_lower = preg_match('/[a-z]/', $password);
        $has_upper = preg_match('/[A-Z]/', $password);
        $has_number = preg_match('/[0-9]/', $password);
        
        return $has_lower && $has_upper && $has_number;
    }
    
    /**
     * Admin'e WooCommerce notification gönder
     */
    private function send_admin_notification($user_id, $email, $context) {
        $user = get_userdata($user_id);
        $admin_email = get_option('admin_email');
        
        $subject = sprintf(__('[%s] Yeni WooCommerce Müşteri Kaydı', 'email-verification-forms'), get_bloginfo('name'));
        
        $context_info = '';
        if (isset($context['context']) && $context['context'] === 'checkout') {
            $context_info = sprintf(__('Sipariş ID: %s<br>', 'email-verification-forms'), $context['order_id']);
        }
        
        $message = sprintf('
            <h2>Yeni WooCommerce Müşteri Kaydı</h2>
            <p><strong>%s</strong> mağazanıza yeni bir müşteri kaydoldu:</p>
            
            <table style="border-collapse: collapse; width: 100%%; margin: 20px 0;">
                <tr style="background: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Müşteri Adı:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%s</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>E-posta:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%s</td>
                </tr>
                <tr style="background: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Kayıt Tarihi:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%s</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Kullanıcı ID:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">#%d</td>
                </tr>
            </table>
            
            %s
            
            <p><strong>🛡️ E-posta doğrulama bağlantısı müşteriye gönderildi.</strong></p>
            
            <p><a href="%s" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Müşteri Profilini Görüntüle</a></p>',
            get_bloginfo('name'),
            esc_html($user->display_name ?: $user->user_login),
            esc_html($email),
            current_time('d.m.Y H:i'),
            $user_id,
            $context_info,
            admin_url('user-edit.php?user_id=' . $user_id)
        );
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * WooCommerce verification token'ını handle et
     */
    public function handle_wc_verification() {
        if (!isset($_GET['evf_action']) || $_GET['evf_action'] !== 'wc_verify') {
            return;
        }
        
        if (!isset($_GET['evf_token'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['evf_token']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';
        
        // Token'ı kontrol et
        $pending_verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status = 'wc_pending'",
            $token
        ));
        
        if (!$pending_verification) {
            wp_redirect(wc_get_account_endpoint_url('email-verification') . '?error=invalid_token');
            exit;
        }
        
        // Token süresini kontrol et
        if (strtotime($pending_verification->expires_at) < time()) {
            wp_redirect(wc_get_account_endpoint_url('email-verification') . '?error=expired_token');
            exit;
        }
        
        // Verification'ı tamamla
        $user_id = $pending_verification->user_id;
        
        // User meta güncelle
        update_user_meta($user_id, 'evf_email_verified', 1);
        update_user_meta($user_id, 'evf_verified_at', current_time('mysql'));
        
        // Pending table güncelle
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'email_verified_at' => current_time('mysql')
            ),
            array('id' => $pending_verification->id)
        );
        
        // Success sayfasına yönlendir
        wp_redirect(wc_get_account_endpoint_url('email-verification') . '?verified=1');
        exit;
    }
}