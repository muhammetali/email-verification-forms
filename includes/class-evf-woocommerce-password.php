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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
        // Template değişkenleri
        $min_password_length = (int) get_option('evf_min_password_length', 8);
        $require_strong_password = (bool) get_option('evf_require_strong_password', true);
        $site_name = get_bloginfo('name');
        $home_url = home_url();
        $admin_ajax_url = admin_url('admin-ajax.php');
        $myaccount_url = wc_get_page_permalink('myaccount');
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($site_name); ?> - <?php esc_html_e('Parola Belirleme', 'email-verification-forms'); ?></title>
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
                        <span><?php esc_html_e('E-posta', 'email-verification-forms'); ?></span>
                    </div>
                    <div class="evf-step completed">
                        <div class="evf-step-circle">✓</div>
                        <span><?php esc_html_e('Doğrulama', 'email-verification-forms'); ?></span>
                    </div>
                    <div class="evf-step active">
                        <div class="evf-step-circle">3</div>
                        <span><?php esc_html_e('Parola', 'email-verification-forms'); ?></span>
                    </div>
                </div>

                <!-- Header -->
                <div class="evf-setup-header" style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #333; margin-bottom: 10px;">🔐 <?php esc_html_e('Parolanızı Belirleyin', 'email-verification-forms'); ?></h1>
                    <p style="color: #666; font-size: 16px;">
                        <?php
                        /* translators: %s: User display name or username (wrapped in <strong> tags) */
                        printf(esc_html__('Merhaba %s,<br>Hesabınızın güvenliği için lütfen yeni bir parola belirleyin.', 'email-verification-forms'), '<strong>' . esc_html($user->display_name ?: $user->user_login) . '</strong>');
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
                            <?php esc_html_e('Yeni Parola', 'email-verification-forms'); ?> <span style="color: red;">*</span>
                        </label>
                        <input type="password" id="evf_new_password" name="new_password"
                               class="input-text" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;"
                               required minlength="<?php echo esc_attr($min_password_length); ?>">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <?php
                            /* translators: %d: Minimum password length from settings */
                            printf(esc_html__('En az %d karakter, büyük harf, küçük harf ve rakam içermelidir.', 'email-verification-forms'), esc_html($min_password_length));
                            ?>
                        </small>
                    </div>

                    <div class="evf-form-row" style="margin-bottom: 25px;">
                        <label for="evf_confirm_password" style="display: block; font-weight: 600; margin-bottom: 8px;">
                            <?php esc_html_e('Parola Tekrar', 'email-verification-forms'); ?> <span style="color: red;">*</span>
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
                            <span class="evf-btn-text">🚀 <?php esc_html_e('Hesabımı Aktifleştir', 'email-verification-forms'); ?></span>
                            <span class="evf-btn-loading" style="display: none;">
                                <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px;"></span>
                                <?php esc_html_e('Parola kaydediliyor...', 'email-verification-forms'); ?>
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Security Info -->
                <div class="evf-security-notice" style="background: #e8f4fd; border-left: 4px solid #2196f3; padding: 15px; margin-top: 20px; border-radius: 4px;">
                    <p style="margin: 0; color: #1976d2; font-size: 14px;">
                        <strong>🛡️ <?php esc_html_e('Güvenlik:', 'email-verification-forms'); ?></strong> <?php esc_html_e('Parolanız şifrelenerek saklanır ve hiçbir zaman görüntülenemez.', 'email-verification-forms'); ?>
                    </p>
                </div>

                <!-- Back to Site Link -->
                <div style="text-align: center; margin-top: 30px;">
                    <a href="<?php echo esc_url($home_url); ?>" style="color: #666; text-decoration: none; font-size: 14px;">
                        <?php
                        /* translators: %s: Site name */
                        printf(esc_html__('← %s Ana Sayfaya Dön', 'email-verification-forms'), esc_html($site_name));
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

            /* Responsive design */
            @media (max-width: 640px) {
                .evf-wc-password-setup {
                    margin: 20px auto;
                    padding: 20px 15px;
                }

                .evf-progress-steps {
                    gap: 15px;
                }

                .evf-step-circle {
                    width: 35px;
                    height: 35px;
                    font-size: 14px;
                }

                .evf-step span {
                    font-size: 12px;
                }
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Configuration from PHP
                const config = {
                    minPasswordLength: <?php echo wp_json_encode($min_password_length); ?>,
                    requireStrongPassword: <?php echo wp_json_encode($require_strong_password); ?>,
                    ajaxUrl: <?php echo wp_json_encode($admin_ajax_url); ?>,
                    redirectUrl: <?php echo wp_json_encode($myaccount_url); ?>,
                    messages: {
                        fillAllFields: <?php echo wp_json_encode(__('Lütfen tüm alanları doldurun.', 'email-verification-forms')); ?>,
                        passwordsNotMatch: <?php echo wp_json_encode(__('Parolalar eşleşmiyor.', 'email-verification-forms')); ?>,
                        passwordWeak: <?php echo wp_json_encode(__('Parola çok zayıf. Lütfen güçlü bir parola seçin.', 'email-verification-forms')); ?>,
                        error: <?php echo wp_json_encode(__('Bir hata oluştu. Lütfen tekrar deneyen.', 'email-verification-forms')); ?>,
                        sending: <?php echo wp_json_encode(__('📤 Gönderiliyor...', 'email-verification-forms')); ?>,
                        success: <?php echo wp_json_encode(__('✅ Başarılı! Yönlendiriliyor...', 'email-verification-forms')); ?>,
                        strengthLevels: {
                            veryWeak: <?php echo wp_json_encode(__('Çok Zayıf', 'email-verification-forms')); ?>,
                            weak: <?php echo wp_json_encode(__('Zayıf', 'email-verification-forms')); ?>,
                            medium: <?php echo wp_json_encode(__('Orta', 'email-verification-forms')); ?>,
                            strong: <?php echo wp_json_encode(__('Güçlü', 'email-verification-forms')); ?>,
                            veryStrong: <?php echo wp_json_encode(__('Çok Güçlü', 'email-verification-forms')); ?>,
                            invalid: <?php echo wp_json_encode(__('Geçersiz', 'email-verification-forms')); ?>
                        }
                    }
                };

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
                        alert(config.messages.fillAllFields);
                        return;
                    }

                    if (password !== confirm) {
                        alert(config.messages.passwordsNotMatch);
                        return;
                    }

                    if (!isPasswordStrong(password)) {
                        alert(config.messages.passwordWeak);
                        return;
                    }

                    // Set loading state
                    btn.disabled = true;
                    btnText.style.display = 'none';
                    btnLoading.style.display = 'inline-block';

                    // AJAX request
                    const formData = new FormData(form);

                    fetch(config.ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Success message before redirect
                                btnLoading.innerHTML = config.messages.success;
                                setTimeout(() => {
                                    window.location.href = data.data.redirect_url || config.redirectUrl;
                                }, 1500);
                            } else {
                                alert(data.data.message || config.messages.error);
                                btn.disabled = false;
                                btnText.style.display = 'inline-block';
                                btnLoading.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Password setup error:', error);
                            alert(config.messages.error);
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
                            return {width: '25%', color: '#dc3545', text: config.messages.strengthLevels.veryWeak};
                        case 2:
                            return {width: '50%', color: '#fd7e14', text: config.messages.strengthLevels.weak};
                        case 3:
                            return {width: '75%', color: '#ffc107', text: config.messages.strengthLevels.medium};
                        case 4:
                            return {width: '100%', color: '#28a745', text: config.messages.strengthLevels.strong};
                        case 5:
                            return {width: '100%', color: '#20c997', text: config.messages.strengthLevels.veryStrong};
                        default:
                            return {width: '0%', color: '#dc3545', text: config.messages.strengthLevels.invalid};
                    }
                }

                function isPasswordStrong(password) {
                    if (password.length < config.minPasswordLength) {
                        return false;
                    }

                    if (!config.requireStrongPassword) {
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
        // Nonce verification
        if (!isset($_POST['evf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['evf_nonce'])), 'evf_wc_password_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Güvenlik kontrolü başarısız', 'email-verification-forms')));
        }

        // Validate and sanitize inputs
        if (!isset($_POST['evf_token']) || !isset($_POST['new_password']) || !isset($_POST['confirm_password'])) {
            wp_send_json_error(array('message' => esc_html__('Eksik form verileri.', 'email-verification-forms')));
        }

        $token = sanitize_text_field(wp_unslash($_POST['evf_token']));
        $new_password = wp_unslash($_POST['new_password']); // Don't sanitize passwords
        $confirm_password = wp_unslash($_POST['confirm_password']); // Don't sanitize passwords

        // Validation
        if (empty($new_password) || empty($confirm_password)) {
            wp_send_json_error(array('message' => esc_html__('Lütfen tüm alanları doldurun.', 'email-verification-forms')));
        }

        if ($new_password !== $confirm_password) {
            wp_send_json_error(array('message' => esc_html__('Parolalar eşleşmiyor.', 'email-verification-forms')));
        }

        // Password strength check
        if (!$this->is_password_strong($new_password)) {
            wp_send_json_error(array('message' => esc_html__('Parola çok zayıf. En az 8 karakter, büyük harf, küçük harf ve rakam içermelidir.', 'email-verification-forms')));
        }

        // Token'ı doğrula
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE token = %s AND status = 'completed'",
            $token
        ));

        if (!$verification || !$verification->user_id) {
            wp_send_json_error(array('message' => esc_html__('Geçersiz token.', 'email-verification-forms')));
        }

        $user_id = $verification->user_id;

        // Parola değiştirme gerekli mi?
        $password_change_required = get_user_meta($user_id, 'evf_password_change_required', true);
        if (!$password_change_required) {
            wp_send_json_error(array('message' => esc_html__('Parola değiştirme gerekli değil.', 'email-verification-forms')));
        }

        // Parolayı güncelle
        wp_set_password($new_password, $user_id);

        // Meta'ları temizle
        update_user_meta($user_id, 'evf_password_change_required', 0);
        update_user_meta($user_id, 'evf_password_changed_at', current_time('mysql'));

        // Token'ı final olarak işaretle
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table_name,
            array('status' => 'final_completed'),
            array('id' => $verification->id),
            array('%s'),
            array('%d')
        );

        // Kullanıcıyı otomatik login yap
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        // Cache'i temizle
        wp_cache_delete('evf_user_verification_' . $user_id);

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
        $min_length = (int) get_option('evf_min_password_length', 8);

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
     * Get password requirements text for display
     */
    public function get_password_requirements_text() {
        $min_length = (int) get_option('evf_min_password_length', 8);
        $require_strong = (bool) get_option('evf_require_strong_password', true);

        if ($require_strong) {
            /* translators: %d: Minimum password length */
            return sprintf(
                esc_html__('En az %d karakter, büyük harf, küçük harf ve rakam içermelidir.', 'email-verification-forms'),
                $min_length
            );
        } else {
            /* translators: %d: Minimum password length */
            return sprintf(
                esc_html__('En az %d karakter olmalıdır.', 'email-verification-forms'),
                $min_length
            );
        }
    }

    /**
     * Clear user-related caches
     */
    private function clear_user_caches($user_id) {
        $cache_keys = array(
            'evf_user_verification_' . $user_id,
            'evf_user_status_' . $user_id,
            'evf_password_change_' . $user_id
        );

        foreach ($cache_keys as $key) {
            wp_cache_delete($key);
        }
    }
}