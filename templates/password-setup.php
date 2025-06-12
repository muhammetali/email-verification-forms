<?php
/**
 * Password Setup Template
 * Parola belirleme sayfası
 */

if (!defined('ABSPATH')) {
    exit;
}

// Template variables
$min_password_length = (int) get_option('evf_min_password_length', 8);
$require_strong_password = (bool) get_option('evf_require_strong_password', true);
$site_name = get_bloginfo('name');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e('Parola Belirleyin', 'email-verification-forms'); ?> - <?php echo esc_html($site_name); ?></title>
    <?php wp_head(); ?>
</head>
<body class="evf-password-setup-page">

<div class="evf-password-setup-wrapper">
    <div class="evf-password-setup-card evf-fade-in">
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
            <div class="evf-progress-step active">
                <div class="evf-progress-circle">3</div>
                <div class="evf-progress-label"><?php esc_html_e('Parola', 'email-verification-forms'); ?></div>
            </div>
        </div>

        <!-- Form Header -->
        <div class="evf-form-header">
            <h1 class="evf-form-title"><?php esc_html_e('Parolanızı Belirleyin', 'email-verification-forms'); ?></h1>
            <p class="evf-form-subtitle">
                <?php
                if (isset($email) && !empty($email)) {
                    /* translators: %s: User email address (wrapped in <strong> tags) */
                    printf(
                        esc_html__('E-posta adresiniz doğrulandı: %s<br>Şimdi hesabınız için güvenli bir parola oluşturun.', 'email-verification-forms'),
                        '<strong>' . esc_html($email) . '</strong>'
                    );
                } else {
                    esc_html_e('Hesabınız için güvenli bir parola oluşturun.', 'email-verification-forms');
                }
                ?>
            </p>
        </div>

        <!-- Message Container -->
        <div class="evf-message" id="evf-message" role="alert" aria-live="polite"></div>

        <!-- Password Setup Form -->
        <form class="evf-password-setup-form" id="evf-password-setup-form" novalidate>
            <?php wp_nonce_field('evf_nonce', 'evf_nonce', false); ?>
            <?php if (isset($token)): ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <?php endif; ?>

            <div class="evf-form-group">
                <label for="evf-password" class="evf-label">
                    <?php esc_html_e('Parola', 'email-verification-forms'); ?>
                    <span class="required" aria-label="<?php esc_attr_e('zorunlu', 'email-verification-forms'); ?>">*</span>
                </label>
                <div class="evf-password-wrapper">
                    <input
                            type="password"
                            id="evf-password"
                            name="password"
                            class="evf-input evf-password-input"
                            required
                            autocomplete="new-password"
                            aria-describedby="evf-password-help evf-password-strength"
                            minlength="<?php echo esc_attr($min_password_length); ?>"
                    >
                    <button type="button" class="evf-password-toggle" aria-label="<?php esc_attr_e('Parolayı göster/gizle', 'email-verification-forms'); ?>">
                        <span class="evf-eye-icon">👁️</span>
                    </button>
                </div>
                <div id="evf-password-help" class="evf-help-text">
                    <?php
                    /* translators: %d: Minimum password length from settings */
                    printf(
                        esc_html__('En az %d karakter uzunluğunda olmalıdır.', 'email-verification-forms'),
                        esc_html($min_password_length)
                    ); ?>
                    <?php if ($require_strong_password): ?>
                        <?php esc_html_e('Büyük harf, küçük harf ve rakam içermelidir.', 'email-verification-forms'); ?>
                    <?php endif; ?>
                </div>

                <!-- Password Strength Meter -->
                <div class="evf-password-strength" id="evf-password-strength" style="display: none;">
                    <div class="evf-strength-bar">
                        <div class="evf-strength-fill"></div>
                    </div>
                    <div class="evf-strength-text"></div>
                </div>
            </div>

            <div class="evf-form-group">
                <label for="evf-password-confirm" class="evf-label">
                    <?php esc_html_e('Parola Tekrar', 'email-verification-forms'); ?>
                    <span class="required" aria-label="<?php esc_attr_e('zorunlu', 'email-verification-forms'); ?>">*</span>
                </label>
                <div class="evf-password-wrapper">
                    <input
                            type="password"
                            id="evf-password-confirm"
                            name="password_confirm"
                            class="evf-input evf-password-confirm"
                            required
                            autocomplete="new-password"
                            aria-describedby="evf-password-confirm-help"
                    >
                    <button type="button" class="evf-password-toggle" aria-label="<?php esc_attr_e('Parolayı göster/gizle', 'email-verification-forms'); ?>">
                        <span class="evf-eye-icon">👁️</span>
                    </button>
                </div>
                <div id="evf-password-confirm-help" class="evf-help-text">
                    <?php esc_html_e('Parolanızı tekrar girin.', 'email-verification-forms'); ?>
                </div>
            </div>

            <!-- Password Requirements -->
            <div class="evf-password-requirements">
                <h3><?php esc_html_e('Parola Gereksinimleri:', 'email-verification-forms'); ?></h3>
                <ul class="evf-requirements-list">
                    <li class="evf-requirement" data-requirement="length">
                        <span class="evf-requirement-icon">○</span>
                        <?php
                        /* translators: %d: Minimum password length from settings */
                        printf(esc_html__('En az %d karakter', 'email-verification-forms'), esc_html($min_password_length));
                        ?>
                    </li>
                    <?php if ($require_strong_password): ?>
                        <li class="evf-requirement" data-requirement="lowercase">
                            <span class="evf-requirement-icon">○</span>
                            <?php esc_html_e('En az bir küçük harf', 'email-verification-forms'); ?>
                        </li>
                        <li class="evf-requirement" data-requirement="uppercase">
                            <span class="evf-requirement-icon">○</span>
                            <?php esc_html_e('En az bir büyük harf', 'email-verification-forms'); ?>
                        </li>
                        <li class="evf-requirement" data-requirement="number">
                            <span class="evf-requirement-icon">○</span>
                            <?php esc_html_e('En az bir rakam', 'email-verification-forms'); ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="evf-form-group">
                <button type="submit" class="evf-btn evf-btn-primary evf-btn-full evf-submit-btn">
                    <span class="evf-btn-text"><?php esc_html_e('Hesabımı Oluştur', 'email-verification-forms'); ?></span>
                    <span class="evf-btn-loading" style="display: none;">
                        <span class="evf-spinner"></span>
                        <?php esc_html_e('Hesap oluşturuluyor...', 'email-verification-forms'); ?>
                    </span>
                </button>
            </div>
        </form>

        <!-- Security Info -->
        <div class="evf-security-info">
            <div class="evf-security-icon">🔒</div>
            <p>
                <?php esc_html_e('Bilgileriniz SSL ile şifrelenerek güvenli bir şekilde işlenir. Parolanız güvenli bir şekilde saklanır.', 'email-verification-forms'); ?>
            </p>
        </div>
    </div>
</div>

<style>
    /* Additional styles for password setup page */
    .evf-password-setup-page {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }

    .evf-password-setup-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .evf-password-setup-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        padding: 3rem 2.5rem;
        width: 100%;
        max-width: 500px;
        position: relative;
        overflow: hidden;
    }

    .evf-password-setup-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .evf-password-wrapper {
        position: relative;
    }

    .evf-password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        font-size: 16px;
        color: var(--evf-gray-400, #9ca3af);
        transition: all 0.2s ease;
        padding: 4px;
        border-radius: 4px;
    }

    .evf-password-toggle:hover {
        color: var(--evf-gray-600, #4b5563);
        background: rgba(0, 0, 0, 0.05);
    }

    .evf-password-toggle:focus {
        outline: 2px solid var(--evf-primary, #3b82f6);
        outline-offset: 2px;
        border-radius: 4px;
    }

    .evf-password-requirements {
        background: var(--evf-gray-50, #f9fafb);
        border-radius: 8px;
        padding: 1.5rem;
        margin: 1.5rem 0;
        border: 1px solid var(--evf-gray-200, #e5e7eb);
    }

    .evf-password-requirements h3 {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--evf-gray-800, #1f2937);
        margin: 0 0 1rem 0;
    }

    .evf-requirements-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .evf-requirement {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: var(--evf-gray-600, #4b5563);
        margin-bottom: 0.5rem;
        transition: all 0.2s ease;
    }

    .evf-requirement:last-child {
        margin-bottom: 0;
    }

    .evf-requirement-icon {
        width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
        border-radius: 50%;
        border: 1px solid currentColor;
    }

    .evf-requirement.met {
        color: var(--evf-success, #10b981);
    }

    .evf-requirement.met .evf-requirement-icon {
        background: var(--evf-success, #10b981);
        color: white;
        border-color: var(--evf-success, #10b981);
    }

    .evf-requirement.met .evf-requirement-icon::before {
        content: '✓';
    }

    .evf-password-strength {
        margin-top: 0.75rem;
    }

    .evf-strength-bar {
        height: 4px;
        background: var(--evf-gray-200, #e5e7eb);
        border-radius: 2px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }

    .evf-strength-fill {
        height: 100%;
        transition: all 0.3s ease;
        width: 0%;
        background: var(--evf-gray-400, #9ca3af);
    }

    .evf-strength-text {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--evf-gray-600, #4b5563);
    }

    .evf-security-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: var(--evf-gray-50, #f9fafb);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 2rem;
        border: 1px solid var(--evf-gray-200, #e5e7eb);
    }

    .evf-security-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .evf-security-info p {
        font-size: 0.875rem;
        color: var(--evf-gray-600, #4b5563);
        margin: 0;
        line-height: 1.5;
    }

    /* Form validation states */
    .evf-input.success {
        border-color: var(--evf-success, #10b981);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .evf-input.error {
        border-color: var(--evf-error, #ef4444);
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    /* Loading spinner */
    .evf-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: evf-spin 0.8s linear infinite;
        margin-right: 8px;
    }

    @keyframes evf-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Fade in animation */
    .evf-fade-in {
        animation: evf-fade-in 0.5s ease-out;
    }

    @keyframes evf-fade-in {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 640px) {
        .evf-password-setup-wrapper {
            padding: 1rem;
        }

        .evf-password-setup-card {
            padding: 2rem 1.5rem;
            margin: 0.5rem;
        }

        .evf-password-requirements {
            padding: 1rem;
        }

        .evf-security-info {
            flex-direction: column;
            text-align: center;
            gap: 0.5rem;
        }

        .evf-form-title {
            font-size: 1.5rem;
        }
    }

    @media (max-width: 480px) {
        .evf-password-setup-card {
            padding: 1.5rem 1rem;
        }

        .evf-form-title {
            font-size: 1.25rem;
        }

        .evf-requirement {
            font-size: 0.8rem;
        }
    }

    /* Print styles */
    @media print {
        .evf-password-setup-wrapper {
            background: white;
            min-height: auto;
        }

        .evf-password-setup-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }

        .evf-btn,
        .evf-password-toggle {
            display: none;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Configuration object for localized strings and settings
        const evfConfig = {
            minPasswordLength: <?php echo wp_json_encode($min_password_length); ?>,
            requireStrongPassword: <?php echo wp_json_encode($require_strong_password); ?>,
            messages: {
                showPassword: <?php echo wp_json_encode(__('Parolayı göster', 'email-verification-forms')); ?>,
                hidePassword: <?php echo wp_json_encode(__('Parolayı gizle', 'email-verification-forms')); ?>,
                strengthLevels: {
                    veryWeak: <?php echo wp_json_encode(__('Çok Zayıf', 'email-verification-forms')); ?>,
                    weak: <?php echo wp_json_encode(__('Zayıf', 'email-verification-forms')); ?>,
                    medium: <?php echo wp_json_encode(__('Orta', 'email-verification-forms')); ?>,
                    strong: <?php echo wp_json_encode(__('Güçlü', 'email-verification-forms')); ?>,
                    veryStrong: <?php echo wp_json_encode(__('Çok Güçlü', 'email-verification-forms')); ?>
                }
            }
        };

        // Password toggle functionality
        $('.evf-password-toggle').on('click', function(e) {
            e.preventDefault();

            const $toggle = $(this);
            const $input = $toggle.siblings('input');
            const $icon = $toggle.find('.evf-eye-icon');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.text('🙈');
                $toggle.attr('aria-label', evfConfig.messages.hidePassword);
            } else {
                $input.attr('type', 'password');
                $icon.text('👁️');
                $toggle.attr('aria-label', evfConfig.messages.showPassword);
            }
        });

        // Password requirements validation
        function checkPasswordRequirements(password) {
            const requirements = {
                length: password.length >= evfConfig.minPasswordLength
            };

            if (evfConfig.requireStrongPassword) {
                requirements.lowercase = /[a-z]/.test(password);
                requirements.uppercase = /[A-Z]/.test(password);
                requirements.number = /[0-9]/.test(password);
            }

            // Update requirement indicators
            Object.keys(requirements).forEach(function(req) {
                const $requirement = $('[data-requirement="' + req + '"]');
                if (requirements[req]) {
                    $requirement.addClass('met');
                } else {
                    $requirement.removeClass('met');
                }
            });

            return Object.values(requirements).every(Boolean);
        }

        // Password strength calculation
        function calculatePasswordStrength(password) {
            let score = 0;
            const checks = {
                length: password.length >= 8,
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            Object.values(checks).forEach(function(check) {
                if (check) score++;
            });

            const strengthLevels = [
                { width: '20%', color: '#ef4444', text: evfConfig.messages.strengthLevels.veryWeak },
                { width: '40%', color: '#f59e0b', text: evfConfig.messages.strengthLevels.weak },
                { width: '60%', color: '#eab308', text: evfConfig.messages.strengthLevels.medium },
                { width: '80%', color: '#22c55e', text: evfConfig.messages.strengthLevels.strong },
                { width: '100%', color: '#16a34a', text: evfConfig.messages.strengthLevels.veryStrong }
            ];

            return strengthLevels[Math.min(score, 4)] || strengthLevels[0];
        }

        // Password input validation
        $('.evf-password-input').on('input', function() {
            const password = $(this).val();
            const meetsRequirements = checkPasswordRequirements(password);
            const $strengthDiv = $('#evf-password-strength');
            const $strengthFill = $('.evf-strength-fill');
            const $strengthText = $('.evf-strength-text');

            // Update input styling
            $(this).removeClass('error success');
            if (password.length > 0) {
                if (meetsRequirements) {
                    $(this).addClass('success');
                } else {
                    $(this).addClass('error');
                }

                // Show and update strength meter
                $strengthDiv.show();
                const strength = calculatePasswordStrength(password);
                $strengthFill.css({
                    'width': strength.width,
                    'background-color': strength.color
                });
                $strengthText.text(strength.text).css('color', strength.color);
            } else {
                $strengthDiv.hide();
            }
        });

        // Password confirmation validation
        $('.evf-password-confirm').on('input', function() {
            const password = $('.evf-password-input').val();
            const confirmPassword = $(this).val();

            $(this).removeClass('error success');
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    $(this).addClass('success');
                } else {
                    $(this).addClass('error');
                }
            }
        });

        // Auto-focus password input
        $('.evf-password-input').focus();

        // Enhanced form validation
        $('#evf-password-setup-form').on('submit', function(e) {
            const password = $('.evf-password-input').val();
            const confirmPassword = $('.evf-password-confirm').val();

            let isValid = true;

            // Reset previous states
            $('.evf-input').removeClass('error');

            if (!password) {
                $('.evf-password-input').addClass('error').focus();
                isValid = false;
            } else if (!checkPasswordRequirements(password)) {
                $('.evf-password-input').addClass('error').focus();
                isValid = false;
            }

            if (!confirmPassword) {
                $('.evf-password-confirm').addClass('error');
                if (isValid) $('.evf-password-confirm').focus();
                isValid = false;
            } else if (password !== confirmPassword) {
                $('.evf-password-confirm').addClass('error');
                if (isValid) $('.evf-password-confirm').focus();
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                return false;
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Alt + S to submit form
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                $('#evf-password-setup-form').submit();
            }
        });

        // Accessibility improvements
        $('.evf-input').on('focus', function() {
            $(this).closest('.evf-form-group').addClass('focused');
        }).on('blur', function() {
            $(this).closest('.evf-form-group').removeClass('focused');
        });
    });
</script>

<?php wp_footer(); ?>

</body>
</html>