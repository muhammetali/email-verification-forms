<?php
/**
 * Password Setup Template
 * Parola belirleme sayfası
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get document structure
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Parola Belirleyin', 'email-verification-forms'); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="evf-password-setup-page">

<div class="evf-password-setup-wrapper">
    <div class="evf-password-setup-card evf-fade-in">
        <!-- Progress Bar -->
        <div class="evf-progress-bar">
            <div class="evf-progress-step completed">
                <div class="evf-progress-circle">✓</div>
                <div class="evf-progress-label"><?php _e('E-posta', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step completed">
                <div class="evf-progress-circle">✓</div>
                <div class="evf-progress-label"><?php _e('Doğrulama', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step active">
                <div class="evf-progress-circle">3</div>
                <div class="evf-progress-label"><?php _e('Parola', 'email-verification-forms'); ?></div>
            </div>
        </div>

        <!-- Form Header -->
        <div class="evf-form-header">
            <h1 class="evf-form-title"><?php _e('Parolanızı Belirleyin', 'email-verification-forms'); ?></h1>
            <p class="evf-form-subtitle">
                <?php
                /* translators: %s: User email address (wrapped in <strong> tags) */
                printf(
                    __('E-posta adresiniz doğrulandı: %s<br>Şimdi hesabınız için güvenli bir parola oluşturun.', 'email-verification-forms'),
                    '<strong>' . esc_html($email) . '</strong>'
                ); ?>
            </p>
        </div>

        <!-- Message Container -->
        <div class="evf-message" id="evf-message" role="alert" aria-live="polite"></div>

        <!-- Password Setup Form -->
        <form class="evf-password-setup-form" id="evf-password-setup-form" novalidate>
            <?php wp_nonce_field('evf_nonce', 'evf_nonce', false); ?>
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

            <div class="evf-form-group">
                <label for="evf-password" class="evf-label">
                    <?php _e('Parola', 'email-verification-forms'); ?>
                    <span class="required" aria-label="<?php _e('zorunlu', 'email-verification-forms'); ?>">*</span>
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
                            minlength="<?php echo esc_attr(get_option('evf_min_password_length', 8)); ?>"
                    >
                    <button type="button" class="evf-password-toggle" aria-label="<?php _e('Parolayı göster/gizle', 'email-verification-forms'); ?>">
                        <span class="evf-eye-icon">👁️</span>
                    </button>
                </div>
                <div id="evf-password-help" class="evf-help-text">
                    <?php
                    /* translators: %d: Minimum password length from settings */
                    printf(
                        __('En az %d karakter uzunluğunda olmalıdır.', 'email-verification-forms'),
                        get_option('evf_min_password_length', 8)
                    ); ?>
                    <?php if (get_option('evf_require_strong_password', true)): ?>
                        <?php _e('Büyük harf, küçük harf ve rakam içermelidir.', 'email-verification-forms'); ?>
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
                    <?php _e('Parola Tekrar', 'email-verification-forms'); ?>
                    <span class="required" aria-label="<?php _e('zorunlu', 'email-verification-forms'); ?>">*</span>
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
                    <button type="button" class="evf-password-toggle" aria-label="<?php _e('Parolayı göster/gizle', 'email-verification-forms'); ?>">
                        <span class="evf-eye-icon">👁️</span>
                    </button>
                </div>
                <div id="evf-password-confirm-help" class="evf-help-text">
                    <?php _e('Parolanızı tekrar girin.', 'email-verification-forms'); ?>
                </div>
            </div>

            <!-- Password Requirements -->
            <div class="evf-password-requirements">
                <h3><?php _e('Parola Gereksinimleri:', 'email-verification-forms'); ?></h3>
                <ul class="evf-requirements-list">
                    <li class="evf-requirement" data-requirement="length">
                        <span class="evf-requirement-icon">○</span>
                        <?php
                        /* translators: %d: Minimum password length from settings */
                        printf(__('En az %d karakter', 'email-verification-forms'), get_option('evf_min_password_length', 8));
                        ?>
                    </li>
                    <?php if (get_option('evf_require_strong_password', true)): ?>
                        <li class="evf-requirement" data-requirement="lowercase">
                            <span class="evf-requirement-icon">○</span>
                            <?php _e('En az bir küçük harf', 'email-verification-forms'); ?>
                        </li>
                        <li class="evf-requirement" data-requirement="uppercase">
                            <span class="evf-requirement-icon">○</span>
                            <?php _e('En az bir büyük harf', 'email-verification-forms'); ?>
                        </li>
                        <li class="evf-requirement" data-requirement="number">
                            <span class="evf-requirement-icon">○</span>
                            <?php _e('En az bir rakam', 'email-verification-forms'); ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="evf-form-group">
                <button type="submit" class="evf-btn evf-btn-primary evf-btn-full evf-submit-btn">
                    <span class="evf-btn-text"><?php _e('Hesabımı Oluştur', 'email-verification-forms'); ?></span>
                    <span class="evf-btn-loading" style="display: none;">
                        <span class="evf-spinner"></span>
                        <?php _e('Hesap oluşturuluyor...', 'email-verification-forms'); ?>
                    </span>
                </button>
            </div>
        </form>

        <!-- Security Info -->
        <div class="evf-security-info">
            <div class="evf-security-icon">🔒</div>
            <p>
                <?php _e('Bilgileriniz SSL ile şifrelenerek güvenli bir şekilde işlenir. Parolanız güvenli bir şekilde saklanır.', 'email-verification-forms'); ?>
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
        color: var(--evf-gray-400);
        transition: var(--evf-transition);
    }

    .evf-password-toggle:hover {
        color: var(--evf-gray-600);
    }

    .evf-password-toggle:focus {
        outline: 2px solid var(--evf-primary);
        outline-offset: 2px;
        border-radius: 4px;
    }

    .evf-password-requirements {
        background: var(--evf-gray-50);
        border-radius: var(--evf-border-radius);
        padding: 1.5rem;
        margin: 1.5rem 0;
    }

    .evf-password-requirements h3 {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--evf-gray-800);
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
        color: var(--evf-gray-600);
        margin-bottom: 0.5rem;
        transition: var(--evf-transition);
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
    }

    .evf-requirement.met {
        color: var(--evf-success);
    }

    .evf-requirement.met .evf-requirement-icon {
        color: var(--evf-success);
    }

    .evf-requirement.met .evf-requirement-icon::before {
        content: '✓';
    }

    .evf-security-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: var(--evf-gray-50);
        border-radius: var(--evf-border-radius);
        padding: 1rem;
        margin-top: 2rem;
        border: 1px solid var(--evf-gray-200);
    }

    .evf-security-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .evf-security-info p {
        font-size: 0.875rem;
        color: var(--evf-gray-600);
        margin: 0;
        line-height: 1.5;
    }

    @media (max-width: 640px) {
        .evf-password-requirements {
            padding: 1rem;
        }

        .evf-security-info {
            flex-direction: column;
            text-align: center;
            gap: 0.5rem;
        }
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Password toggle functionality
        $('.evf-password-toggle').on('click', function(e) {
            e.preventDefault();

            const $toggle = $(this);
            const $input = $toggle.siblings('input');
            const $icon = $toggle.find('.evf-eye-icon');

            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.text('🙈');
            } else {
                $input.attr('type', 'password');
                $icon.text('👁️');
            }
        });

        // Password requirements validation
        function checkPasswordRequirements(password) {
            const requirements = {
                length: password.length >= <?php echo get_option('evf_min_password_length', 8); ?>,
                <?php if (get_option('evf_require_strong_password', true)): ?>
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                number: /[0-9]/.test(password)
                <?php endif; ?>
            };

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

        // Password input validation
        $('.evf-password-input').on('input', function() {
            const password = $(this).val();
            const meetsRequirements = checkPasswordRequirements(password);

            $(this).removeClass('error success');
            if (password.length > 0) {
                if (meetsRequirements) {
                    $(this).addClass('success');
                } else {
                    $(this).addClass('error');
                }
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

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Alt + S to submit form
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                $('#evf-password-setup-form').submit();
            }
        });
    });
</script>

<?php wp_footer(); ?>

</body>
</html>