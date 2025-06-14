<?php
/**
 * EVF Registration Class
 * WordPress mode registration handling - Temiz versiyon
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Registration {

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
        // WordPress login page'i override et
        add_action('login_form_register', array($this, 'redirect_registration'));

        // Custom registration endpoints
        add_action('wp_loaded', array($this, 'add_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_registration_endpoints'));

        // AJAX handlers (sadece WordPress mode'da)
        add_action('wp_ajax_evf_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_nopriv_evf_register_user', array($this, 'ajax_register_user'));

        add_action('wp_ajax_evf_verify_email', array($this, 'ajax_verify_email'));
        add_action('wp_ajax_nopriv_evf_verify_email', array($this, 'ajax_verify_email'));

        add_action('wp_ajax_evf_set_password', array($this, 'ajax_set_password'));
        add_action('wp_ajax_nopriv_evf_set_password', array($this, 'ajax_set_password'));
    }

    /**
     * WordPress registration sayfasını yönlendir
     */
    public function redirect_registration() {
        wp_redirect(home_url('/email-verification/register/'));
        exit;
    }

    /**
     * Rewrite rules ekle
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^email-verification/register/?$',
            'index.php?evf_action=register',
            'top'
        );

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

        add_rewrite_tag('%evf_action%', '([^&]+)');
        add_rewrite_tag('%evf_token%', '([^&]+)');
    }

    /**
     * Registration endpoints'leri handle et
     */
    public function handle_registration_endpoints() {
        $action = get_query_var('evf_action');

        if (!$action) {
            return;
        }

        switch ($action) {
            case 'register':
                $this->show_registration_page();
                break;

            case 'verify':
                $token = get_query_var('evf_token');
                if ($token) {
                    $this->handle_email_verification(sanitize_text_field($token));
                }
                break;

            case 'set_password':
                $token = get_query_var('evf_token');
                if ($token) {
                    $this->handle_password_setup(sanitize_text_field($token));
                }
                break;
        }
    }

    /**
     * Registration sayfasını göster
     */
    private function show_registration_page() {
        $template_path = EVF_TEMPLATES_PATH . 'registration-form.php';

        if (file_exists($template_path)) {
            include $template_path;
            exit;
        }

        // Fallback: Basit registration formu
        $this->show_simple_registration_form();
    }

    /**
     * Basit registration formu
     */
    private function show_simple_registration_form() {
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html(get_bloginfo('name')); ?> - Kayıt Ol</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; margin: 0; }
                .container { max-width: 400px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; }
                .form-group { margin-bottom: 20px; }
                .form-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
                .btn { width: 100%; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; }
                .btn:hover { background: #2563eb; }
                .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; display: none; }
                .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
                .error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
            </style>
        </head>
        <body>
        <div class="container">
            <h1>Kayıt Ol</h1>
            <div id="message" class="message"></div>

            <form id="registration-form">
                <div class="form-group">
                    <input type="text" id="first_name" name="first_name" placeholder="Ad" class="form-input" required>
                </div>
                <div class="form-group">
                    <input type="text" id="last_name" name="last_name" placeholder="Soyad" class="form-input" required>
                </div>
                <div class="form-group">
                    <input type="email" id="email" name="email" placeholder="E-posta" class="form-input" required>
                </div>
                <button type="submit" class="btn">Kayıt Ol</button>
            </form>
        </div>

        <script>
            document.getElementById('registration-form').addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const data = new URLSearchParams();
                data.append('action', 'evf_register_user');
                data.append('nonce', '<?php echo wp_create_nonce('evf_nonce'); ?>');

                formData.forEach((value, key) => {
                    data.append(key, value);
                });

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: data
                })
                    .then(response => response.json())
                    .then(data => {
                        const message = document.getElementById('message');
                        message.style.display = 'block';

                        if (data.success) {
                            message.className = 'message success';
                            message.textContent = 'Doğrulama e-postası gönderildi! E-postanızı kontrol edin.';
                        } else {
                            message.className = 'message error';
                            message.textContent = data.data || 'Bir hata oluştu.';
                        }
                    });
            });
        </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * E-posta doğrulama handle et
     */
    private function handle_email_verification($token) {
        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration) {
            $this->show_error_page(__('Geçersiz doğrulama bağlantısı.', 'email-verification-forms'));
            return;
        }

        if ($registration->status === 'completed') {
            $this->show_success_page(__('E-posta zaten doğrulanmış.', 'email-verification-forms'));
            return;
        }

        // Token'ın süresi dolmuş mu kontrol et
        if (strtotime($registration->expires_at) < time()) {
            $this->show_error_page(__('Doğrulama bağlantısının süresi dolmuş.', 'email-verification-forms'));
            return;
        }

        // E-posta doğrulandı - status'u güncelle
        $database->mark_email_verified($registration->id);

        // Parola belirleme sayfasına yönlendir
        wp_redirect(home_url('/email-verification/set-password/' . $token));
        exit;
    }

    /**
     * Parola belirleme handle et
     */
    private function handle_password_setup($token) {
        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration || $registration->status !== 'email_verified') {
            $this->show_error_page(__('Geçersiz veya süresi dolmuş bağlantı.', 'email-verification-forms'));
            return;
        }

        $this->show_password_setup_page($token, $registration->email);
    }

    /**
     * Parola belirleme sayfası
     */
    private function show_password_setup_page($token, $email) {
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html(get_bloginfo('name')); ?> - Parola Belirle</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; margin: 0; }
                .container { max-width: 400px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; }
                .form-group { margin-bottom: 20px; }
                .form-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
                .btn { width: 100%; padding: 12px; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; }
                .btn:hover { background: #059669; }
                .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; display: none; }
                .success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
                .error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
                .email { color: #3b82f6; font-weight: bold; }
            </style>
        </head>
        <body>
        <div class="container">
            <h1>Parola Belirle</h1>
            <p>E-posta adresinizi doğruladınız: <span class="email"><?php echo esc_html($email); ?></span></p>
            <p>Şimdi hesabınız için bir parola belirleyin.</p>

            <div id="message" class="message"></div>

            <form id="password-form">
                <div class="form-group">
                    <input type="password" id="password" name="password" placeholder="Parola (min 8 karakter)" class="form-input" minlength="8" required>
                </div>
                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Parola Tekrar" class="form-input" required>
                </div>
                <button type="submit" class="btn">Hesabı Oluştur</button>
            </form>
        </div>

        <script>
            document.getElementById('password-form').addEventListener('submit', function(e) {
                e.preventDefault();

                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (password !== confirmPassword) {
                    showMessage('error', 'Parolalar eşleşmiyor.');
                    return;
                }

                if (password.length < 8) {
                    showMessage('error', 'Parola en az 8 karakter olmalıdır.');
                    return;
                }

                const data = new URLSearchParams();
                data.append('action', 'evf_set_password');
                data.append('nonce', '<?php echo wp_create_nonce('evf_nonce'); ?>');
                data.append('token', '<?php echo esc_js($token); ?>');
                data.append('password', password);

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: data
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage('success', 'Hesabınız oluşturuldu! Yönlendiriliyor...');
                            setTimeout(() => {
                                window.location.href = data.data.redirect_url || '/';
                            }, 2000);
                        } else {
                            showMessage('error', data.data || 'Bir hata oluştu.');
                        }
                    });
            });

            function showMessage(type, text) {
                const message = document.getElementById('message');
                message.style.display = 'block';
                message.className = 'message ' + type;
                message.textContent = text;
            }
        </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * AJAX: Kullanıcı kaydı
     */
    public function ajax_register_user() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);

        if (!is_email($email) || !$first_name || !$last_name) {
            wp_send_json_error('Lütfen tüm alanları doldurun.');
        }

        // E-posta var mı kontrol et
        if (email_exists($email)) {
            wp_send_json_error('Bu e-posta adresi zaten kayıtlı.');
        }

        $database = EVF_Database::instance();
        $email_handler = EVF_Email::instance();

        // Registration kaydı oluştur
        $registration_data = array(
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'verification_type' => 'link',
            'status' => 'pending',
            'token' => wp_generate_uuid4(),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+24 hours')),
            'created_at' => current_time('mysql')
        );

        $registration_id = $database->create_registration($registration_data);

        if (!$registration_id) {
            wp_send_json_error('Kayıt oluşturulamadı.');
        }

        // Doğrulama e-postası gönder
        $verification_url = home_url('/email-verification/verify/' . $registration_data['token']);
        $result = $email_handler->send_verification_email($email, $verification_url);

        if (!$result) {
            wp_send_json_error('E-posta gönderilemedi.');
        }

        wp_send_json_success('Doğrulama e-postası gönderildi!');
    }

    /**
     * AJAX: Parola belirleme
     */
    public function ajax_set_password() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_nonce')) {
            wp_send_json_error('invalid_nonce');
        }

        $token = sanitize_text_field($_POST['token']);
        $password = $_POST['password']; // Don't sanitize password

        if (!$token || !$password) {
            wp_send_json_error('Eksik bilgiler.');
        }

        $database = EVF_Database::instance();
        $registration = $database->get_registration_by_token($token);

        if (!$registration || $registration->status !== 'email_verified') {
            wp_send_json_error('Geçersiz token.');
        }

        // Parola güçlülük kontrolü
        if (strlen($password) < 8) {
            wp_send_json_error('Parola en az 8 karakter olmalıdır.');
        }

        // Kullanıcı oluştur
        $username = sanitize_user($registration->email);
        $user_id = wp_create_user($username, $password, $registration->email);

        if (is_wp_error($user_id)) {
            wp_send_json_error('Kullanıcı oluşturulamadı.');
        }

        // User meta'ları ekle
        update_user_meta($user_id, 'first_name', $registration->first_name);
        update_user_meta($user_id, 'last_name', $registration->last_name);
        update_user_meta($user_id, 'evf_email_verified', 1);

        // Registration'ı completed olarak işaretle
        $database->mark_registration_completed($registration->id, $user_id);

        // Welcome e-postası gönder
        $email_handler = EVF_Email::instance();
        $email_handler->send_welcome_email($user_id);

        // Kullanıcıyı otomatik login yap
        wp_set_auth_cookie($user_id);
        wp_set_current_user($user_id);

        wp_send_json_success(array(
            'redirect_url' => home_url()
        ));
    }

    /**
     * Hata sayfası göster
     */
    private function show_error_page($message) {
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title>Hata</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; margin: 0; text-align: center; }
                .container { max-width: 400px; margin: 50px auto; background: white; padding: 40px; border-radius: 8px; }
                .error-icon { font-size: 4rem; color: #ef4444; margin-bottom: 20px; }
                h1 { color: #ef4444; }
                .btn { display: inline-block; padding: 12px 24px; background: #3b82f6; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            </style>
        </head>
        <body>
        <div class="container">
            <div class="error-icon">❌</div>
            <h1>Hata</h1>
            <p><?php echo esc_html($message); ?></p>
            <a href="<?php echo home_url(); ?>" class="btn">Ana Sayfaya Dön</a>
        </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Başarı sayfası göster
     */
    private function show_success_page($message) {
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title>Başarılı</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; margin: 0; text-align: center; }
                .container { max-width: 400px; margin: 50px auto; background: white; padding: 40px; border-radius: 8px; }
                .success-icon { font-size: 4rem; color: #10b981; margin-bottom: 20px; }
                h1 { color: #10b981; }
                .btn { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            </style>
        </head>
        <body>
        <div class="container">
            <div class="success-icon">✅</div>
            <h1>Başarılı</h1>
            <p><?php echo esc_html($message); ?></p>
            <a href="<?php echo home_url(); ?>" class="btn">Ana Sayfaya Git</a>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}