<?php
/**
 * EVF WooCommerce Display Templates Handler
 * HTML template oluşturma ve yönetimi
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_Display_Templates {

    private static $instance = null;

    /**
     * Template değişkenleri
     */
    private $template_vars = array();

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_template_vars();
    }

    /**
     * Template değişkenlerini başlat
     */
    private function init_template_vars() {
        $this->template_vars = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'admin_email' => get_option('admin_email'),
            'primary_color' => get_option('evf_primary_color', '#3b82f6'),
            'resend_interval' => get_option('evf_code_resend_interval', 5),
            'max_attempts' => get_option('evf_max_code_attempts', 5),
            'code_expiry' => get_option('evf_code_expiry_minutes', 30)
        );
    }

    /**
     * Kod doğrulama template'ini render et
     */
    public function render_code_verification($registration) {
        // Kalan süreyi hesapla
        $remaining_seconds = $this->calculate_remaining_seconds($registration->email);

        // Template variables
        $vars = array_merge($this->template_vars, array(
            'email' => $registration->email,
            'remaining_seconds' => $remaining_seconds,
            'registration' => $registration
        ));

        // Template'i render et
        $this->render_enhanced_code_verification($vars);
    }

    /**
     * Zaten doğrulanmış template'ini render et
     */
    public function render_already_verified($email) {
        $vars = array_merge($this->template_vars, array(
            'email' => $email
        ));

        $this->render_enhanced_already_verified($vars);
    }

    /**
     * Enhanced kod doğrulama sayfası
     */
    private function render_enhanced_code_verification($vars) {
        extract($vars);

        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html($site_name); ?> - E-posta Doğrulama</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <meta name="description" content="E-posta adresinizi doğrulayın">
            <?php $this->render_meta_tags(); ?>
            <?php $this->render_css_links(); ?>
        </head>
        <body class="evf-verification-body">
        <?php $this->render_page_loader(); ?>

        <div class="evf-container">
            <div class="evf-card evf-verification-card">
                <?php $this->render_progress_bar(2, 3); ?>

                <!-- Header -->
                <div class="evf-header">
                    <?php $this->render_site_logo(); ?>
                    <div class="evf-icon">📧</div>
                    <h1 class="evf-title">Doğrulama Kodunu Girin</h1>
                    <p class="evf-description">
                        <strong class="evf-email"><?php echo esc_html($email); ?></strong>
                        adresine 6 haneli doğrulama kodu gönderdik.
                    </p>
                </div>

                <?php $this->render_message_container(); ?>

                <!-- Code Form -->
                <?php $this->render_code_form($email); ?>

                <!-- Resend Section -->
                <?php $this->render_resend_section(); ?>

                <!-- Help Section -->
                <?php $this->render_help_section(); ?>

                <!-- Footer -->
                <?php $this->render_footer_links(); ?>
            </div>
        </div>

        <?php $this->render_javascript($email, $remaining_seconds); ?>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Enhanced zaten doğrulanmış sayfası
     */
    private function render_enhanced_already_verified($vars) {
        extract($vars);

        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html($site_name); ?> - E-posta Doğrulandı</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <?php $this->render_meta_tags(); ?>
            <?php $this->render_css_links(); ?>
        </head>
        <body class="evf-verification-body evf-success-page">
        <div class="evf-container">
            <div class="evf-card evf-success-card">
                <!-- Success Icon Animation -->
                <div class="evf-success-icon-wrapper">
                    <div class="evf-success-icon">
                        <svg viewBox="0 0 24 24" class="evf-checkmark">
                            <path class="evf-checkmark-check" fill="none" d="m1.73,12.91 8.1,8.1 11.5-11.5"/>
                        </svg>
                    </div>
                </div>

                <div class="evf-content">
                    <h1 class="evf-title evf-success-title">
                        🎉 E-posta Zaten Doğrulanmış!
                    </h1>

                    <p class="evf-description">
                        <strong class="evf-email"><?php echo esc_html($email); ?></strong>
                        e-posta adresi zaten doğrulanmış durumda.
                    </p>

                    <div class="evf-success-message">
                        <div class="evf-success-icon-small">✅</div>
                        <div>
                            <strong>Hesabınız aktif ve kullanıma hazır!</strong>
                            <br>
                            <small>Tüm özellikler erişiminize açık.</small>
                        </div>
                    </div>

                    <div class="evf-button-group">
                        <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
                           class="evf-btn evf-btn-primary">
                            <span class="evf-btn-icon">🏪</span>
                            Hesabıma Git
                        </a>

                        <?php if (wc_get_page_id('shop') > 0): ?>
                            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
                               class="evf-btn evf-btn-secondary">
                                <span class="evf-btn-icon">🛍️</span>
                                Alışverişe Başla
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="evf-additional-links">
                        <a href="<?php echo esc_url(home_url()); ?>" class="evf-link">
                            Ana Sayfa
                        </a>
                        <?php if (get_permalink(wc_get_page_id('terms'))): ?>
                            <span class="evf-separator">•</span>
                            <a href="<?php echo esc_url(get_permalink(wc_get_page_id('terms'))); ?>" class="evf-link">
                                Kullanım Şartları
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php $this->render_success_javascript(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Meta tags render et
     */
    private function render_meta_tags() {
        ?>
        <meta name="theme-color" content="<?php echo esc_attr($this->template_vars['primary_color']); ?>">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="format-detection" content="telephone=no">
        <?php
    }

    /**
     * CSS links render et
     */
    private function render_css_links() {
        // CSS handler'dan CSS'i al
        if (class_exists('EVF_WooCommerce_Display_CSS')) {
            $css_handler = EVF_WooCommerce_Display_CSS::instance();
            echo '<style>' . $css_handler->get_verification_css() . '</style>';
        }
    }

    /**
     * Site logosu render et
     */
    private function render_site_logo() {
        $custom_logo_id = get_theme_mod('custom_logo');

        if ($custom_logo_id) {
            $logo = wp_get_attachment_image(
                $custom_logo_id,
                'medium',
                false,
                array(
                    'class' => 'evf-logo',
                    'alt' => get_bloginfo('name'),
                    'style' => 'max-height: 60px; margin-bottom: 16px;'
                )
            );
            echo '<div class="evf-logo-wrapper">' . $logo . '</div>';
        }
    }

    /**
     * Progress bar render et
     */
    private function render_progress_bar($current = 2, $total = 3) {
        ?>
        <div class="evf-progress-wrapper">
            <div class="evf-progress-bar">
                <?php for ($i = 1; $i <= $total; $i++): ?>
                    <div class="evf-progress-step <?php echo $i < $current ? 'evf-step-completed' : ($i === $current ? 'evf-step-active' : ''); ?>">
                        <div class="evf-progress-circle">
                            <?php echo $i < $current ? '✓' : $i; ?>
                        </div>
                        <span class="evf-progress-label">
                        <?php
                        switch ($i) {
                            case 1: echo 'E-posta'; break;
                            case 2: echo 'Doğrulama'; break;
                            case 3: echo 'Tamamla'; break;
                            default: echo 'Adım ' . $i; break;
                        }
                        ?>
                    </span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Message container render et
     */
    private function render_message_container() {
        ?>
        <div id="evf-message" class="evf-message" style="display: none;" role="alert" aria-live="polite"></div>
        <?php
    }

    /**
     * Kod formu render et
     */
    private function render_code_form($email) {
        ?>
        <form id="evf-code-form" class="evf-form" novalidate>
            <?php wp_nonce_field('evf_nonce', 'evf_nonce', false); ?>
            <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">

            <div class="evf-form-group">
                <label for="evf-code-input" class="evf-form-label">
                    Doğrulama Kodu
                    <span class="evf-required">*</span>
                </label>
                <div class="evf-code-input-wrapper">
                    <input type="text"
                           id="evf-code-input"
                           name="verification_code"
                           class="evf-form-input evf-code-input"
                           placeholder="123456"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           data-error-message="6 haneli sayısal kod girin"
                           required
                           aria-describedby="evf-code-help">
                    <div id="evf-code-help" class="evf-code-input-help">
                        6 haneli sayısal kodu girin
                    </div>
                </div>
            </div>

            <button type="submit" id="evf-submit-btn" class="evf-btn evf-btn-primary evf-btn-full">
                <span id="evf-submit-text" class="evf-btn-text">
                    <span class="evf-btn-icon">✅</span>
                    Kodu Doğrula
                </span>
                <span id="evf-submit-loading" class="evf-btn-loading" style="display: none;">
                    <span class="evf-spinner"></span>
                    Doğrulanıyor...
                </span>
            </button>
        </form>
        <?php
    }

    /**
     * Resend section render et
     */
    private function render_resend_section() {
        ?>
        <div class="evf-resend-section">
            <h3 class="evf-resend-title">Kod gelmedi mi?</h3>
            <p class="evf-resend-description">
                E-posta gelmezse spam klasörünüzü kontrol edin veya yeni kod isteyin.
            </p>

            <button type="button" id="evf-resend-btn" class="evf-btn evf-btn-secondary">
                <span id="evf-resend-text" class="evf-btn-text">
                    <span class="evf-btn-icon">📤</span>
                    Kodu Tekrar Gönder
                </span>
                <span id="evf-resend-countdown" class="evf-btn-text" style="display: none;">
                    <span class="evf-btn-icon">⏱️</span>
                    <span id="evf-countdown-timer">0</span> saniye bekleyin
                </span>
                <span id="evf-resend-loading" class="evf-btn-loading" style="display: none;">
                    <span class="evf-spinner"></span>
                    Gönderiliyor...
                </span>
            </button>
        </div>
        <?php
    }

    /**
     * Help section render et
     */
    private function render_help_section() {
        ?>
        <details class="evf-help-details">
            <summary class="evf-help-summary">
                💡 Yardım ve İpuçları
            </summary>
            <div class="evf-help-content">
                <ul class="evf-help-list">
                    <li>E-posta gelene kadar 2-3 dakika bekleyin</li>
                    <li>Spam/Gereksiz e-posta klasörünüzü kontrol edin</li>
                    <li>E-posta adresinizi doğru yazdığınızdan emin olun</li>
                    <li>Kod <?php echo esc_html($this->template_vars['code_expiry']); ?> dakika geçerlidir</li>
                    <li>Sorun devam ederse destek ile iletişime geçin</li>
                </ul>
            </div>
        </details>
        <?php
    }

    /**
     * Footer links render et
     */
    private function render_footer_links() {
        ?>
        <div class="evf-footer">
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount') . '?action=register'); ?>"
               class="evf-back-link">
                ← Farklı e-posta ile kayıt ol
            </a>

            <div class="evf-footer-links">
                <a href="<?php echo esc_url($this->template_vars['site_url']); ?>" class="evf-link">Ana Sayfa</a>
                <span class="evf-separator">•</span>
                <a href="mailto:<?php echo esc_attr($this->template_vars['admin_email']); ?>" class="evf-link">Destek</a>
            </div>
        </div>
        <?php
    }

    /**
     * Page loader render et
     */
    private function render_page_loader() {
        ?>
        <div id="evf-page-loader" class="evf-page-loader" style="display: none;">
            <div class="evf-loader-spinner"></div>
            <div class="evf-loader-text">Yükleniyor...</div>
        </div>

        <style>
            .evf-page-loader {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            }

            .evf-loader-spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid var(--evf-primary, #3b82f6);
                border-radius: 50%;
                animation: evf-loader-spin 1s linear infinite;
                margin-bottom: 16px;
            }

            .evf-loader-text {
                color: var(--evf-gray-600, #6b7280);
                font-size: 14px;
            }

            @keyframes evf-loader-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <?php
    }

    /**
     * JavaScript render et
     */
    private function render_javascript($email, $remaining_seconds) {
        if (class_exists('EVF_WooCommerce_Display_JS')) {
            $js_handler = EVF_WooCommerce_Display_JS::instance();
            echo '<script>';
            echo $js_handler->get_verification_javascript(
                $email,
                $remaining_seconds,
                $this->template_vars['resend_interval'] * 60,
                $this->template_vars['max_attempts'],
                $this->template_vars['code_expiry'] * 60
            );
            echo '</script>';
        }
    }

    /**
     * Success JavaScript render et
     */
    private function render_success_javascript() {
        if (class_exists('EVF_WooCommerce_Display_JS')) {
            $js_handler = EVF_WooCommerce_Display_JS::instance();
            echo '<script>';
            echo $js_handler->get_success_javascript();
            echo '</script>';
        }
    }

    /**
     * Kalan süreyi hesapla
     */
    private function calculate_remaining_seconds($email) {
        $resend_interval = $this->template_vars['resend_interval'] * 60; // dakikayı saniyeye çevir
        $cache_key = 'evf_resend_limit_' . md5($email);

        $last_sent = get_transient($cache_key);

        if ($last_sent) {
            $elapsed = time() - $last_sent;
            return max(0, $resend_interval - $elapsed);
        }

        return 0;
    }

    /**
     * Template variables getter
     */
    public function get_template_vars() {
        return $this->template_vars;
    }

    /**
     * Template variable setter
     */
    public function set_template_var($key, $value) {
        $this->template_vars[$key] = $value;
    }

    /**
     * Bulk template variables setter
     */
    public function set_template_vars($vars) {
        $this->template_vars = array_merge($this->template_vars, $vars);
    }

    /**
     * Template cache temizle
     */
    public function clear_template_cache() {
        // Template cache'ini temizle (eğer cache kullanılıyorsa)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('evf_templates');
        }
    }

    /**
     * Template debug bilgileri
     */
    public function get_debug_info() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return array();
        }

        return array(
            'template_vars' => $this->template_vars,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        );
    }
}