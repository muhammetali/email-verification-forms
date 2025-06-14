<?php
/**
 * EVF Email Class
 * Email gönderim işlemleri sınıfı - Optimize edilmiş ve temizlenmiş versiyon
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Email {

    private static $instance = null;

    /**
     * Email template cache
     */
    private $template_cache = array();

    /**
     * Email common CSS styles
     */
    private $common_styles = array();

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->init_common_styles();
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Email ayarları
        add_filter('wp_mail_from', array($this, 'custom_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

        // Email template actions
        add_action('evf_before_send_email', array($this, 'before_send_email'));
        add_action('evf_after_send_email', array($this, 'after_send_email'));
    }

    /**
     * Ortak CSS stillerini hazırla (inline kullanım için)
     */
    private function init_common_styles() {
        $primary_color = get_option('evf_brand_color', '#3b82f6');

        $this->common_styles = array(
            'body' => 'margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8fafc; line-height: 1.6;',
            'container' => 'max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);',
            'header' => 'background: linear-gradient(135deg, ' . esc_attr($primary_color) . ', #6366f1); padding: 40px 20px; text-align: center;',
            'header_title' => 'color: #ffffff; margin: 0; font-size: 28px; font-weight: 300; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);',
            'content' => 'padding: 40px 20px;',
            'text' => 'font-size: 16px; line-height: 1.6; color: #374151; margin-bottom: 20px;',
            'button' => 'display: inline-block; padding: 16px 32px; background: linear-gradient(135deg, ' . esc_attr($primary_color) . ', #1d4ed8); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; text-align: center; box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.4); transition: all 0.2s ease;',
            'button_wrapper' => 'text-align: center; margin: 30px 0;',
            'code_wrapper' => 'text-align: center; margin: 40px 0;',
            'code_box' => 'background: #f8fafc; border: 3px dashed ' . esc_attr($primary_color) . '; border-radius: 12px; padding: 30px; display: inline-block; min-width: 200px;',
            'code_label' => 'font-size: 14px; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;',
            'code_value' => 'font-size: 36px; font-weight: bold; color: ' . esc_attr($primary_color) . '; letter-spacing: 6px; font-family: Monaco, Consolas, monospace;',
            'info_box' => 'background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 30px 0;',
            'warning_box' => 'border-left: 4px solid #f59e0b; background-color: #fffbeb; padding: 16px; margin: 30px 0;',
            'success_box' => 'border-left: 4px solid #10b981; background-color: #ecfdf5; padding: 16px; margin: 30px 0;',
            'footer' => 'background-color: #f8fafc; padding: 30px 20px; text-align: center; border-top: 1px solid #e5e7eb;',
            'footer_text' => 'font-size: 14px; color: #6b7280; margin: 0 0 10px 0;',
            'footer_link' => 'font-size: 12px; color: #9ca3af; margin: 0;'
        );
    }

    /**
     * Email gönderiminden önce
     */
    public function before_send_email($data) {
        // Email filtrelerini ekle
        add_filter('wp_mail_from', array($this, 'custom_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Email: Sending email to ' . $data['email'] . ' with template: ' . $data['template']);
        }
    }

    /**
     * Email gönderiminden sonra
     */
    public function after_send_email($result) {
        // Email filtrelerini kaldır
        remove_filter('wp_mail_from', array($this, 'custom_mail_from'));
        remove_filter('wp_mail_from_name', array($this, 'custom_mail_from_name'));
        remove_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF Email: Email sent result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }
    }

    /**
     * Doğrulama e-postası gönder
     */
    public function send_verification_email($email, $token) {
        $verification_url = home_url('/email-verification/verify/' . $token);
        $site_name = get_bloginfo('name');

        /* translators: %s: Site name */
        $subject = sprintf(__('%s - E-posta Adresinizi Doğrulayın', 'email-verification-forms'), $site_name);

        $template_data = array(
            'template' => 'verification',
            'email' => $email,
            'site_name' => $site_name,
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo_html(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'verification_url' => $verification_url,
            'expiry_hours' => get_option('evf_token_expiry', 24)
        );

        do_action('evf_before_send_email', $template_data);

        $message = $this->get_email_template('verification', $template_data);
        $headers = $this->get_email_headers();

        $result = wp_mail($email, $subject, $message, $headers);

        do_action('evf_after_send_email', $result);

        return $result;
    }

    /**
     * Doğrulama kodu e-postası gönder
     */
    public function send_verification_code_email($email, $code) {
        $site_name = get_bloginfo('name');

        /* translators: %s: Site name */
        $subject = sprintf(__('%s - E-posta Doğrulama Kodu', 'email-verification-forms'), $site_name);

        $template_data = array(
            'template' => 'verification_code',
            'email' => $email,
            'site_name' => $site_name,
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo_html(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'verification_code' => $code,
            'expiry_minutes' => get_option('evf_code_expiry_minutes', 30)
        );

        do_action('evf_before_send_email', $template_data);

        $message = $this->get_email_template('verification_code', $template_data);
        $headers = $this->get_email_headers();

        $result = wp_mail($email, $subject, $message, $headers);

        do_action('evf_after_send_email', $result);

        return $result;
    }

    /**
     * Hoş geldin e-postası gönder
     */
    public function send_welcome_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $site_name = get_bloginfo('name');
        $login_url = evf_is_woocommerce_active() ? wc_get_page_permalink('myaccount') : wp_login_url();

        /* translators: %s: Site name */
        $subject = sprintf(__('%s - Hoş Geldiniz!', 'email-verification-forms'), $site_name);

        $template_data = array(
            'template' => 'welcome',
            'email' => $user->user_email,
            'site_name' => $site_name,
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo_html(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'user_name' => $user->display_name ?: $user->user_login,
            'user_email' => $user->user_email,
            'login_url' => $login_url,
            'user_id' => $user_id
        );

        do_action('evf_before_send_email', $template_data);

        $message = $this->get_email_template('welcome', $template_data);
        $headers = $this->get_email_headers();

        $result = wp_mail($user->user_email, $subject, $message, $headers);

        do_action('evf_after_send_email', $result);

        return $result;
    }

    /**
     * Admin bildirim e-postası gönder
     */
    public function send_admin_notification($user_id, $user_email) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        $user = get_userdata($user_id);

        /* translators: %s: Site name */
        $subject = sprintf(__('%s - Yeni Kullanıcı Kaydı', 'email-verification-forms'), $site_name);

        $template_data = array(
            'template' => 'admin_notification',
            'email' => $admin_email,
            'site_name' => $site_name,
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo_html(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'user_name' => $user->display_name ?: $user->user_login,
            'user_email' => $user_email,
            'user_id' => $user_id,
            'registration_date' => current_time('d.m.Y H:i'),
            'user_profile_url' => admin_url('user-edit.php?user_id=' . $user_id)
        );

        do_action('evf_before_send_email', $template_data);

        $message = $this->get_email_template('admin_notification', $template_data);
        $headers = $this->get_email_headers();

        $result = wp_mail($admin_email, $subject, $message, $headers);

        do_action('evf_after_send_email', $result);

        return $result;
    }

    /**
     * Test e-postası gönder
     */
    public function send_test_email($email, $template = 'verification') {
        $test_data = array(
            'template' => $template,
            'email' => $email,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo_html(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'verification_url' => home_url('/email-verification/verify/test-token'),
            'verification_code' => '123456',
            'expiry_hours' => 24,
            'expiry_minutes' => 30,
            'user_name' => 'Test User',
            'user_email' => $email,
            'login_url' => wp_login_url(),
            'user_id' => 999,
            'registration_date' => current_time('d.m.Y H:i'),
            'user_profile_url' => admin_url('user-edit.php?user_id=999')
        );

        $subject = sprintf(__('Test E-postası - %s', 'email-verification-forms'), ucfirst($template));

        do_action('evf_before_send_email', $test_data);

        $message = $this->get_email_template($template, $test_data);
        $headers = $this->get_email_headers();

        $result = wp_mail($email, $subject, $message, $headers);

        do_action('evf_after_send_email', $result);

        return $result;
    }

    /**
     * Email template'ini getir
     */
    private function get_email_template($template_name, $data = array()) {
        // Cache kontrolü
        $cache_key = md5($template_name . serialize($data));
        if (isset($this->template_cache[$cache_key])) {
            return $this->template_cache[$cache_key];
        }

        // External template dosyası kontrolü
        $template_file = EVF_TEMPLATES_PATH . 'emails/' . $template_name . '.php';

        if (file_exists($template_file)) {
            ob_start();
            extract($data);
            include $template_file;
            $content = ob_get_clean();
        } else {
            // Fallback: inline template
            $content = $this->get_inline_template($template_name, $data);
        }

        // Template'i cache'le
        $this->template_cache[$cache_key] = $content;

        return $content;
    }

    /**
     * Inline email template'leri (optimized)
     */
    private function get_inline_template($template_name, $data) {
        switch ($template_name) {
            case 'verification':
                return $this->get_verification_template($data);

            case 'verification_code':
                return $this->get_verification_code_template($data);

            case 'welcome':
                return $this->get_welcome_template($data);

            case 'admin_notification':
                return $this->get_admin_notification_template($data);

            default:
                return $this->get_default_template($data);
        }
    }

    /**
     * Doğrulama email template'i (optimize edilmiş)
     */
    private function get_verification_template($data) {
        /* translators: %s: Site name (wrapped in <strong> tags) */
        $welcome_text = sprintf(__('Merhaba,<br><br>%s sitesine kayıt olduğunuz için teşekkür ederiz. Kayıt işleminizi tamamlamak için aşağıdaki butona tıklayarak e-posta adresinizi doğrulayın:', 'email-verification-forms'), '<strong>' . esc_html($data['site_name']) . '</strong>');

        /* translators: %d: Number of hours for link expiry */
        $expiry_text = sprintf(__('Bu bağlantı %d saat geçerlidir. Süre dolmadan önce doğrulama işlemini tamamlayın.', 'email-verification-forms'), $data['expiry_hours']);

        /* translators: %s: Site name */
        $footer_text = sprintf(__('Bu e-posta %s tarafından gönderilmiştir.', 'email-verification-forms'), esc_html($data['site_name']));

        return $this->wrap_email_template(
            __('E-posta Doğrulama', 'email-verification-forms'),
            '
            <p style="' . $this->common_styles['text'] . '">
                ' . $welcome_text . '
            </p>
            
            <div style="' . $this->common_styles['button_wrapper'] . '">
                <a href="' . esc_url($data['verification_url']) . '" style="' . $this->common_styles['button'] . '">
                    ✅ ' . __('E-postamı Doğrula', 'email-verification-forms') . '
                </a>
            </div>
            
            <div style="' . $this->common_styles['info_box'] . '">
                <h3 style="color: #374151; margin: 0 0 12px 0; font-size: 16px;">
                    💡 ' . __('Alternatif Yöntem:', 'email-verification-forms') . '
                </h3>
                <p style="font-size: 14px; color: #6b7280; margin: 0; line-height: 1.5;">
                    ' . __('Butona tıklayamıyorsanız, aşağıdaki bağlantıyı kopyalayıp tarayıcınıza yapıştırın:', 'email-verification-forms') . '<br>
                    <span style="word-break: break-all; color: #3b82f6;">' . esc_url($data['verification_url']) . '</span>
                </p>
            </div>
            
            <div style="' . $this->common_styles['warning_box'] . '">
                <p style="font-size: 14px; color: #92400e; margin: 0;">
                    <strong>' . __('Önemli:', 'email-verification-forms') . '</strong> 
                    ' . $expiry_text . '
                </p>
            </div>
            ',
            $data,
            $footer_text
        );
    }

    /**
     * Kod doğrulama email template'i (optimize edilmiş)
     */
    private function get_verification_code_template($data) {
        /* translators: %s: Site name (wrapped in <strong> tags) */
        $welcome_text = sprintf(__('Merhaba,<br><br>%s sitesine kayıt olduğunuz için teşekkür ederiz. Kayıt işleminizi tamamlamak için aşağıdaki 6 haneli doğrulama kodunu kullanın:', 'email-verification-forms'), '<strong>' . esc_html($data['site_name']) . '</strong>');

        /* translators: %d: Number of minutes for code expiry */
        $expiry_text = sprintf(__('Bu kod %d dakika geçerlidir. Süre dolmadan önce doğrulama işlemini tamamlayın.', 'email-verification-forms'), $data['expiry_minutes']);

        /* translators: %s: Site name */
        $footer_text = sprintf(__('Bu e-posta %s tarafından gönderilmiştir.', 'email-verification-forms'), esc_html($data['site_name']));

        return $this->wrap_email_template(
            __('E-posta Doğrulama Kodu', 'email-verification-forms'),
            '
            <p style="' . $this->common_styles['text'] . '">
                ' . $welcome_text . '
            </p>
            
            <div style="' . $this->common_styles['code_wrapper'] . '">
                <div style="' . $this->common_styles['code_box'] . '">
                    <div style="' . $this->common_styles['code_label'] . '">
                        ' . __('Doğrulama Kodu', 'email-verification-forms') . '
                    </div>
                    <div style="' . $this->common_styles['code_value'] . '">
                        ' . esc_html($data['verification_code']) . '
                    </div>
                </div>
            </div>
            
            <div style="' . $this->common_styles['info_box'] . '">
                <h3 style="color: #374151; margin: 0 0 12px 0; font-size: 16px;">
                    📝 ' . __('Nasıl kullanılır:', 'email-verification-forms') . '
                </h3>
                <ol style="margin: 0; padding-left: 20px; color: #6b7280; line-height: 1.6;">
                    <li style="margin-bottom: 8px;">' . __('Kayıt sayfasına geri dönün', 'email-verification-forms') . '</li>
                    <li style="margin-bottom: 8px;">' . __('Yukarıdaki 6 haneli kodu girin', 'email-verification-forms') . '</li>
                    <li style="margin-bottom: 0;">' . __('Doğrula butonuna tıklayın', 'email-verification-forms') . '</li>
                </ol>
            </div>
            
            <div style="' . $this->common_styles['warning_box'] . '">
                <p style="font-size: 14px; color: #92400e; margin: 0;">
                    <strong>' . __('Önemli:', 'email-verification-forms') . '</strong> 
                    ' . $expiry_text . '
                </p>
            </div>
            
            <div style="background-color: #fef2f2; border: 1px solid #fecaca; padding: 16px; border-radius: 8px; margin: 20px 0;">
                <p style="font-size: 14px; color: #991b1b; margin: 0;">
                    <strong>🔒 ' . __('Güvenlik:', 'email-verification-forms') . '</strong> 
                    ' . __('Bu kodu kimseyle paylaşmayın. Sadece sizin kullanımınız için gönderilmiştir.', 'email-verification-forms') . '
                </p>
            </div>
            ',
            $data,
            $footer_text
        );
    }

    /**
     * Hoş geldin email template'i (optimize edilmiş)
     */
    private function get_welcome_template($data) {
        /* translators: %s: User name (wrapped in <strong> tags) */
        $greeting_text = sprintf(__('Merhaba %s,', 'email-verification-forms'), '<strong>' . esc_html($data['user_name']) . '</strong>');

        /* translators: %s: Site name (wrapped in <strong> tags) */
        $welcome_message = sprintf(__('%s ailesine katıldığınız için teşekkür ederiz! Kayıt işleminiz başarıyla tamamlandı ve artık sitemizin tüm özelliklerini kullanabilirsiniz.', 'email-verification-forms'), '<strong>' . esc_html($data['site_name']) . '</strong>');

        /* translators: %s: Site name */
        $team_signature = sprintf(__('%s ekibi', 'email-verification-forms'), esc_html($data['site_name']));

        /* translators: %s: Site name */
        $footer_text = sprintf(__('Bu e-posta %s tarafından gönderilmiştir.', 'email-verification-forms'), esc_html($data['site_name']));

        return $this->wrap_email_template(
            __('Hoş Geldiniz!', 'email-verification-forms'),
            '
            <p style="' . $this->common_styles['text'] . '">
                ' . $greeting_text . '
            </p>
            
            <p style="' . $this->common_styles['text'] . '">
                ' . $welcome_message . '
            </p>
            
            <div style="' . $this->common_styles['success_box'] . '">
                <p style="font-size: 16px; color: #065f46; margin: 0; font-weight: 600;">
                    🎉 ' . __('Hesabınız aktif ve kullanıma hazır!', 'email-verification-forms') . '
                </p>
            </div>
            
            <div style="' . $this->common_styles['button_wrapper'] . '">
                <a href="' . esc_url($data['login_url']) . '" style="' . $this->common_styles['button'] . '">
                    🚀 ' . __('Hesabıma Giriş Yap', 'email-verification-forms') . '
                </a>
            </div>
            
            <div style="' . $this->common_styles['info_box'] . '">
                <h3 style="color: #374151; margin: 0 0 12px 0; font-size: 16px;">
                    📋 ' . __('Hesap Bilgileriniz:', 'email-verification-forms') . '
                </h3>
                <table style="width: 100%; font-size: 14px; color: #6b7280;">
                    <tr>
                        <td style="padding: 4px 0; font-weight: 600;">' . __('Kullanıcı Adı:', 'email-verification-forms') . '</td>
                        <td style="padding: 4px 0;">' . esc_html($data['user_name']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; font-weight: 600;">' . __('E-posta:', 'email-verification-forms') . '</td>
                        <td style="padding: 4px 0;">' . esc_html($data['user_email']) . '</td>
                    </tr>
                </table>
            </div>
            
            <p style="' . $this->common_styles['text'] . '">
                ' . __('Sorularınız için bizimle iletişime geçmekten çekinmeyin.', 'email-verification-forms') . '<br><br>
                ' . __('Saygılarımızla,', 'email-verification-forms') . '<br>
                <strong>' . $team_signature . '</strong>
            </p>
            ',
            $data,
            $footer_text,
            'welcome'
        );
    }

    /**
     * Admin bildirim email template'i (optimize edilmiş)
     */
    private function get_admin_notification_template($data) {
        /* translators: %s: User email */
        $notification_text = sprintf(__('Yeni bir kullanıcı kaydoldu: %s', 'email-verification-forms'), '<strong>' . esc_html($data['user_email']) . '</strong>');

        /* translators: %s: Site name */
        $footer_text = sprintf(__('Bu bildirim %s tarafından otomatik olarak gönderilmiştir.', 'email-verification-forms'), esc_html($data['site_name']));

        return $this->wrap_email_template(
            __('Yeni Kullanıcı Kaydı', 'email-verification-forms'),
            '
            <p style="' . $this->common_styles['text'] . '">
                ' . __('Merhaba,', 'email-verification-forms') . '
            </p>
            
            <p style="' . $this->common_styles['text'] . '">
                ' . $notification_text . '
            </p>
            
            <div style="' . $this->common_styles['info_box'] . '">
                <h3 style="color: #374151; margin: 0 0 15px 0; font-size: 16px;">
                    👤 ' . __('Kullanıcı Detayları:', 'email-verification-forms') . '
                </h3>
                <table style="width: 100%; font-size: 14px; color: #6b7280; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; border-bottom: 1px solid #e5e7eb;">' . __('Kullanıcı Adı:', 'email-verification-forms') . '</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">' . esc_html($data['user_name']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; border-bottom: 1px solid #e5e7eb;">' . __('E-posta:', 'email-verification-forms') . '</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">' . esc_html($data['user_email']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; border-bottom: 1px solid #e5e7eb;">' . __('Kayıt Tarihi:', 'email-verification-forms') . '</td>
                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">' . esc_html($data['registration_date']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600;">' . __('Kullanıcı ID:', 'email-verification-forms') . '</td>
                        <td style="padding: 8px 0;">' . esc_html($data['user_id']) . '</td>
                    </tr>
                </table>
            </div>
            
            <div style="' . $this->common_styles['button_wrapper'] . '">
                <a href="' . esc_url($data['user_profile_url']) . '" style="' . $this->common_styles['button'] . '">
                    👁️ ' . __('Kullanıcı Profilini Görüntüle', 'email-verification-forms') . '
                </a>
            </div>
            ',
            $data,
            $footer_text,
            'admin'
        );
    }

    /**
     * Default email template'i
     */
    private function get_default_template($data) {
        return $this->wrap_email_template(
            __('Bildirim', 'email-verification-forms'),
            '<p style="' . $this->common_styles['text'] . '">' . __('Bu bir test e-postasıdır.', 'email-verification-forms') . '</p>',
            $data,
            sprintf(__('Bu e-posta %s tarafından gönderilmiştir.', 'email-verification-forms'), esc_html($data['site_name']))
        );
    }

    /**
     * Email template wrapper (DRY principle)
     */
    private function wrap_email_template($title, $content, $data, $footer_text, $template_type = 'default') {
        $header_gradient = $template_type === 'welcome' ?
            'background: linear-gradient(135deg, #059669, #10b981);' :
            $this->common_styles['header'];

        return '
        <!DOCTYPE html>
        <html lang="' . get_locale() . '">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="x-apple-disable-message-reformatting">
            <title>' . esc_html($title) . '</title>
            <!--[if mso]>
            <noscript>
                <xml>
                    <o:OfficeDocumentSettings>
                        <o:PixelsPerInch>96</o:PixelsPerInch>
                    </o:OfficeDocumentSettings>
                </xml>
            </noscript>
            <![endif]-->
        </head>
        <body style="' . $this->common_styles['body'] . '">
            <div style="' . $this->common_styles['container'] . '">
                <!-- Header -->
                <div style="' . $header_gradient . '">
                    ' . $data['site_logo'] . '
                    <h1 style="' . $this->common_styles['header_title'] . '">
                        ' . esc_html($title) . '
                    </h1>
                </div>
                
                <!-- Content -->
                <div style="' . $this->common_styles['content'] . '">
                    ' . $content . '
                </div>
                
                <!-- Footer -->
                <div style="' . $this->common_styles['footer'] . '">
                    <p style="' . $this->common_styles['footer_text'] . '">
                        ' . $footer_text . '
                    </p>
                    <p style="' . $this->common_styles['footer_link'] . '">
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
     * Site logosunu HTML formatında getir (WordPress standartlarına uygun)
     */
    private function get_site_logo_html() {
        static $logo_html = null;

        if ($logo_html === null) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_image = wp_get_attachment_image(
                    $custom_logo_id,
                    'medium',
                    false,
                    array(
                        'style' => 'max-height: 60px; margin-bottom: 20px; display: block;',
                        'alt' => get_bloginfo('name')
                    )
                );
                $logo_html = $logo_image;
            } else {
                // Fallback: Site name
                $logo_html = '<div style="color: #ffffff; font-size: 20px; font-weight: 600; margin-bottom: 10px;">' .
                    esc_html(get_bloginfo('name')) . '</div>';
            }
        }

        return $logo_html;
    }

    /**
     * Email headers array'ini getir
     */
    private function get_email_headers() {
        return array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>',
            'Reply-To: ' . $this->get_from_name() . ' <' . $this->get_from_email() . '>'
        );
    }

    /**
     * Gönderen e-posta adresi
     */
    public function custom_mail_from($email) {
        return $this->get_from_email();
    }

    /**
     * Gönderen adı
     */
    public function custom_mail_from_name($name) {
        return $this->get_from_name();
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
     * Template cache'i temizle
     */
    public function clear_template_cache() {
        $this->template_cache = array();
    }

    /**
     * Email preview için (admin area)
     */
    public function get_email_preview($template_name, $data = array()) {
        // Ensure we have all required data
        $default_data = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'site_logo' => $this->get_site_logo_html(),
            'primary_color' => get_option('evf_brand_color', '#3b82f6'),
            'email' => 'example@example.com',
            'user_name' => 'Örnek Kullanıcı',
            'verification_url' => home_url('/email-verification/verify/preview-token'),
            'verification_code' => '123456',
            'expiry_hours' => 24,
            'expiry_minutes' => 30
        );

        $data = array_merge($default_data, $data);

        return $this->get_email_template($template_name, $data);
    }
}