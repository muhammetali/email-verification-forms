<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('E-posta Doğrulama', 'email-verification-forms'); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="evf-code-verification-page">

<div class="evf-code-verification-wrapper">
    <div class="evf-code-verification-card evf-fade-in">
        <!-- Progress Bar -->
        <div class="evf-progress-bar">
            <div class="evf-progress-step completed">
                <div class="evf-progress-circle">✓</div>
                <div class="evf-progress-label"><?php esc_html_e('E-posta', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step active">
                <div class="evf-progress-circle">2</div>
                <div class="evf-progress-label"><?php esc_html_e('Kod Doğrulama', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step">
                <div class="evf-progress-circle">3</div>
                <div class="evf-progress-label"><?php esc_html_e('Parola', 'email-verification-forms'); ?></div>
            </div>
        </div>

        <!-- Form Header -->
        <div class="evf-form-header">
            <div class="evf-code-icon">📧</div>
            <h1 class="evf-form-title"><?php esc_html_e('Doğrulama Kodunu Girin', 'email-verification-forms'); ?></h1>
            <p class="evf-form-subtitle">
                <?php
                /* translators: %s: User email address (wrapped in <strong> tags) */
                printf(esc_html__('%s adresine 6 haneli doğrulama kodu gönderdik.', 'email-verification-forms'),
                    '<strong>' . esc_html($email) . '</strong>');
                ?>
            </p>
        </div>

        <!-- Message Container -->
        <div class="evf-message" id="evf-message" role="alert" aria-live="polite"></div>

        <!-- Code Verification Form -->
        <form class="evf-code-verification-form" id="evf-code-verification-form" novalidate>
            <?php wp_nonce_field('evf_nonce', 'evf_nonce', false); ?>
            <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">

            <div class="evf-form-group">
                <label for="evf-verification-code" class="evf-label">
                    <?php esc_html_e('Doğrulama Kodu', 'email-verification-forms'); ?>
                    <span class="required">*</span>
                </label>

                <div class="evf-code-input-wrapper">
                    <input type="text"
                           id="evf-verification-code"
                           name="verification_code"
                           class="evf-code-input"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           placeholder="123456"
                           autocomplete="one-time-code"
                           inputmode="numeric"
                           required>
                    <div class="evf-code-input-help">
                        <?php esc_html_e('6 haneli kodu girin', 'email-verification-forms'); ?>
                    </div>
                </div>
            </div>

            <div class="evf-form-group">
                <button type="submit" class="evf-btn evf-btn-primary evf-btn-full evf-submit-btn">
                    <span class="evf-btn-text">✅ <?php esc_html_e('Kodu Doğrula', 'email-verification-forms'); ?></span>
                    <span class="evf-btn-loading" style="display: none;">
                        <span class="evf-spinner"></span>
                        <?php esc_html_e('Doğrulanıyor...', 'email-verification-forms'); ?>
                    </span>
                </button>
            </div>
        </form>

        <!-- Resend Code Section -->
        <div class="evf-resend-section">
            <p class="evf-resend-text"><?php esc_html_e('Kod gelmedi mi?', 'email-verification-forms'); ?></p>

            <button type="button" id="evf-resend-code" class="evf-btn evf-btn-secondary evf-resend-btn">
                <span class="evf-resend-text">📤 <?php esc_html_e('Kodu Tekrar Gönder', 'email-verification-forms'); ?></span>
                <span class="evf-resend-countdown" style="display: none;">
                    ⏱️ <span id="countdown-timer"></span> <?php esc_html_e('saniye bekleyin', 'email-verification-forms'); ?>
                </span>
                <span class="evf-resend-loading" style="display: none;">
                    <span class="evf-spinner-small"></span>
                    <?php esc_html_e('Gönderiliyor...', 'email-verification-forms'); ?>
                </span>
            </button>
        </div>

        <!-- Help Section -->
        <div class="evf-help-section">
            <details class="evf-help-details">
                <summary><?php esc_html_e('Kod gelmedi mi?', 'email-verification-forms'); ?></summary>
                <div class="evf-help-content">
                    <ul>
                        <li><?php esc_html_e('Spam/Junk klasörünüzü kontrol edin', 'email-verification-forms'); ?></li>
                        <li><?php esc_html_e('E-posta adresinizi doğru yazdığınızdan emin olun', 'email-verification-forms'); ?></li>
                        <li><?php esc_html_e('Birkaç dakika bekleyin, e-posta gelmesi zaman alabilir', 'email-verification-forms'); ?></li>
                        <li><?php esc_html_e('Kod 30 dakika geçerlidir', 'email-verification-forms'); ?></li>
                    </ul>
                </div>
            </details>
        </div>

        <!-- Back to Registration -->
        <div class="evf-back-section">
            <?php if (evf_is_woocommerce_active()): ?>
                <a href="<?php echo esc_url(wc_get_page_permalink('myaccount') . '?action=register'); ?>" class="evf-back-link">
                    ← <?php esc_html_e('Farklı e-posta ile kayıt ol', 'email-verification-forms'); ?>
                </a>
            <?php else: ?>
                <a href="<?php echo esc_url(wp_registration_url()); ?>" class="evf-back-link">
                    ← <?php esc_html_e('Farklı e-posta ile kayıt ol', 'email-verification-forms'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// CSS ve JS dosyalarını yükle
require_once dirname(__FILE__) . '/code-verification-styles.php';
require_once dirname(__FILE__) . '/code-verification-scripts.php';
?>

<?php wp_footer(); ?>
</body>
</html>