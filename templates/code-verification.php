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

<style>
    /* CSS düzenlemeleri */
    .evf-message {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        font-weight: 500;
        display: none;
    }

    .evf-message.show {
        display: block;
        animation: slideDown 0.3s ease-out;
    }

    .evf-message.success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .evf-message.error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .evf-message.warning {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .evf-code-verification-page {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea, #764ba2);
        min-height: 100vh;
    }

    .evf-code-verification-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .evf-code-verification-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        padding: 2.5rem;
        width: 100%;
        max-width: 450px;
        position: relative;
        overflow: hidden;
    }

    .evf-code-verification-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .evf-code-icon {
        font-size: 3rem;
        text-align: center;
        margin-bottom: 1rem;
    }

    .evf-code-input-wrapper {
        position: relative;
    }

    .evf-code-input {
        width: 100%;
        padding: 1rem;
        font-size: 1.5rem;
        text-align: center;
        letter-spacing: 0.5rem;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
        transition: all 0.2s ease;
        font-family: 'Monaco', 'Consolas', monospace;
    }

    .evf-code-input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .evf-code-input-help {
        text-align: center;
        font-size: 0.875rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }

    .evf-resend-section {
        text-align: center;
        margin: 2rem 0;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .evf-resend-text {
        margin: 0 0 1rem 0;
        color: #6b7280;
        font-size: 0.9rem;
    }

    .evf-resend-btn {
        min-width: 200px;
        position: relative;
    }

    .evf-resend-countdown {
        color: #f59e0b;
        font-weight: 600;
    }

    .evf-resend-loading {
        color: #6b7280;
    }

    .evf-spinner-small {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #e5e7eb;
        border-top: 2px solid #6b7280;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }

    .evf-help-section {
        margin: 2rem 0;
    }

    .evf-help-details {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 8px;
        padding: 1rem;
    }

    .evf-help-details summary {
        font-weight: 600;
        color: #0369a1;
        cursor: pointer;
        outline: none;
    }

    .evf-help-details[open] summary {
        margin-bottom: 1rem;
    }

    .evf-help-content ul {
        margin: 0;
        padding-left: 1.25rem;
        color: #0c4a6e;
    }

    .evf-help-content li {
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }

    .evf-back-section {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .evf-back-link {
        color: #6b7280;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.2s ease;
    }

    .evf-back-link:hover {
        color: #374151;
        text-decoration: underline;
    }

    /* Button styles */
    .evf-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 1rem;
        line-height: 1.5;
    }

    .evf-btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 4px 14px 0 rgba(102, 126, 234, 0.4);
    }

    .evf-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px 0 rgba(102, 126, 234, 0.6);
    }

    .evf-btn-secondary {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .evf-btn-secondary:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .evf-btn-full {
        width: 100%;
    }

    .evf-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    .evf-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 640px) {
        .evf-code-verification-card {
            padding: 2rem 1.5rem;
            margin: 1rem;
        }

        .evf-code-input {
            font-size: 1.25rem;
            letter-spacing: 0.25rem;
        }

        .evf-resend-section {
            padding: 1rem;
        }
    }

    /* Animation */
    .evf-fade-in {
        animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Code input animation */
    .evf-code-input.success {
        border-color: #10b981;
        background: #ecfdf5;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .evf-code-input.error {
        border-color: #ef4444;
        background: #fef2f2;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    /* Progress bar styles */
    .evf-progress-bar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
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
        height: 2px;
        background: #e5e7eb;
        z-index: 1;
    }

    .evf-progress-step.completed:not(:last-child)::after {
        background: #10b981;
    }

    .evf-progress-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
        position: relative;
        z-index: 2;
        background: #f3f4f6;
        color: #6b7280;
        border: 2px solid #e5e7eb;
    }

    .evf-progress-step.completed .evf-progress-circle {
        background: #10b981;
        color: white;
        border-color: #10b981;
    }

    .evf-progress-step.active .evf-progress-circle {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .evf-progress-label {
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: #6b7280;
        text-align: center;
    }

    .evf-progress-step.completed .evf-progress-label,
    .evf-progress-step.active .evf-progress-label {
        color: #374151;
        font-weight: 500;
    }

    /* Form styles */
    .evf-form-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .evf-form-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 1rem 0;
    }

    .evf-form-subtitle {
        color: #6b7280;
        margin: 0;
        line-height: 1.5;
    }

    .evf-form-group {
        margin-bottom: 1.5rem;
    }

    .evf-label {
        display: block;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .evf-label .required {
        color: #ef4444;
        margin-left: 0.25rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('EVF Code Verification: Page loaded');

        // EVF namespace
        window.EVF = window.EVF || {};

        // Message handler - GÜÇLENDIRILMIŞ
        EVF.showMessage = function(type, message) {
            const messageContainer = document.getElementById('evf-message');

            if (!messageContainer) {
                console.error('EVF: Message container not found');
                return;
            }

            console.log('EVF: Showing message -', type, ':', message);

            // Mesaj türüne göre sınıf belirle
            messageContainer.className = 'evf-message ' + type + ' show';
            messageContainer.innerHTML = message;

            // Scroll to message
            messageContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Otomatik gizleme (başarı mesajları için)
            if (type === 'success') {
                setTimeout(function() {
                    messageContainer.classList.remove('show');
                }, 5000);
            }
        };

        // DÜZELTME: Config değerleri - PHP'den doğru şekilde al
        const config = {
            resendInterval: <?php echo (int) get_option('evf_code_resend_interval', 2) * 60; ?>, // dakika -> saniye
            maxAttempts: <?php echo (int) get_option('evf_max_code_attempts', 5); ?>,
            email: <?php echo wp_json_encode($email); ?>,
            ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('evf_nonce')); ?>,
            redirectUrl: <?php
            if (evf_is_woocommerce_active()) {
                echo wp_json_encode(wc_get_page_permalink('myaccount'));
            } else {
                echo wp_json_encode(home_url('/login'));
            }
            ?>
        };

        console.log('EVF Config:', config);

        let countdownTimer = null;
        let attempts = 0;

        // DÜZELTME: Countdown function - Net çalışacak
        function startCountdown(seconds) {
            const btn = document.getElementById('evf-resend-code');
            const timer = document.getElementById('countdown-timer');
            const resendText = btn.querySelector('.evf-resend-text');
            const resendCountdown = btn.querySelector('.evf-resend-countdown');

            if (!btn || !timer || !resendText || !resendCountdown) {
                console.error('EVF: Countdown elements not found');
                return;
            }

            let remaining = Math.floor(seconds);
            console.log('EVF: Starting countdown:', remaining, 'seconds');

            btn.disabled = true;
            resendText.style.display = 'none';
            resendCountdown.style.display = 'inline';

            if (countdownTimer) {
                clearInterval(countdownTimer);
            }

            // İlk değeri hemen set et
            timer.textContent = remaining;

            countdownTimer = setInterval(() => {
                remaining--;
                timer.textContent = remaining;

                if (remaining <= 0) {
                    clearInterval(countdownTimer);
                    btn.disabled = false;
                    resendText.style.display = 'inline';
                    resendCountdown.style.display = 'none';
                    console.log('EVF: Countdown finished');
                }
            }, 1000);
        }

        // DÜZELTME: Code input formatting - SADECE RAKAM
        const codeInput = document.getElementById('evf-verification-code');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, ''); // Sadece rakam
                if (value.length > 6) value = value.substr(0, 6);
                this.value = value;

                // Reset error state
                this.classList.remove('error', 'success');

                // Auto-submit when 6 digits entered
                if (value.length === 6) {
                    console.log('EVF: 6 digits entered, auto-submitting...');
                    setTimeout(() => {
                        document.getElementById('evf-code-verification-form').dispatchEvent(new Event('submit'));
                    }, 500);
                }
            });

            // Focus input on load
            codeInput.focus();
        }

        // DÜZELTME: Form submission - ERROR HANDLING GÜÇLENDİRİLDİ
        const form = document.getElementById('evf-code-verification-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('EVF: Form submitted');

                const submitBtn = form.querySelector('.evf-submit-btn');
                const code = codeInput.value.trim();

                // Input validation
                if (code.length !== 6) {
                    console.log('EVF: Invalid code length:', code.length);
                    codeInput.classList.add('error');
                    codeInput.focus();
                    EVF.showMessage('error', '<?php esc_html_e('Lütfen 6 haneli kodu girin.', 'email-verification-forms'); ?>');
                    return;
                }

                if (!/^[0-9]{6}$/.test(code)) {
                    console.log('EVF: Invalid code format:', code);
                    codeInput.classList.add('error');
                    codeInput.focus();
                    EVF.showMessage('error', '<?php esc_html_e('Kod sadece rakamlardan oluşmalıdır.', 'email-verification-forms'); ?>');
                    return;
                }

                attempts++;
                console.log('EVF: Attempt', attempts, 'of', config.maxAttempts);

                // Set loading state
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const btnText = submitBtn.querySelector('.evf-btn-text');
                    const btnLoading = submitBtn.querySelector('.evf-btn-loading');
                    if (btnText) btnText.style.display = 'none';
                    if (btnLoading) btnLoading.style.display = 'inline';
                }

                // AJAX request
                console.log('EVF: Sending AJAX request...');

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'evf_verify_code',
                        nonce: config.nonce,
                        email: config.email,
                        verification_code: code
                    })
                })
                    .then(response => {
                        console.log('EVF: AJAX response received:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('EVF: AJAX data:', data);

                        if (data.success) {
                            codeInput.classList.remove('error');
                            codeInput.classList.add('success');
                            EVF.showMessage('success', '<?php esc_html_e('Kod doğrulandı! Yönlendiriliyor...', 'email-verification-forms'); ?>');

                            setTimeout(() => {
                                const redirectUrl = data.data?.redirect_url || config.redirectUrl;
                                console.log('EVF: Redirecting to:', redirectUrl);
                                window.location.href = redirectUrl;
                            }, 2000);
                        } else {
                            codeInput.classList.remove('success');
                            codeInput.classList.add('error');
                            codeInput.focus();

                            let errorMsg = '<?php esc_html_e('Geçersiz kod. Lütfen tekrar deneyin.', 'email-verification-forms'); ?>';

                            if (data.data === 'code_expired') {
                                errorMsg = '<?php esc_html_e('Kodun süresi dolmuş. Lütfen yeni kod isteyin.', 'email-verification-forms'); ?>';
                                // Show resend button
                                const resendSection = document.querySelector('.evf-resend-section');
                                if (resendSection) {
                                    resendSection.style.display = 'block';
                                }
                            } else if (data.data === 'max_attempts') {
                                errorMsg = '<?php esc_html_e('Çok fazla yanlış deneme. Kayıt işleminiz iptal edildi.', 'email-verification-forms'); ?>';
                                setTimeout(() => {
                                    <?php if (evf_is_woocommerce_active()): ?>
                                    window.location.href = '<?php echo esc_js(wc_get_page_permalink('myaccount') . '?action=register'); ?>';
                                    <?php else: ?>
                                    window.location.href = '<?php echo esc_js(wp_registration_url()); ?>';
                                    <?php endif; ?>
                                }, 3000);
                            } else if (data.data === 'registration_not_found') {
                                errorMsg = '<?php esc_html_e('Kayıt bulunamadı. Lütfen tekrar kayıt olun.', 'email-verification-forms'); ?>';
                            }

                            EVF.showMessage('error', errorMsg);
                        }
                    })
                    .catch(error => {
                        console.error('EVF: AJAX error:', error);
                        codeInput.classList.add('error');
                        EVF.showMessage('error', '<?php esc_html_e('Bir hata oluştu. Lütfen tekrar deneyin.', 'email-verification-forms'); ?>');
                    })
                    .finally(() => {
                        // Reset loading state
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            const btnText = submitBtn.querySelector('.evf-btn-text');
                            const btnLoading = submitBtn.querySelector('.evf-btn-loading');
                            if (btnText) btnText.style.display = 'inline';
                            if (btnLoading) btnLoading.style.display = 'none';
                        }
                    });
            });
        }

        // DÜZELTME: Resend code - LOADING STATE + ERROR HANDLING
        const resendBtn = document.getElementById('evf-resend-code');
        if (resendBtn) {
            resendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('EVF: Resend button clicked');

                const resendText = this.querySelector('.evf-resend-text');
                const resendLoading = this.querySelector('.evf-resend-loading');

                // Set loading state
                this.disabled = true;
                if (resendText) resendText.style.display = 'none';
                if (resendLoading) resendLoading.style.display = 'inline';

                fetch(config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'evf_resend_code',
                        nonce: config.nonce,
                        email: config.email
                    })
                })
                    .then(response => {
                        console.log('EVF: Resend response received:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('EVF: Resend data:', data);

                        if (data.success) {
                            EVF.showMessage('success', '<?php esc_html_e('Yeni doğrulama kodu gönderildi!', 'email-verification-forms'); ?>');
                            startCountdown(config.resendInterval);
                        } else {
                            let errorMsg = '<?php esc_html_e('Kod gönderilemedi. Lütfen tekrar deneyin.', 'email-verification-forms'); ?>';

                            if (data.data === 'rate_limit') {
                                errorMsg = '<?php esc_html_e('Çok hızlı kod istiyorsunuz. Lütfen bekleyin.', 'email-verification-forms'); ?>';
                            } else if (data.data === 'registration_not_found') {
                                errorMsg = '<?php esc_html_e('Kayıt bulunamadı. Lütfen tekrar kayıt olun.', 'email-verification-forms'); ?>';
                            }

                            EVF.showMessage('error', errorMsg);
                        }
                    })
                    .catch(error => {
                        console.error('EVF: Resend error:', error);
                        EVF.showMessage('error', '<?php esc_html_e('Bir hata oluştu.', 'email-verification-forms'); ?>');
                    })
                    .finally(() => {
                        // Reset loading state if not in countdown
                        if (!countdownTimer) {
                            this.disabled = false;
                            if (resendText) resendText.style.display = 'inline';
                            if (resendLoading) resendLoading.style.display = 'none';
                        }
                    });
            });
        }

        // DÜZELTME: Initial countdown kontrolü - PHP'den veri al
        <?php if (isset($last_code_sent) && $last_code_sent): ?>
        const lastSentTime = <?php echo strtotime($last_code_sent); ?>;
        const currentTime = <?php echo time(); ?>;
        const elapsedSeconds = currentTime - lastSentTime;

        console.log('EVF: Last sent:', new Date(lastSentTime * 1000));
        console.log('EVF: Current time:', new Date(currentTime * 1000));
        console.log('EVF: Elapsed seconds:', elapsedSeconds);
        console.log('EVF: Resend interval:', config.resendInterval);

        if (elapsedSeconds < config.resendInterval) {
            const remainingSeconds = config.resendInterval - elapsedSeconds;
            console.log('EVF: Starting initial countdown:', remainingSeconds, 'seconds');
            startCountdown(remainingSeconds);
        } else {
            console.log('EVF: No countdown needed - enough time has passed');
        }
        <?php else: ?>
        console.log('EVF: No last_code_sent data available');
        <?php endif; ?>
    });
</script>

<?php wp_footer(); ?>
</body>
</html>