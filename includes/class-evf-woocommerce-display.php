<?php
/**
 * EVF WooCommerce Display Handler - Part 4/4
 * Template display işlemleri - TÜM ÖPTİMİZASYONLAR UYGULANMIŞ VERSİYON
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_Display {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Display hooks can be added here if needed
        add_action('wp_enqueue_scripts', array($this, 'enqueue_verification_assets'), 100);
    }

    /**
     * Verification sayfaları için CSS/JS assets
     */
    public function enqueue_verification_assets() {
        if (defined('EVF_CODE_VERIFICATION_PAGE')) {
            // Custom CSS for verification pages
            wp_add_inline_style('wp-block-library', $this->get_verification_css());
        }
    }

    /**
     * DÜZELTME: Kod doğrulama sayfasını göster - Status kontrolü + Countdown fix + Enhanced UX
     */
    public function show_code_verification_page($email) {
        // Email'in pending registrations'da olup olmadığını kontrol et
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
         WHERE email = %s 
         AND verification_type = 'code' 
         ORDER BY created_at DESC 
         LIMIT 1",
            $email
        ));

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($registration) {
                error_log('EVF WooCommerce: Magic link clicked - Email: ' . $email . ', Status: ' . $registration->status);
            } else {
                error_log('EVF WooCommerce: Magic link clicked - No registration found for: ' . $email);
            }
        }

        if (!$registration) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: No registration found, redirecting with error');
            }
            wp_redirect(add_query_arg('evf_error', 'registration_not_found', wc_get_page_permalink('myaccount')));
            exit;
        }

        // DÜZELTME: Status kontrolü ekle
        if ($registration->status === 'completed') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Registration already completed, showing already verified page');
            }
            $this->show_already_verified_page($email);
            return;
        }

        if ($registration->status !== 'pending') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce: Invalid registration status: ' . $registration->status);
            }
            wp_redirect(add_query_arg('evf_error', 'invalid_status', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Template variables
        $email = $registration->email;
        $last_code_sent = $registration->last_code_sent;

        // Template'i include et
        $template_path = EVF_TEMPLATES_PATH . 'code-verification.php';

        if (file_exists($template_path)) {
            // WP head/footer'ı deaktif ederek sadece template'i göster
            define('EVF_CODE_VERIFICATION_PAGE', true);
            include $template_path;
            exit;
        } else {
            // Fallback - optimize edilmiş HTML sayfası göster
            $this->show_enhanced_code_verification_page($email);
        }
    }

    /**
     * Zaten doğrulanmış sayfasını göster - Template kullan
     */
    private function show_already_verified_page($email) {
        $template_path = EVF_TEMPLATES_PATH . 'already-verified.php';

        if (file_exists($template_path)) {
            // Template'i include et
            include $template_path;
            exit;
        } else {
            // Fallback - enhanced HTML sayfası göster
            $this->show_enhanced_already_verified_page($email);
        }
    }

    /**
     * YENI: Enhanced "zaten doğrulanmış" sayfası - Modern tasarım
     */
    private function show_enhanced_already_verified_page($email) {
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html(get_bloginfo('name')); ?> - E-posta Doğrulandı</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <?php echo $this->get_verification_css(); ?>
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

                        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
                           class="evf-btn evf-btn-secondary">
                            <span class="evf-btn-icon">🛍️</span>
                            Alışverişe Başla
                        </a>
                    </div>

                    <div class="evf-additional-links">
                        <a href="<?php echo esc_url(home_url()); ?>" class="evf-link">
                            Ana Sayfa
                        </a>
                        <span class="evf-separator">•</span>
                        <a href="<?php echo esc_url(get_permalink(wc_get_page_id('terms'))); ?>" class="evf-link">
                            Kullanım Şartları
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Simple success page animation
            document.addEventListener('DOMContentLoaded', function() {
                const card = document.querySelector('.evf-success-card');
                const icon = document.querySelector('.evf-success-icon-wrapper');

                setTimeout(() => {
                    card.classList.add('evf-animate-in');
                    icon.classList.add('evf-animate-check');
                }, 100);
            });
        </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * YENI: Enhanced kod doğrulama sayfası - Tüm optimizasyonlar uygulanmış
     */
    private function show_enhanced_code_verification_page($email) {
        // AJAX handler'dan remaining seconds al
        $wc_main = EVF_WooCommerce::instance();
        $ajax_handler = $wc_main->get_ajax_handler();
        $remaining_seconds = 0;

        if ($ajax_handler && method_exists($ajax_handler, 'get_remaining_seconds')) {
            $remaining_seconds = $ajax_handler->get_remaining_seconds($email);
        }

        // Config values
        $resend_interval = (int) get_option('evf_code_resend_interval', 2) * 60;
        $max_attempts = (int) get_option('evf_max_verification_attempts', 3);
        $code_expiry = (int) get_option('evf_code_expiry_minutes', 30) * 60;

        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html(get_bloginfo('name')); ?> - E-posta Doğrulama</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <meta name="description" content="E-posta adresinizi doğrulayın">
            <?php echo $this->get_verification_css(); ?>
        </head>
        <body class="evf-verification-body">
        <div class="evf-container">
            <div class="evf-card evf-verification-card">
                <!-- Progress Bar -->
                <div class="evf-progress-wrapper">
                    <div class="evf-progress-bar">
                        <div class="evf-progress-step evf-step-completed">
                            <div class="evf-progress-circle">✓</div>
                            <span class="evf-progress-label">E-posta</span>
                        </div>
                        <div class="evf-progress-step evf-step-active">
                            <div class="evf-progress-circle">2</div>
                            <span class="evf-progress-label">Doğrulama</span>
                        </div>
                        <div class="evf-progress-step">
                            <div class="evf-progress-circle">3</div>
                            <span class="evf-progress-label">Tamamla</span>
                        </div>
                    </div>
                </div>

                <!-- Header -->
                <div class="evf-header">
                    <div class="evf-icon">📧</div>
                    <h1 class="evf-title">Doğrulama Kodunu Girin</h1>
                    <p class="evf-description">
                        <strong class="evf-email"><?php echo esc_html($email); ?></strong>
                        adresine 6 haneli doğrulama kodu gönderdik.
                    </p>
                </div>

                <!-- Message Area -->
                <div id="evf-message" class="evf-message" style="display: none;"></div>

                <!-- Code Form -->
                <form id="evf-code-form" class="evf-form" novalidate>
                    <div class="evf-form-group">
                        <label for="evf-code-input" class="evf-form-label">
                            Doğrulama Kodu
                            <span class="evf-required">*</span>
                        </label>
                        <input type="text"
                               id="evf-code-input"
                               class="evf-form-input evf-code-input"
                               placeholder="123456"
                               maxlength="6"
                               inputmode="numeric"
                               pattern="[0-9]{6}"
                               autocomplete="one-time-code"
                               required
                               aria-describedby="evf-code-help">
                        <div id="evf-code-help" class="evf-input-help">
                            6 haneli sayısal kodu girin
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

                <!-- Resend Section -->
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

                <!-- Help Section -->
                <details class="evf-help-details">
                    <summary class="evf-help-summary">
                        💡 Yardım ve İpuçları
                    </summary>
                    <div class="evf-help-content">
                        <ul class="evf-help-list">
                            <li>E-posta gelene kadar 2-3 dakika bekleyin</li>
                            <li>Spam/Gereksiz e-posta klasörünüzü kontrol edin</li>
                            <li>E-posta adresinizi doğru yazdığınızdan emin olun</li>
                            <li>Kod <?php echo esc_html(get_option('evf_code_expiry_minutes', 30)); ?> dakika geçerlidir</li>
                            <li>Sorun devam ederse destek ile iletişime geçin</li>
                        </ul>
                    </div>
                </details>

                <!-- Footer -->
                <div class="evf-footer">
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount') . '?action=register'); ?>"
                       class="evf-back-link">
                        ← Farklı e-posta ile kayıt ol
                    </a>

                    <div class="evf-footer-links">
                        <a href="<?php echo esc_url(home_url()); ?>" class="evf-link">Ana Sayfa</a>
                        <span class="evf-separator">•</span>
                        <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>" class="evf-link">Destek</a>
                    </div>
                </div>
            </div>
        </div>

        <script>
            <?php echo $this->get_verification_javascript($email, $remaining_seconds, $resend_interval, $max_attempts, $code_expiry); ?>
        </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Basit kod doğrulama sayfası (geriye dönük uyumluluk için)
     * @deprecated Use show_enhanced_code_verification_page instead
     */
    private function show_simple_code_verification_page($email) {
        // Redirect to enhanced version
        $this->show_enhanced_code_verification_page($email);
    }

    /**
     * Verification CSS styles
     */
    private function get_verification_css() {
        ob_start();
        ?>
        <style>
            /* EVF Verification Page Styles */
            :root {
                --evf-primary: #3b82f6;
                --evf-primary-hover: #2563eb;
                --evf-success: #10b981;
                --evf-error: #ef4444;
                --evf-warning: #f59e0b;
                --evf-gray-50: #f9fafb;
                --evf-gray-100: #f3f4f6;
                --evf-gray-200: #e5e7eb;
                --evf-gray-300: #d1d5db;
                --evf-gray-500: #6b7280;
                --evf-gray-700: #374151;
                --evf-gray-900: #111827;
                --evf-white: #ffffff;
                --evf-border-radius: 12px;
                --evf-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                --evf-transition: all 0.2s ease-in-out;
            }

            /* Reset and base */
            * { box-sizing: border-box; }

            .evf-verification-body {
                margin: 0;
                padding: 20px;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1.6;
            }

            .evf-success-page {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            }

            .evf-container {
                width: 100%;
                max-width: 480px;
            }

            .evf-card {
                background: var(--evf-white);
                border-radius: var(--evf-border-radius);
                box-shadow: var(--evf-shadow);
                padding: 40px;
                position: relative;
                overflow: hidden;
                transform: translateY(20px);
                opacity: 0;
                animation: evf-slide-in 0.5s ease-out forwards;
            }

            @keyframes evf-slide-in {
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            .evf-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--evf-primary), #6366f1);
            }

            /* Progress bar */
            .evf-progress-wrapper {
                margin-bottom: 32px;
            }

            .evf-progress-bar {
                display: flex;
                justify-content: space-between;
                position: relative;
                margin: 0 20px;
            }

            .evf-progress-bar::before {
                content: '';
                position: absolute;
                top: 12px;
                left: 0;
                right: 0;
                height: 2px;
                background: var(--evf-gray-200);
                z-index: 1;
            }

            .evf-progress-step {
                display: flex;
                flex-direction: column;
                align-items: center;
                position: relative;
                z-index: 2;
                flex: 1;
            }

            .evf-progress-circle {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 600;
                background: var(--evf-gray-200);
                color: var(--evf-gray-500);
                margin-bottom: 8px;
                transition: var(--evf-transition);
            }

            .evf-step-completed .evf-progress-circle {
                background: var(--evf-success);
                color: var(--evf-white);
            }

            .evf-step-active .evf-progress-circle {
                background: var(--evf-primary);
                color: var(--evf-white);
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            }

            .evf-progress-label {
                font-size: 11px;
                color: var(--evf-gray-500);
                font-weight: 500;
            }

            .evf-step-completed .evf-progress-label,
            .evf-step-active .evf-progress-label {
                color: var(--evf-gray-700);
                font-weight: 600;
            }

            /* Header */
            .evf-header {
                text-align: center;
                margin-bottom: 32px;
            }

            .evf-icon {
                font-size: 3rem;
                margin-bottom: 16px;
                animation: evf-bounce 2s infinite;
            }

            @keyframes evf-bounce {
                0%, 20%, 53%, 80%, 100% { transform: translate3d(0,0,0); }
                40%, 43% { transform: translate3d(0,-15px,0); }
                70% { transform: translate3d(0,-7px,0); }
                90% { transform: translate3d(0,-2px,0); }
            }

            .evf-title {
                color: var(--evf-gray-900);
                font-size: 1.5rem;
                font-weight: 700;
                margin: 0 0 12px 0;
            }

            .evf-description {
                color: var(--evf-gray-500);
                margin: 0;
                line-height: 1.5;
            }

            .evf-email {
                color: var(--evf-primary);
                font-weight: 600;
            }

            /* Form */
            .evf-form {
                margin-bottom: 32px;
            }

            .evf-form-group {
                margin-bottom: 24px;
            }

            .evf-form-label {
                display: block;
                font-weight: 600;
                color: var(--evf-gray-700);
                margin-bottom: 8px;
                font-size: 14px;
            }

            .evf-required {
                color: var(--evf-error);
                margin-left: 2px;
            }

            .evf-form-input {
                width: 100%;
                padding: 16px;
                border: 2px solid var(--evf-gray-200);
                border-radius: 8px;
                font-size: 16px;
                transition: var(--evf-transition);
                background: var(--evf-white);
            }

            .evf-form-input:focus {
                outline: none;
                border-color: var(--evf-primary);
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }

            .evf-code-input {
                font-family: 'Monaco', 'Consolas', 'Courier New', monospace;
                font-size: 1.5rem;
                text-align: center;
                letter-spacing: 0.25rem;
                background: var(--evf-gray-50);
                font-weight: 600;
                padding: 20px 16px;
            }

            .evf-input-help {
                font-size: 12px;
                color: var(--evf-gray-500);
                text-align: center;
                margin-top: 8px;
            }

            /* Buttons */
            .evf-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 16px 24px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 8px;
                border: none;
                cursor: pointer;
                text-decoration: none;
                transition: var(--evf-transition);
                min-height: 52px;
                gap: 8px;
            }

            .evf-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none !important;
            }

            .evf-btn-primary {
                background: linear-gradient(135deg, var(--evf-primary), #6366f1);
                color: var(--evf-white);
                box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.4);
            }

            .evf-btn-primary:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px 0 rgba(59, 130, 246, 0.5);
            }

            .evf-btn-secondary {
                background: var(--evf-gray-100);
                color: var(--evf-gray-700);
                border: 1px solid var(--evf-gray-300);
            }

            .evf-btn-secondary:hover:not(:disabled) {
                background: var(--evf-gray-200);
                transform: translateY(-1px);
            }

            .evf-btn-full {
                width: 100%;
            }

            .evf-btn-icon {
                display: inline-block;
            }

            .evf-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                border: 2px solid transparent;
                border-top: 2px solid currentColor;
                border-radius: 50%;
                animation: evf-spin 1s linear infinite;
            }

            @keyframes evf-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Messages */
            .evf-message {
                padding: 16px;
                border-radius: 8px;
                margin-bottom: 24px;
                font-weight: 500;
                display: flex;
                align-items: flex-start;
                gap: 8px;
            }

            .evf-message.evf-message-success {
                background: #d1fae5;
                color: #065f46;
                border: 1px solid #a7f3d0;
            }

            .evf-message.evf-message-error {
                background: #fee2e2;
                color: #991b1b;
                border: 1px solid #fca5a5;
            }

            /* Resend section */
            .evf-resend-section {
                background: var(--evf-gray-50);
                padding: 24px;
                border-radius: 8px;
                border: 1px solid var(--evf-gray-200);
                text-align: center;
                margin-bottom: 24px;
            }

            .evf-resend-title {
                color: var(--evf-gray-700);
                font-size: 16px;
                font-weight: 600;
                margin: 0 0 8px 0;
            }

            .evf-resend-description {
                color: var(--evf-gray-500);
                font-size: 14px;
                margin: 0 0 16px 0;
                line-height: 1.4;
            }

            /* Help section */
            .evf-help-details {
                margin-bottom: 24px;
                border: 1px solid var(--evf-gray-200);
                border-radius: 8px;
                overflow: hidden;
            }

            .evf-help-summary {
                padding: 16px;
                background: var(--evf-gray-50);
                cursor: pointer;
                font-weight: 600;
                color: var(--evf-gray-700);
                user-select: none;
            }

            .evf-help-summary:hover {
                background: var(--evf-gray-100);
            }

            .evf-help-content {
                padding: 16px;
            }

            .evf-help-list {
                margin: 0;
                padding-left: 20px;
                color: var(--evf-gray-600);
            }

            .evf-help-list li {
                margin-bottom: 4px;
                line-height: 1.4;
            }

            /* Footer */
            .evf-footer {
                text-align: center;
                padding-top: 24px;
                border-top: 1px solid var(--evf-gray-200);
            }

            .evf-back-link,
            .evf-link {
                color: var(--evf-gray-500);
                text-decoration: none;
                font-size: 14px;
                transition: var(--evf-transition);
            }

            .evf-back-link:hover,
            .evf-link:hover {
                color: var(--evf-primary);
            }

            .evf-footer-links {
                margin-top: 12px;
                font-size: 12px;
            }

            .evf-separator {
                margin: 0 8px;
                color: var(--evf-gray-400);
            }

            /* Success page specific styles */
            .evf-success-card {
                text-align: center;
            }

            .evf-success-icon-wrapper {
                margin-bottom: 24px;
            }

            .evf-success-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, var(--evf-success), #059669);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto;
                transform: scale(0);
                animation: evf-scale-in 0.5s ease-out 0.3s forwards;
            }

            @keyframes evf-scale-in {
                to { transform: scale(1); }
            }

            .evf-checkmark {
                width: 32px;
                height: 32px;
                stroke: white;
                stroke-width: 2;
            }

            .evf-checkmark-check {
                stroke-dasharray: 29;
                stroke-dashoffset: 29;
                animation: evf-checkmark 0.5s ease-out 0.8s forwards;
            }

            @keyframes evf-checkmark {
                to { stroke-dashoffset: 0; }
            }

            .evf-success-title {
                color: var(--evf-success);
                font-size: 1.75rem;
                margin-bottom: 16px;
            }

            .evf-success-message {
                background: #d1fae5;
                color: #065f46;
                padding: 20px;
                border-radius: 8px;
                margin: 24px 0;
                border: 1px solid #a7f3d0;
                display: flex;
                align-items: flex-start;
                gap: 12px;
                text-align: left;
            }

            .evf-success-icon-small {
                font-size: 1.2rem;
                flex-shrink: 0;
            }

            .evf-button-group {
                display: flex;
                gap: 12px;
                justify-content: center;
                margin: 32px 0;
                flex-wrap: wrap;
            }

            .evf-additional-links {
                margin-top: 24px;
                padding-top: 16px;
                border-top: 1px solid var(--evf-gray-200);
            }

            /* Mobile responsive */
            @media (max-width: 640px) {
                .evf-verification-body {
                    padding: 16px;
                }

                .evf-card {
                    padding: 24px 20px;
                }

                .evf-code-input {
                    font-size: 1.25rem;
                    letter-spacing: 0.15rem;
                }

                .evf-button-group {
                    flex-direction: column;
                }

                .evf-btn {
                    width: 100%;
                }

                .evf-progress-bar {
                    margin: 0 10px;
                }

                .evf-progress-circle {
                    width: 24px;
                    height: 24px;
                    font-size: 10px;
                }

                .evf-progress-label {
                    font-size: 10px;
                }
            }

            /* High contrast mode */
            @media (prefers-contrast: high) {
                :root {
                    --evf-primary: #0000ff;
                    --evf-success: #008000;
                    --evf-error: #ff0000;
                }
            }

            /* Reduced motion */
            @media (prefers-reduced-motion: reduce) {
                * {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Enhanced verification JavaScript
     */
    private function get_verification_javascript($email, $remaining_seconds, $resend_interval, $max_attempts, $code_expiry) {
        ob_start();
        ?>
        // EVF Enhanced Code Verification JavaScript
        document.addEventListener('DOMContentLoaded', function() {
        console.log('EVF Code Verification: Enhanced version loaded');

        // Configuration
        const config = {
        email: <?php echo wp_json_encode($email); ?>,
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce('evf_nonce')); ?>,
        resendInterval: <?php echo intval($resend_interval); ?>,
        maxAttempts: <?php echo intval($max_attempts); ?>,
        codeExpiry: <?php echo intval($code_expiry); ?>,
        accountUrl: <?php echo wp_json_encode(wc_get_page_permalink('myaccount')); ?>,
        registerUrl: <?php echo wp_json_encode(wc_get_page_permalink('myaccount') . '?action=register'); ?>
        };

        // DOM elements
        const elements = {
        form: document.getElementById('evf-code-form'),
        input: document.getElementById('evf-code-input'),
        message: document.getElementById('evf-message'),
        submitBtn: document.getElementById('evf-submit-btn'),
        submitText: document.getElementById('evf-submit-text'),
        submitLoading: document.getElementById('evf-submit-loading'),
        resendBtn: document.getElementById('evf-resend-btn'),
        resendText: document.getElementById('evf-resend-text'),
        resendCountdown: document.getElementById('evf-resend-countdown'),
        resendLoading: document.getElementById('evf-resend-loading'),
        timer: document.getElementById('evf-countdown-timer')
        };

        // Validation
        if (!elements.form || !elements.input) {
        console.error('EVF: Required elements not found');
        return;
        }

        let countdownTimer = null;
        let attemptCount = 0;

        // Message functions
        function showMessage(type, text, duration = 5000) {
        if (!elements.message) return;

        elements.message.style.display = 'flex';
        elements.message.textContent = text;
        elements.message.className = 'evf-message evf-message-' + type;

        if (duration > 0) {
        setTimeout(() => hideMessage(), duration);
        }
        }

        function hideMessage() {
        if (elements.message) {
        elements.message.style.display = 'none';
        }
        }

        // Countdown functions
        function startCountdown(seconds) {
        let remaining = Math.floor(seconds);
        console.log('Starting countdown:', remaining, 'seconds');

        if (remaining <= 0) {
        endCountdown();
        return;
        }

        if (countdownTimer) {
        clearInterval(countdownTimer);
        }

        setCountdownState(true);
        updateCountdownDisplay(remaining);

        countdownTimer = setInterval(() => {
        remaining--;
        updateCountdownDisplay(remaining);

        if (remaining <= 0) {
        endCountdown();
        }
        }, 1000);
        }

        function updateCountdownDisplay(seconds) {
        if (elements.timer) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        const display = minutes > 0 ?
        `${minutes}:${secs.toString().padStart(2, '0')}` :
        secs.toString();
        elements.timer.textContent = display;
        }
        }

        function setCountdownState(active) {
        elements.resendBtn.disabled = active;
        elements.resendText.style.display = active ? 'none' : 'flex';
        elements.resendCountdown.style.display = active ? 'flex' : 'none';
        elements.resendLoading.style.display = 'none';
        }

        function endCountdown() {
        if (countdownTimer) {
        clearInterval(countdownTimer);
        countdownTimer = null;
        }
        setCountdownState(false);
        console.log('Countdown finished');
        }

        // Input handling
        elements.input.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');

        if (value.length > 6) {
        value = value.substr(0, 6);
        }

        this.value = value;
        this.style.borderColor = '';
        this.style.boxShadow = '';
        hideMessage();

        // Auto-submit on 6 digits
        if (value.length === 6) {
        console.log('Auto-submitting code:', value);
        setTimeout(() => {
        if (!elements.submitBtn.disabled) {
        elements.form.dispatchEvent(new Event('submit'));
        }
        }, 300);
        }
        });

        // Form submission
        elements.form.addEventListener('submit', function(e) {
        e.preventDefault();

        const code = elements.input.value.trim();

        if (code.length !== 6 || !/^\d{6}$/.test(code)) {
        showInputError('Lütfen 6 haneli sayısal kod girin.');
        return;
        }

        if (elements.submitBtn.disabled) {
        return;
        }

        submitCode(code);
        });

        function showInputError(message) {
        elements.input.style.borderColor = 'var(--evf-error)';
        elements.input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
        elements.input.focus();
        showMessage('error', message);
        }

        function submitCode(code) {
        console.log('Submitting code:', code);
        attemptCount++;

        setSubmitLoading(true);

        fetch(config.ajaxUrl, {
        method: 'POST',
        headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
        action: 'evf_verify_code',
        nonce: config.nonce,
        email: config.email,
        verification_code: code
        })
        })
        .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
        })
        .then(data => {
        console.log('Verification response:', data);
        handleVerificationResponse(data);
        })
        .catch(error => {
        console.error('Verification error:', error);
        showMessage('error', 'Bir hata oluştu. Lütfen tekrar deneyin.');
        setSubmitLoading(false);
        });
        }

        function handleVerificationResponse(data) {
        if (data.success) {
        elements.input.style.borderColor = 'var(--evf-success)';
        elements.input.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';

        showMessage('success', '🎉 Kod doğrulandı! Yönlendiriliyor...', 0);

        setTimeout(() => {
        const redirectUrl = (data.data && data.data.redirect_url) ?
        data.data.redirect_url : config.accountUrl;
        window.location.href = redirectUrl;
        }, 2000);

        } else {
        let errorMsg = 'Geçersiz kod. Lütfen tekrar deneyin.';

        if (data.data === 'code_expired') {
        errorMsg = '⏰ Kodun süresi dolmuş. Lütfen yeni kod isteyin.';
        } else if (data.data === 'max_attempts') {
        errorMsg = '🚫 Çok fazla yanlış deneme. Kayıt işleminiz iptal edildi.';
        setTimeout(() => {
        window.location.href = config.registerUrl;
        }, 3000);
        } else if (data.data === 'invalid_code') {
        const remaining = config.maxAttempts - attemptCount;
        errorMsg = `❌ Yanlış kod. ${remaining} deneme hakkınız kaldı.`;
        }

        showInputError(errorMsg);
        setSubmitLoading(false);
        }
        }

        function setSubmitLoading(loading) {
        elements.submitBtn.disabled = loading;
        elements.submitText.style.display = loading ? 'none' : 'flex';
        elements.submitLoading.style.display = loading ? 'flex' : 'none';
        }

        // Resend functionality
        elements.resendBtn.addEventListener('click', function(e) {
        e.preventDefault();

        if (this.disabled) {
        return;
        }

        resendCode();
        });

        function resendCode() {
        console.log('Resending verification code');

        elements.resendBtn.disabled = true;
        elements.resendText.style.display = 'none';
        elements.resendLoading.style.display = 'flex';

        fetch(config.ajaxUrl, {
        method: 'POST',
        headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
        action: 'evf_resend_code',
        nonce: config.nonce,
        email: config.email
        })
        })
        .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
        })
        .then(data => {
        console.log('Resend response:', data);
        handleResendResponse(data);
        })
        .catch(error => {
        console.error('Resend error:', error);
        showMessage('error', 'Kod gönderilemedi. Lütfen tekrar deneyin.');
        resetResendButton();
        });
        }

        function handleResendResponse(data) {
        if (data.success) {
        showMessage('success', '📨 Yeni doğrulama kodu gönderildi!');
        startCountdown(config.resendInterval);
        attemptCount = 0;
        elements.input.value = '';
        elements.input.focus();
        } else {
        let errorMsg = 'Kod gönderilemedi. Lütfen tekrar deneyin.';

        if (data.data === 'rate_limit') {
        errorMsg = '⏱️ Çok hızlı kod istiyorsunuz. Lütfen bekleyin.';
        } else if (data.data === 'email_not_found') {
        errorMsg = '❌ E-posta adresi bulunamadı. Lütfen yeniden kayıt olun.';
        setTimeout(() => {
        window.location.href = config.registerUrl;
        }, 3000);
        }

        showMessage('error', errorMsg);
        resetResendButton();
        }
        }

        function resetResendButton() {
        elements.resendBtn.disabled = false;
        elements.resendText.style.display = 'flex';
        elements.resendLoading.style.display = 'none';
        elements.resendCountdown.style.display = 'none';
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
        if (elements.input === document.activeElement) {
        e.preventDefault();
        elements.form.dispatchEvent(new Event('submit'));
        }
        }

        if (e.key === 'Escape') {
        elements.input.value = '';
        elements.input.focus();
        hideMessage();
        }
        });

        // Initialize
        const initialRemaining = <?php echo intval($remaining_seconds); ?>;
        console.log('Initial remaining seconds:', initialRemaining);

        if (initialRemaining > 0) {
        startCountdown(initialRemaining);
        }

        setTimeout(() => {
        elements.input.focus();
        }, 100);

        // Page visibility handling
        if (typeof document.visibilityState !== 'undefined') {
        document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible' && countdownTimer) {
        console.log('Page became visible');
        }
        });
        }

        // Cleanup
        window.addEventListener('beforeunload', function() {
        if (countdownTimer) {
        clearInterval(countdownTimer);
        }
        });

        console.log('EVF Code Verification: Enhanced version initialized');
        });
        <?php
        return ob_get_clean();
    }
}