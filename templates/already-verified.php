<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('E-posta Zaten Doğrulanmış', 'email-verification-forms'); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="evf-already-verified-page">

<div class="evf-already-verified-wrapper">
    <div class="evf-already-verified-card evf-fade-in">
        <!-- Progress Bar -->
        <div class="evf-progress-bar">
            <div class="evf-progress-step completed">
                <div class="evf-progress-circle">✓</div>
                <div class="evf-progress-label"><?php esc_html_e('E-posta', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step completed">
                <div class="evf-progress-circle">✓</div>
                <div class="evf-progress-label"><?php esc_html_e('Doğrulama', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step completed">
                <div class="evf-progress-circle">✓</div>
                <div class="evf-progress-label"><?php esc_html_e('Tamamlandı', 'email-verification-forms'); ?></div>
            </div>
        </div>

        <!-- Success Header -->
        <div class="evf-success-header">
            <div class="evf-success-icon">
                <div class="evf-checkmark">✓</div>
            </div>
            <h1 class="evf-success-title"><?php esc_html_e('E-posta Zaten Doğrulanmış!', 'email-verification-forms'); ?></h1>
            <p class="evf-success-subtitle">
                <?php
                if (isset($email) && $email) {
                    /* translators: %s: User email address (wrapped in <strong> tags) */
                    printf(esc_html__('%s e-posta adresi zaten doğrulanmış durumda.', 'email-verification-forms'),
                        '<strong>' . esc_html($email) . '</strong>');
                } else {
                    esc_html_e('Bu e-posta adresi zaten doğrulanmış durumda.', 'email-verification-forms');
                }
                ?>
            </p>
        </div>

        <!-- Success Benefits -->
        <div class="evf-benefits-section">
            <h3 class="evf-benefits-title"><?php esc_html_e('🎉 Hesabınız Aktif!', 'email-verification-forms'); ?></h3>
            <div class="evf-benefits-grid">
                <div class="evf-benefit-item">
                    <div class="evf-benefit-icon">🛡️</div>
                    <div class="evf-benefit-text"><?php esc_html_e('Güvenlik aktif', 'email-verification-forms'); ?></div>
                </div>
                <div class="evf-benefit-item">
                    <div class="evf-benefit-icon">📧</div>
                    <div class="evf-benefit-text"><?php esc_html_e('E-posta bildirimleri', 'email-verification-forms'); ?></div>
                </div>
                <div class="evf-benefit-item">
                    <div class="evf-benefit-icon">⚡</div>
                    <div class="evf-benefit-text"><?php esc_html_e('Tüm özellikler', 'email-verification-forms'); ?></div>
                </div>
                <div class="evf-benefit-item">
                    <div class="evf-benefit-icon">🎯</div>
                    <div class="evf-benefit-text"><?php esc_html_e('Tam erişim', 'email-verification-forms'); ?></div>
                </div>
            </div>
        </div>

        <!-- Account Actions -->
        <div class="evf-actions-section">
            <?php if (evf_is_woocommerce_active()): ?>
                <!-- WooCommerce Actions -->
                <div class="evf-action-buttons">
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"
                       class="evf-btn evf-btn-primary evf-btn-full">
                        🏪 <?php esc_html_e('Hesabıma Git', 'email-verification-forms'); ?>
                    </a>

                    <div class="evf-action-row">
                        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
                           class="evf-btn evf-btn-secondary">
                            🛍️ <?php esc_html_e('Alışverişe Başla', 'email-verification-forms'); ?>
                        </a>
                        <a href="<?php echo esc_url(wc_get_page_permalink('cart')); ?>"
                           class="evf-btn evf-btn-secondary">
                            🛒 <?php esc_html_e('Sepetim', 'email-verification-forms'); ?>
                        </a>
                    </div>
                </div>

                <!-- WooCommerce User Info -->
                <?php if (is_user_logged_in()): ?>
                    <div class="evf-user-info">
                        <div class="evf-user-avatar">
                            <?php echo get_avatar(get_current_user_id(), 60); ?>
                        </div>
                        <div class="evf-user-details">
                            <div class="evf-user-name"><?php echo esc_html(wp_get_current_user()->display_name); ?></div>
                            <div class="evf-user-email"><?php echo esc_html(wp_get_current_user()->user_email); ?></div>
                            <div class="evf-user-status">
                                <span class="evf-status-badge verified">✓ <?php esc_html_e('Doğrulanmış', 'email-verification-forms'); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- WordPress Actions -->
                <div class="evf-action-buttons">
                    <?php if (is_user_logged_in()): ?>
                        <a href="<?php echo esc_url(admin_url('profile.php')); ?>"
                           class="evf-btn evf-btn-primary evf-btn-full">
                            👤 <?php esc_html_e('Profilim', 'email-verification-forms'); ?>
                        </a>

                        <div class="evf-action-row">
                            <a href="<?php echo esc_url(home_url()); ?>"
                               class="evf-btn evf-btn-secondary">
                                🏠 <?php esc_html_e('Ana Sayfa', 'email-verification-forms'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url()); ?>"
                               class="evf-btn evf-btn-secondary">
                                ⚙️ <?php esc_html_e('Yönetim', 'email-verification-forms'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo esc_url(wp_login_url()); ?>"
                           class="evf-btn evf-btn-primary evf-btn-full">
                            🔐 <?php esc_html_e('Giriş Yap', 'email-verification-forms'); ?>
                        </a>

                        <div class="evf-action-row">
                            <a href="<?php echo esc_url(home_url()); ?>"
                               class="evf-btn evf-btn-secondary">
                                🏠 <?php esc_html_e('Ana Sayfa', 'email-verification-forms'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Additional Info -->
        <div class="evf-info-section">
            <div class="evf-info-card">
                <h4 class="evf-info-title"><?php esc_html_e('💡 Bilgi', 'email-verification-forms'); ?></h4>
                <div class="evf-info-content">
                    <p><?php esc_html_e('E-posta adresiniz daha önce başarıyla doğrulanmış. Herhangi bir işlem yapmanıza gerek yok.', 'email-verification-forms'); ?></p>

                    <?php if (evf_is_woocommerce_active()): ?>
                        <p><?php esc_html_e('Artık tüm mağaza özelliklerini kullanabilir, sipariş verebilir ve hesap bilgilerinizi yönetebilirsiniz.', 'email-verification-forms'); ?></p>
                    <?php else: ?>
                        <p><?php esc_html_e('Artık tüm site özelliklerini kullanabilir ve hesap bilgilerinizi yönetebilirsiniz.', 'email-verification-forms'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="evf-help-section">
            <details class="evf-help-details">
                <summary><?php esc_html_e('Sorun mu yaşıyorsunuz?', 'email-verification-forms'); ?></summary>
                <div class="evf-help-content">
                    <div class="evf-help-grid">
                        <div class="evf-help-item">
                            <div class="evf-help-icon">📧</div>
                            <div class="evf-help-text">
                                <strong><?php esc_html_e('E-posta sorunları', 'email-verification-forms'); ?></strong>
                                <p><?php esc_html_e('E-posta alamıyorsanız spam klasörünüzü kontrol edin.', 'email-verification-forms'); ?></p>
                            </div>
                        </div>

                        <div class="evf-help-item">
                            <div class="evf-help-icon">🔐</div>
                            <div class="evf-help-text">
                                <strong><?php esc_html_e('Giriş sorunları', 'email-verification-forms'); ?></strong>
                                <p><?php esc_html_e('Şifrenizi unuttuysanız şifre sıfırlama linkini kullanın.', 'email-verification-forms'); ?></p>
                            </div>
                        </div>

                        <div class="evf-help-item">
                            <div class="evf-help-icon">💬</div>
                            <div class="evf-help-text">
                                <strong><?php esc_html_e('Destek', 'email-verification-forms'); ?></strong>
                                <p><?php esc_html_e('Başka bir sorununuz varsa destek ekibimizle iletişime geçin.', 'email-verification-forms'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        </div>

        <!-- Footer -->
        <div class="evf-footer-section">
            <p class="evf-footer-text">
                <?php
                /* translators: %s: Site name (wrapped in <strong> tags) */
                printf(esc_html__('%s ailesine hoş geldiniz! 🎉', 'email-verification-forms'),
                    '<strong>' . get_bloginfo('name') . '</strong>');
                ?>
            </p>
            <div class="evf-footer-links">
                <a href="<?php echo esc_url(home_url()); ?>" class="evf-footer-link">
                    <?php esc_html_e('Ana Sayfa', 'email-verification-forms'); ?>
                </a>
                <span class="evf-footer-separator">•</span>
                <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" class="evf-footer-link">
                    <?php esc_html_e('Gizlilik', 'email-verification-forms'); ?>
                </a>
                <?php if (evf_is_woocommerce_active()): ?>
                    <span class="evf-footer-separator">•</span>
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="evf-footer-link">
                        <?php esc_html_e('Hesabım', 'email-verification-forms'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .evf-already-verified-page {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #10b981, #059669);
        min-height: 100vh;
    }

    .evf-already-verified-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .evf-already-verified-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        padding: 3rem;
        width: 100%;
        max-width: 600px;
        position: relative;
        overflow: hidden;
    }

    .evf-already-verified-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #10b981, #059669);
    }

    /* Progress Bar */
    .evf-progress-bar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 3rem;
        padding: 0 1rem;
    }

    .evf-progress-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
    }

    .evf-progress-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 20px;
        left: 60%;
        width: 100%;
        height: 3px;
        background: #10b981;
        z-index: 1;
        border-radius: 2px;
    }

    .evf-progress-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
        position: relative;
        z-index: 2;
        background: #10b981;
        color: white;
        border: 3px solid #10b981;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .evf-progress-label {
        margin-top: 0.75rem;
        font-size: 0.8rem;
        color: #10b981;
        text-align: center;
        font-weight: 600;
    }

    /* Success Header */
    .evf-success-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .evf-success-icon {
        margin-bottom: 1.5rem;
    }

    .evf-checkmark {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: bold;
        margin: 0 auto;
        box-shadow: 0 8px 30px rgba(16, 185, 129, 0.4);
        animation: checkmarkPulse 2s ease-in-out infinite;
    }

    @keyframes checkmarkPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .evf-success-title {
        font-size: 2rem;
        font-weight: 700;
        color: #065f46;
        margin: 0 0 1rem 0;
        background: linear-gradient(135deg, #10b981, #059669);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .evf-success-subtitle {
        color: #6b7280;
        font-size: 1.1rem;
        margin: 0;
        line-height: 1.6;
    }

    /* Benefits Section */
    .evf-benefits-section {
        background: linear-gradient(135deg, #ecfdf5, #d1fae5);
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid #a7f3d0;
    }

    .evf-benefits-title {
        color: #065f46;
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0 0 1.5rem 0;
        text-align: center;
    }

    .evf-benefits-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .evf-benefit-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: white;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
    }

    .evf-benefit-icon {
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0fdf4;
        border-radius: 8px;
    }

    .evf-benefit-text {
        color: #374151;
        font-weight: 500;
        font-size: 0.9rem;
    }

    /* Actions Section */
    .evf-actions-section {
        margin-bottom: 2rem;
    }

    .evf-action-buttons {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .evf-action-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    /* Button Styles */
    .evf-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 1rem;
        line-height: 1.5;
    }

    .evf-btn-primary {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.4);
    }

    .evf-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.6);
    }

    .evf-btn-secondary {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .evf-btn-secondary:hover {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }

    .evf-btn-full {
        width: 100%;
    }

    /* User Info */
    .evf-user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: #f8fafc;
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1.5rem;
        border: 1px solid #e2e8f0;
    }

    .evf-user-avatar {
        flex-shrink: 0;
    }

    .evf-user-avatar img {
        border-radius: 50%;
        border: 3px solid #10b981;
    }

    .evf-user-details {
        flex: 1;
    }

    .evf-user-name {
        font-weight: 600;
        color: #1f2937;
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
    }

    .evf-user-email {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .evf-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        background: #dcfce7;
        color: #16a34a;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid #bbf7d0;
    }

    /* Info Section */
    .evf-info-section {
        margin-bottom: 2rem;
    }

    .evf-info-card {
        background: #fffbeb;
        border: 1px solid #fed7aa;
        border-radius: 12px;
        padding: 1.5rem;
    }

    .evf-info-title {
        color: #92400e;
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 1rem 0;
    }

    .evf-info-content p {
        color: #78350f;
        line-height: 1.6;
        margin: 0 0 1rem 0;
    }

    .evf-info-content p:last-child {
        margin-bottom: 0;
    }

    /* Help Section */
    .evf-help-section {
        margin-bottom: 2rem;
    }

    .evf-help-details {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 12px;
        padding: 1.5rem;
    }

    .evf-help-details summary {
        font-weight: 600;
        color: #0369a1;
        cursor: pointer;
        outline: none;
        font-size: 1.1rem;
    }

    .evf-help-details[open] summary {
        margin-bottom: 1.5rem;
    }

    .evf-help-grid {
        display: grid;
        gap: 1rem;
    }

    .evf-help-item {
        display: flex;
        gap: 1rem;
        background: white;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid #e0f2fe;
    }

    .evf-help-icon {
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0f9ff;
        border-radius: 8px;
        flex-shrink: 0;
    }

    .evf-help-text strong {
        color: #0c4a6e;
        display: block;
        margin-bottom: 0.5rem;
    }

    .evf-help-text p {
        color: #0369a1;
        margin: 0;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    /* Footer */
    .evf-footer-section {
        text-align: center;
        padding-top: 2rem;
        border-top: 1px solid #e5e7eb;
    }

    .evf-footer-text {
        color: #6b7280;
        margin: 0 0 1rem 0;
        font-size: 1rem;
    }

    .evf-footer-links {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .evf-footer-link {
        color: #9ca3af;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s ease;
    }

    .evf-footer-link:hover {
        color: #6b7280;
        text-decoration: underline;
    }

    .evf-footer-separator {
        color: #d1d5db;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .evf-already-verified-card {
            padding: 2rem 1.5rem;
            margin: 1rem;
        }

        .evf-benefits-grid {
            grid-template-columns: 1fr;
        }

        .evf-action-row {
            grid-template-columns: 1fr;
        }

        .evf-user-info {
            flex-direction: column;
            text-align: center;
        }

        .evf-help-grid {
            gap: 0.75rem;
        }

        .evf-help-item {
            flex-direction: column;
            text-align: center;
        }

        .evf-success-title {
            font-size: 1.5rem;
        }

        .evf-footer-links {
            flex-direction: column;
            gap: 0.25rem;
        }

        .evf-footer-separator {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .evf-already-verified-wrapper {
            padding: 1rem 0.5rem;
        }

        .evf-already-verified-card {
            padding: 1.5rem 1rem;
        }

        .evf-checkmark {
            width: 60px;
            height: 60px;
            font-size: 2rem;
        }

        .evf-progress-bar {
            margin-bottom: 2rem;
        }

        .evf-progress-circle {
            width: 35px;
            height: 35px;
            font-size: 0.9rem;
        }

        .evf-benefits-section {
            padding: 1.5rem;
        }
    }

    /* Animation */
    .evf-fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('EVF Already Verified: Page loaded');

        // Auto-redirect after timeout (optional)
        const autoRedirectDelay = <?php echo (int) get_option('evf_auto_redirect_delay', 0); ?>; // seconds, 0 = disabled

        if (autoRedirectDelay > 0) {
            console.log('EVF: Auto-redirect enabled, waiting', autoRedirectDelay, 'seconds');

            setTimeout(() => {
                <?php if (evf_is_woocommerce_active()): ?>
                window.location.href = '<?php echo esc_js(wc_get_page_permalink('myaccount')); ?>';
                <?php else: ?>
                window.location.href = '<?php echo esc_js(home_url()); ?>';
                <?php endif; ?>
            }, autoRedirectDelay * 1000);
        }

        // Add celebration effect (optional)
        const celebrateVerification = <?php echo get_option('evf_celebrate_verification', true) ? 'true' : 'false'; ?>;

        if (celebrateVerification) {
            // Simple confetti effect with emojis
            setTimeout(() => {
                createCelebration();
            }, 500);
        }

        function createCelebration() {
            const emojis = ['🎉', '✨', '🎊', '🌟', '💫'];
            const container = document.body;

            for (let i = 0; i < 20; i++) {
                setTimeout(() => {
                    const emoji = document.createElement('div');
                    emoji.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                    emoji.style.cssText = `
                        position: fixed;
                        top: -20px;
                        left: ${Math.random() * 100}%;
                        font-size: 2rem;
                        pointer-events: none;
                        z-index: 9999;
                        animation: fall 3s ease-in forwards;
                    `;

                    container.appendChild(emoji);

                    setTimeout(() => {
                        emoji.remove();
                    }, 3000);
                }, i * 100);
            }
        }

        // Add CSS for falling animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    });
</script>

<?php wp_footer(); ?>
</body>
</html>