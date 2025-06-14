<?php
/**
 * EVF WooCommerce Email Handler - Part 3/4
 * Email gönderme işlemleri
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_Email {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Email hooks can be added here if needed
    }

    /**
     * WooCommerce verification email'i gönder
     */
    public function send_verification_email($email, $token, $user_id, $context = array()) {
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
        $email_content = $this->get_verification_email_template($verification_url, $user_name, $email, $context);

        // WooCommerce email class'ını kullan
        $mailer = WC()->mailer();
        $wrapped_message = $mailer->wrap_message($subject, $email_content);

        $result = $mailer->send($email, $subject, $wrapped_message);

        // Email log'a kaydet
        if (class_exists('EVF_Database')) {
            EVF_Database::instance()->log_email($email, 'wc_verification', $result ? 'sent' : 'failed', null, $user_id);
        }

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Email sent result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }

        return $result;
    }

    /**
     * WooCommerce verification code email'i gönder
     */
    public function send_verification_code_email($email, $code, $user_id, $context = array()) {
        $user = get_userdata($user_id);
        $user_name = $user->display_name ?: $user->user_login;

        /* translators: %s: Site name */
        $subject = sprintf(__('[%s] E-posta Doğrulama Kodu', 'email-verification-forms'), get_bloginfo('name'));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Sending verification code email to ' . $email . ' with code: ' . $code);
        }

        // WooCommerce email template yapısını kullan
        $email_content = $this->get_verification_code_email_template($code, $user_name, $email, $context);

        // WooCommerce email class'ını kullan
        $mailer = WC()->mailer();
        $wrapped_message = $mailer->wrap_message($subject, $email_content);

        $result = $mailer->send($email, $subject, $wrapped_message);

        // Email log'a kaydet
        if (class_exists('EVF_Database')) {
            EVF_Database::instance()->log_email($email, 'wc_code_verification', $result ? 'sent' : 'failed', null, $user_id);
        }

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce: Code email sent result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }

        return $result;
    }

    /**
     * Admin'e WooCommerce notification gönder
     */
    public function send_admin_notification($user_id, $email, $context) {
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

    /**
     * WooCommerce verification email template'i
     */
    private function get_verification_email_template($verification_url, $user_name, $email, $context) {
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
     * WooCommerce kod email template'i
     */
    private function get_verification_code_email_template($code, $user_name, $email, $context) {
        $site_name = get_bloginfo('name');
        $brand_color = get_option('evf_brand_color', '#96588a');

        // Magic link oluştur - kod doğrulama sayfasına direkt gider
        $code_verification_url = add_query_arg(array(
            'evf_action' => 'wc_code_verify',
            'evf_email' => urlencode($email)
        ), wc_get_page_permalink('myaccount'));

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
                                    Aşağıdaki butona tıklayarak doğrulama sayfasına gidin ve size gönderilen kodu girin.
                                </p>
      
                                <!-- Magic Link Button -->
                                <div style="text-align: center; margin: 40px 0;">
                                    <a href="%s" 
                                       style="background: linear-gradient(135deg, %s, %s); 
                                              color: #ffffff; 
                                              padding: 15px 40px; 
                                              text-decoration: none; 
                                              border-radius: 6px; 
                                              font-weight: 600; 
                                              display: inline-block; 
                                              box-shadow: 0 4px 12px rgba(150,88,138,0.3);
                                              font-size: 16px;">
                                        🔓 Doğrulama Sayfasına Git
                                    </a>
                                </div>
                                
                                <!-- Verification Code Display -->
                                <div style="background: #f8f9fa; border: 2px solid %s; border-radius: 8px; padding: 25px; margin: 30px 0; text-align: center;">
                                    <h3 style="color: #333; margin: 0 0 15px 0; font-size: 16px;">
                                        📧 Doğrulama Kodunuz:
                                    </h3>
                                    <div style="font-size: 32px; font-weight: bold; color: %s; letter-spacing: 8px; font-family: monospace; background: white; padding: 15px; border-radius: 6px; border: 1px solid #e9ecef;">
                                        %s
                                    </div>
                                    <p style="margin: 15px 0 0 0; font-size: 14px; color: #666;">
                                        Bu kodu doğrulama sayfasında girin
                                    </p>
                                </div>
                                
                                <!-- Instructions -->
                                <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin: 30px 0;">
                                    <h4 style="color: #1976d2; margin: 0 0 10px 0; font-size: 16px;">
                                        📋 Doğrulama Adımları:
                                    </h4>
                                    <ol style="color: #424242; line-height: 1.6; margin: 0; padding-left: 20px;">
                                        <li>Yukarıdaki butona tıklayın</li>
                                        <li>Açılan sayfada <strong>%s</strong> kodunu girin</li>
                                        <li>"Doğrula" butonuna basın</li>
                                    </ol>
                                </div>
                                
                                <!-- Important Notice -->
                                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 20px 0;">
                                    <p style="margin: 0; font-size: 14px; color: #856404;">
                                        <strong>⚠️ Önemli:</strong> Bu kod 30 dakika geçerlidir. Süre dolmadan önce doğrulama işlemini tamamlayın.
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
            // Parametreler:
            esc_attr($brand_color), // 1. Header gradient 1
            esc_attr($brand_color), // 2. Header gradient 2
            esc_html($user_name), // 3. User name
            $context_message, // 4. Context message
            esc_html($site_name), // 5. Site name
            esc_url($code_verification_url), // 6. Magic Link URL
            esc_attr($brand_color), // 7. Button gradient 1
            esc_attr($brand_color), // 8. Button gradient 2
            esc_attr($brand_color), // 9. Code box border
            esc_attr($brand_color), // 10. Code color
            esc_html($code), // 11. Verification code
            esc_html($code), // 12. Code in instructions
            esc_html($site_name), // 13. Footer site name
            esc_url(home_url()), // 14. Footer home URL
            esc_attr($brand_color), // 15. Footer link color
            esc_html(home_url()) // 16. Footer home URL text
        );
    }
}