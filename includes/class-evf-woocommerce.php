<?php
/**
 * EVF WooCommerce Integration Class
 * WooCommerce entegrasyon sınıfı - Ana işlevsellik
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
     * Alt sınıfları yükle
     */
    private function load_sub_classes() {
        // UI sınıfını yükle
        require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-ui.php';
        EVF_WooCommerce_UI::instance();

        // Password handler sınıfını yükle
        require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-password.php';
        EVF_WooCommerce_Password::instance();
    }

    /**
     * WooCommerce hook'larını başlat
     */
    public function init_hooks() {
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

        // Custom verification endpoint
        add_action('init', array($this, 'add_verification_endpoint'), 5);
        add_filter('woocommerce_account_menu_items', array($this, 'add_verification_menu_item'), 40);
        add_action('woocommerce_account_email-verification_endpoint', array($this, 'verification_endpoint_content'));

        // Verification token handling
        add_action('init', array($this, 'handle_verification_redirect'), 1);

        // Alt sınıfları yükle
        $this->load_sub_classes();
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
    public function start_email_verification($user_id, $email, $context = array()) {
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
            error_log('EVF WooCommerce: Inserting token with status: pending');
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'token' => $token,
                'status' => 'pending',
                'user_id' => $user_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
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

        /* translators: %s: Site name */
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
        $brand_color = get_option('evf_brand_color', '#96588a');

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
                    // Password handler sınıfına yönlendir
                    EVF_WooCommerce_Password::instance()->handle_password_setup($token);
                    break;
            }
        }
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

            // Parola değiştirme kontrolü
            $password_change_required = get_user_meta($user_id, 'evf_password_change_required', true);

            // Debug log - parola kontrolü
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Password change required check for user ' . $user_id . ': ' . ($password_change_required ? 'YES' : 'NO'));
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
     * Admin'e WooCommerce notification gönder
     */
    private function send_admin_notification($user_id, $email, $context) {
        $user = get_userdata($user_id);
        $admin_email = get_option('admin_email');

        /* translators: %s: Site name */
        $subject = sprintf(__('[%s] Yeni WooCommerce Müşteri Kaydı', 'email-verification-forms'), get_bloginfo('name'));

        $context_info = '';
        if (isset($context['context']) && $context['context'] === 'checkout') {
            /* translators: %s: Order ID */
            $context_info = sprintf(__('Sipariş ID: %s<br>', 'email-verification-forms'), $context['order_id']);
        }

        /* translators: 1: Site name, 2: User name, 3: Email, 4: Date, 5: User ID, 6: Context info, 7: Profile URL */
        $message = sprintf('
            <h2>Yeni WooCommerce Müşteri Kaydı</h2>
            <p><strong>%1$s</strong> mağazanıza yeni bir müşteri kaydoldu:</p>
            
            <table style="border-collapse: collapse; width: 100%%; margin: 20px 0;">
                <tr style="background: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Müşteri Adı:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%2$s</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>E-posta:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%3$s</td>
                </tr>
                <tr style="background: #f5f5f5;">
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Kayıt Tarihi:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">%4$s</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #ddd;"><strong>Kullanıcı ID:</strong></td>
                    <td style="padding: 10px; border: 1px solid #ddd;">#%5$d</td>
                </tr>
            </table>
            
            %6$s
            
            <p><strong>🛡️ E-posta doğrulama bağlantısı müşteriye gönderildi.</strong></p>
            
            <p><a href="%7$s" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Müşteri Profilini Görüntüle</a></p>',
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
}