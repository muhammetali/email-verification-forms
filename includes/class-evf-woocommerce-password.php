<?php
/**
 * EVF WooCommerce Password Handler Class
 * WooCommerce parola belirleme işlemleri
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_Password {

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
        // Password setup AJAX
        add_action('wp_ajax_evf_wc_set_password', array($this, 'ajax_set_password'));
        add_action('wp_ajax_nopriv_evf_wc_set_password', array($this, 'ajax_set_password'));
    }

    /**
     * Parola belirleme sayfasını handle et
     */
    public function handle_password_setup($token) {
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

        // Template göster
        $this->render_password_setup_template($token, $user);
    }

    /**
     * Parola belirleme template'ini render et
     */
    private function render_password_setup_template($token, $user) {
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
                        <?php
                        /* translators: %s: User display name or username (wrapped in <strong> tags) */
                        printf(__('Merhaba %s,<br>Hesabınızın güvenliği için lütfen yeni bir parola belirleyin.', 'email-verification-forms'), '<strong>' . esc_html($user->display_name ?: $user->user_login) . '</strong>');
                        ?>
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
                            <?php
                            /* translators: %d: Minimum password length from settings */
                            printf(__('En az %d karakter, büyük harf, küçük harf ve rakam içermelidir.', 'email-verification-forms'), get_option('evf_min_password_length', 8));
                            ?>
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
                        <?php
                        /* translators: %s: Site name */
                        printf(__('← %s Ana Sayfaya Dön', 'email-verification-forms'), esc_html(get_bloginfo('name')));
                        ?>
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
                                // Success message before redirect
                                btnLoading.innerHTML = '✅ Başarılı! Yönlendiriliyor...';
                                setTimeout(() => {
                                    window.location.href = data.data.redirect_url || '<?php echo wc_get_page_permalink('myaccount'); ?>';
                                }, 1500);
                            } else {
                                alert(data.data.message || 'Bir hata oluştu.');
                                btn.disabled = false;
                                btnText.style.display = 'inline-block';
                                btnLoading.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Password setup error:', error);
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
                        default:
                            return {width: '0%', color: '#dc3545', text: 'Geçersiz'};
                    }
                }

                function isPasswordStrong(password) {
                    const minLength = <?php echo get_option('evf_min_password_length', 8); ?>;
                    const requireStrong = <?php echo get_option('evf_require_strong_password', true) ? 'true' : 'false'; ?>;

                    if (password.length < minLength) {
                        return false;
                    }

                    if (!requireStrong) {
                        return true;
                    }

                    // Strong password requirements
                    const hasLower = /[a-z]/.test(password);
                    const hasUpper = /[A-Z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);

                    return hasLower && hasUpper && hasNumber;
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
}