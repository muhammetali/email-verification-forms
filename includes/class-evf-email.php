<?php
/**
 * EVF Email Class
 * Email gönderim işlemleri sınıfı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Email {
    
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
        // Email ayarları
        add_filter('wp_mail_from', array($this, 'custom_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }
    
    /**
     * Doğrulama e-postası gönder
     */
    public function send_verification_email($email, $token) {
        $verification_url = home_url('/email-verification/verify/' . $token);
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        $subject = sprintf(__('%s - E-posta Adresinizi Doğrulayın', 'email-verification-forms'), $site_name);
        
        $template_data = array(
            'site_name' => $site_name,
            'site_url' => $site_url,
            'site_logo' => $this->get_site_logo(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'verification_url' => $verification_url,
            'email' => $email,
            'expiry_hours' => get_option('evf_token_expiry', 24)
        );
        
        $message = $this->get_email_template('verification', $template_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>'
        );
        
        return wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Hoş geldin e-postası gönder
     */
    public function send_welcome_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return false;
        
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $login_url = wp_login_url();
        
        $subject = sprintf(__('%s - Hoş Geldiniz!', 'email-verification-forms'), $site_name);
        
        $template_data = array(
            'site_name' => $site_name,
            'site_url' => $site_url,
            'site_logo' => $this->get_site_logo(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'user_name' => $user->display_name ?: $user->user_login,
            'user_email' => $user->user_email,
            'login_url' => $login_url,
            'user_id' => $user_id
        );
        
        $message = $this->get_email_template('welcome', $template_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>'
        );
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    /**
     * Admin bildirim e-postası gönder
     */
    public function send_admin_notification($user_id, $user_email) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $user = get_userdata($user_id);
        
        $subject = sprintf(__('%s - Yeni Kullanıcı Kaydı', 'email-verification-forms'), $site_name);
        
        $template_data = array(
            'site_name' => $site_name,
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'user_name' => $user->display_name ?: $user->user_login,
            'user_email' => $user_email,
            'user_id' => $user_id,
            'registration_date' => current_time('d.m.Y H:i'),
            'user_profile_url' => admin_url('user-edit.php?user_id=' . $user_id)
        );
        
        $message = $this->get_email_template('admin-notification', $template_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>'
        );
        
        return wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Email template'ini getir
     */
    private function get_email_template($template_name, $data = array()) {
    $template_file = EVF_TEMPLATES_PATH . 'emails/' . $template_name . '.php';
    
    if (file_exists($template_file)) {
        ob_start();
        extract($data);
        include $template_file;
        return ob_get_clean();
    }
    
    // Fallback: inline template
    return $this->get_inline_template($template_name, $data);
}
    
    /**
     * Inline email template'leri
     */
    private function get_inline_template($template_name, $data) {
        extract($data);
        
        switch ($template_name) {
            case 'verification':
                return $this->get_verification_template($data);
                
            case 'welcome':
                return $this->get_welcome_template($data);
                
            case 'admin-notification':
                return $this->get_admin_notification_template($data);
                
            default:
                return '';
        }
    }
    
    /**
     * Doğrulama email template'i
     */
    private function get_verification_template($data) {
        $logo_html = $data['site_logo'] ? '<img src="' . esc_url($data['site_logo']) . '" alt="' . esc_attr($data['site_name']) . '" style="max-height: 60px; margin-bottom: 20px;">' : '';
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . __('E-posta Doğrulama', 'email-verification-forms') . '</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8fafc;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, ' . esc_attr($data['primary_color']) . ', #6366f1); padding: 40px 20px; text-align: center;">
                    ' . $logo_html . '
                    <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 300;">
                        ' . __('E-posta Adresinizi Doğrulayın', 'email-verification-forms') . '
                    </h1>
                </div>
                
                <!-- Content -->
                <div style="padding: 40px 20px;">
                    <p style="font-size: 16px; line-height: 1.6; color: #374151; margin-bottom: 30px;">
                        ' . sprintf(__('Merhaba,<br><br>%s sitesine kayıt olduğunuz için teşekkür ederiz. Kayıt işleminizi tamamlamak için aşağıdaki butona tıklayarak e-posta adresinizi doğrulayın:', 'email-verification-forms'), '<strong>' . esc_html($data['site_name']) . '</strong>') . '
                    </p>
                    
                    <!-- CTA Button -->
                    <div style="text-align: center; margin: 40px 0;">
                        <a href="' . esc_url($data['verification_url']) . '" 
                           style="display: inline-block; padding: 16px 32px; background: linear-gradient(135deg, ' . esc_attr($data['primary_color']) . ', #6366f1); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                            ' . __('E-postamı Doğrula', 'email-verification-forms') . '
                        </a>
                    </div>
                    
                    <!-- Alternative Link -->
                    <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 30px 0;">
                        <p style="font-size: 14px; color: #6b7280; margin: 0 0 10px 0;">
                            ' . __('Butona tıklayamıyorsanız, aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:', 'email-verification-forms') . '
                        </p>
                        <p style="font-size: 12px; color: #9ca3af; word-break: break-all; margin: 0;">
                            ' . esc_url($data['verification_url']) . '
                        </p>
                    </div>
                    
                    <!-- Expiry Info -->
                    <div style="border-left: 4px solid #f59e0b; background-color: #fffbeb; padding: 16px; margin: 30px 0;">
                        <p style="font-size: 14px; color: #92400e; margin: 0;">
                            <strong>' . __('Önemli:', 'email-verification-forms') . '</strong> 
                            ' . sprintf(__('Bu bağlantı %d saat geçerlidir. Süre dolmadan önce doğrulama işlemini tamamlayın.', 'email-verification-forms'), $data['expiry_hours']) . '
                        </p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="background-color: #f8fafc; padding: 30px 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                    <p style="font-size: 14px; color: #6b7280; margin: 0 0 10px 0;">
                        ' . sprintf(__('Bu e-posta %s tarafından gönderilmiştir.', 'email-verification-forms'), esc_html($data['site_name'])) . '
                    </p>
                    <p style="font-size: 12px; color: #9ca3af; margin: 0;">
                        <a href="' . esc_url($data['site_url']) . '" style="color: ' . esc_attr($data['primary_color']) . '; text-decoration: none;">
                            ' . esc_html($data['site_url']) . '
                        </a>
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Hoş geldin email template'i
     */
    private function get_welcome_template($data) {
        $logo_html = $data['site_logo'] ? '<img src="' . esc_url($data['site_logo']) . '" alt="' . esc_attr($data['site_name']) . '" style="max-height: 60px; margin-bottom: 20px;">' : '';
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . __('Hoş Geldiniz', 'email-verification-forms') . '</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8fafc;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #059669, #10b981); padding: 40px 20px; text-align: center;">
                    ' . $logo_html . '
                    <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 300;">
                        ' . __('Hoş Geldiniz!', 'email-verification-forms') . '
                    </h1>
                </div>
                
                <!-- Content -->
                <div style="padding: 40px 20px;">
                    <p style="font-size: 18px; line-height: 1.6; color: #374151; margin-bottom: 20px;">
                        ' . sprintf(__('Merhaba %s,', 'email-verification-forms'), '<strong>' . esc_html($data['user_name']) . '</strong>') . '
                    </p>
                    
                    <p style="font-size: 16px; line-height: 1.6; color: #374151; margin-bottom: 30px;">
                        ' . sprintf(__('%s ailesine katıldığınız için teşekkür ederiz! Kayıt işleminiz başarıyla tamamlandı ve artık sitemizin tüm özelliklerini kullanabilirsiniz.', 'email-verification-forms'), '<strong>' . esc_html($data['site_name']) . '</strong>') . '
                    </p>
                    
                    <!-- User Info -->
                    <div style="background-color: #f3f4f6; padding: 24px; border-radius: 8px; margin: 30px 0;">
                        <h3 style="color: #374151; margin: 0 0 16px 0; font-size: 16px;">
                            ' . __('Hesap Bilgileriniz:', 'email-verification-forms') . '
                        </h3>
                        <p style="margin: 8px 0; font-size: 14px; color: #6b7280;">
                            <strong>' . __('E-posta:', 'email-verification-forms') . '</strong> ' . esc_html($data['user_email']) . '
                        </p>
                        <p style="margin: 8px 0; font-size: 14px; color: #6b7280;">
                            <strong>' . __('Kullanıcı Adı:', 'email-verification-forms') . '</strong> ' . esc_html($data['user_name']) . '
                        </p>
                    </div>
                    
                    <!-- CTA Button -->
                    <div style="text-align: center; margin: 40px 0;">
                        <a href="' . esc_url($data['login_url']) . '" 
                           style="display: inline-block; padding: 16px 32px; background: linear-gradient(135deg, ' . esc_attr($data['primary_color']) . ', #6366f1); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                            ' . __('Giriş Yap', 'email-verification-forms') . '
                        </a>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="background-color: #f8fafc; padding: 30px 20px; text-align: center; border-top: 1px solid #e5e7eb;">
                    <p style="font-size: 14px; color: #6b7280; margin: 0 0 10px 0;">
                        ' . sprintf(__('%s ekibi', 'email-verification-forms'), esc_html($data['site_name'])) . '
                    </p>
                    <p style="font-size: 12px; color: #9ca3af; margin: 0;">
                        <a href="' . esc_url($data['site_url']) . '" style="color: ' . esc_attr($data['primary_color']) . '; text-decoration: none;">
                            ' . esc_html($data['site_url']) . '
                        </a>
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Admin bildirim template'i
     */
    private function get_admin_notification_template($data) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . __('Yeni Kullanıcı Kaydı', 'email-verification-forms') . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <!-- Header -->
                <div style="background-color: #1f2937; padding: 20px; border-radius: 8px 8px 0 0;">
                    <h2 style="color: #ffffff; margin: 0; font-size: 20px;">
                        ' . __('Yeni Kullanıcı Kaydı', 'email-verification-forms') . '
                    </h2>
                </div>
                
                <!-- Content -->
                <div style="padding: 30px;">
                    <p style="font-size: 16px; color: #374151; margin-bottom: 20px;">
                        ' . sprintf(__('%s sitesine yeni bir kullanıcı kaydoldu:', 'email-verification-forms'), '<strong>' . esc_html($data['site_name']) . '</strong>') . '
                    </p>
                    
                    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                        <tr>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f9fafb; font-weight: bold; width: 30%;">
                                ' . __('Kullanıcı Adı:', 'email-verification-forms') . '
                            </td>
                            <td style="padding: 12px; border: 1px solid #e5e7eb;">
                                ' . esc_html($data['user_name']) . '
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f9fafb; font-weight: bold;">
                                ' . __('E-posta:', 'email-verification-forms') . '
                            </td>
                            <td style="padding: 12px; border: 1px solid #e5e7eb;">
                                ' . esc_html($data['user_email']) . '
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f9fafb; font-weight: bold;">
                                ' . __('Kayıt Tarihi:', 'email-verification-forms') . '
                            </td>
                            <td style="padding: 12px; border: 1px solid #e5e7eb;">
                                ' . esc_html($data['registration_date']) . '
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #e5e7eb; background-color: #f9fafb; font-weight: bold;">
                                ' . __('Kullanıcı ID:', 'email-verification-forms') . '
                            </td>
                            <td style="padding: 12px; border: 1px solid #e5e7eb;">
                                #' . esc_html($data['user_id']) . '
                            </td>
                        </tr>
                    </table>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="' . esc_url($data['user_profile_url']) . '" 
                           style="display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600;">
                            ' . __('Kullanıcı Profilini Görüntüle', 'email-verification-forms') . '
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Site logosunu getir
     */
    private function get_site_logo() {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_image = wp_get_attachment_image_src($custom_logo_id, 'full');
            return $logo_image[0];
        }
        return '';
    }
    
    /**
     * Gönderen e-posta adresi
     */
    public function custom_mail_from($email) {
        return get_option('evf_email_from_email', get_option('admin_email'));
    }
    
    /**
     * Gönderen adı
     */
    public function custom_mail_from_name($name) {
        return get_option('evf_email_from_name', get_bloginfo('name'));
    }
    
    /**
     * HTML content type
     */
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Gönderen e-posta adresini getir
     */
    private function get_from_email() {
        return get_option('evf_email_from_email', get_option('admin_email'));
    }
    
    /**
     * Gönderen adını getir
     */
    private function get_from_name() {
        return get_option('evf_email_from_name', get_bloginfo('name'));
    }
    
    /**
     * Email gönderim testleri
     */
    public function test_email($email, $template = 'verification') {
        $test_data = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'verification_url' => home_url('/email-verification/verify/test-token'),
            'email' => $email,
            'expiry_hours' => 24,
            'user_name' => 'Test User',
            'user_email' => $email,
            'login_url' => wp_login_url(),
            'user_id' => 999
        );
        
        switch ($template) {
            case 'verification':
                return $this->send_verification_email($email, 'test-token');
                
            case 'welcome':
                $subject = 'Test Hoş Geldin E-postası';
                $message = $this->get_welcome_template($test_data);
                break;
                
            case 'admin-notification':
                $subject = 'Test Admin Bildirimi';
                $message = $this->get_admin_notification_template($test_data);
                break;
                
            default:
                return false;
        }
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>'
        );
        
        return wp_mail($email, $subject, $message, $headers);
    }
}