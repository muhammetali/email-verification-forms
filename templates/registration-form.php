<?php
/**
 * Registration Form Template
 * Ana kayıt formu sayfası
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

    <div class="evf-registration-wrapper">
        <div class="evf-registration-card evf-fade-in">
            <!-- Progress Bar -->
            <div class="evf-progress-bar">
                <div class="evf-progress-step active">
                    <div class="evf-progress-circle">1</div>
                    <div class="evf-progress-label"><?php esc_html_e('E-posta', 'email-verification-forms'); ?></div>
                </div>
                <div class="evf-progress-step">
                    <div class="evf-progress-circle">2</div>
                    <div class="evf-progress-label"><?php esc_html_e('Doğrulama', 'email-verification-forms'); ?></div>
                </div>
                <div class="evf-progress-step">
                    <div class="evf-progress-circle">3</div>
                    <div class="evf-progress-label"><?php esc_html_e('Parola', 'email-verification-forms'); ?></div>
                </div>
            </div>

            <!-- Form Header -->
            <div class="evf-form-header">
                <h1 class="evf-form-title"><?php esc_html_e('Hesap Oluştur', 'email-verification-forms'); ?></h1>
                <p class="evf-form-subtitle">
                    <?php esc_html_e('E-posta adresinizi girin ve size gönderilecek doğrulama bağlantısını takip edin.', 'email-verification-forms'); ?>
                </p>
            </div>

            <!-- Message Container -->
            <div class="evf-message" id="evf-message" role="alert" aria-live="polite"></div>

            <!-- Registration Form -->
            <form class="evf-registration-form" id="evf-registration-form" novalidate>
                <?php wp_nonce_field('evf_nonce', 'evf_nonce', false); ?>

                <!-- Step 1: Email Input -->
                <div class="evf-form-step evf-form-step-1">
                    <div class="evf-form-group">
                        <label for="evf-email" class="evf-label">
                            <?php esc_html_e('E-posta Adresi', 'email-verification-forms'); ?>
                            <span class="required" aria-label="<?php esc_attr_e('zorunlu', 'email-verification-forms'); ?>">*</span>
                        </label>
                        <input
                                type="email"
                                id="evf-email"
                                name="email"
                                class="evf-input evf-email-input"
                                required
                                autocomplete="email"
                                placeholder="<?php esc_attr_e('ornek@email.com', 'email-verification-forms'); ?>"
                                aria-describedby="evf-email-help"
                        >
                        <div id="evf-email-help" class="evf-help-text">
                            <?php esc_html_e('Bu e-posta adresine doğrulama bağlantısı gönderilecektir.', 'email-verification-forms'); ?>
                        </div>
                    </div>

                    <div class="evf-form-group">
                        <button type="submit" class="evf-btn evf-btn-primary evf-btn-full evf-submit-btn">
                            <span class="evf-btn-text"><?php esc_html_e('Doğrulama E-postası Gönder', 'email-verification-forms'); ?></span>
                            <span class="evf-btn-loading" style="display: none;">
                            <span class="evf-spinner"></span>
                            <?php esc_html_e('Gönderiliyor...', 'email-verification-forms'); ?>
                        </span>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Email Sent Confirmation -->
                <div class="evf-form-step evf-form-step-2" style="display: none;">
                    <div class="evf-success-content">
                        <div class="evf-success-icon">✉️</div>
                        <h2 class="evf-success-title"><?php esc_html_e('E-posta Gönderildi!', 'email-verification-forms'); ?></h2>
                        <p class="evf-success-message">
                            <?php esc_html_e('E-posta adresinize bir doğrulama bağlantısı gönderdik. Lütfen e-postanızı kontrol edin ve bağlantıya tıklayarak kayıt işleminizi tamamlayın.', 'email-verification-forms'); ?>
                        </p>

                        <div class="evf-email-tips">
                            <h3><?php esc_html_e('E-posta gelmedi mi?', 'email-verification-forms'); ?></h3>
                            <ul>
                                <li><?php esc_html_e('Spam/Junk klasörünüzü kontrol edin', 'email-verification-forms'); ?></li>
                                <li><?php esc_html_e('E-posta adresinizi doğru yazdığınızdan emin olun', 'email-verification-forms'); ?></li>
                                <li><?php esc_html_e('Birkaç dakika bekleyin, e-posta gelmesi zaman alabilir', 'email-verification-forms'); ?></li>
                            </ul>
                        </div>

                        <div class="evf-form-actions">
                            <button type="button" class="evf-btn evf-btn-secondary evf-prev-step" data-step="2">
                                <?php esc_html_e('← Geri Dön', 'email-verification-forms'); ?>
                            </button>
                            <button type="button" class="evf-btn evf-btn-primary evf-resend-email">
                                <?php esc_html_e('Tekrar Gönder', 'email-verification-forms'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Login Link -->
            <div class="evf-form-footer">
                <p class="evf-login-link">
                    <?php esc_html_e('Zaten hesabınız var mı?', 'email-verification-forms'); ?>
                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="evf-link">
                        <?php esc_html_e('Giriş Yapın', 'email-verification-forms'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <style>
        /* Additional styles for this template */
        .evf-success-content {
            text-align: center;
            padding: 20px 0;
        }

        .evf-success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .evf-success-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--evf-gray-900);
            margin: 0 0 1rem 0;
        }

        .evf-success-message {
            color: var(--evf-gray-600);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .evf-email-tips {
            background: var(--evf-gray-50);
            border-radius: var(--evf-border-radius);
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }

        .evf-email-tips h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--evf-gray-800);
            margin: 0 0 1rem 0;
        }

        .evf-email-tips ul {
            margin: 0;
            padding-left: 1.25rem;
            color: var(--evf-gray-600);
        }

        .evf-email-tips li {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .evf-form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .evf-form-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--evf-gray-200);
        }

        .evf-login-link {
            color: var(--evf-gray-600);
            font-size: 0.9rem;
            margin: 0;
        }

        .evf-link {
            color: var(--evf-primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--evf-transition);
        }

        .evf-link:hover {
            color: var(--evf-primary-hover);
            text-decoration: underline;
        }

        .evf-help-text {
            font-size: 0.875rem;
            color: var(--evf-gray-500);
            margin-top: 0.5rem;
            line-height: 1.4;
        }

        .required {
            color: var(--evf-error);
            font-weight: bold;
            margin-left: 2px;
        }

        /* Loading state improvements */
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

        /* Form validation states */
        .evf-input.success {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .evf-input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        /* Accessibility improvements */
        .evf-message[role="alert"] {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: var(--evf-border-radius);
            font-weight: 500;
        }

        .evf-message.success {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .evf-message.error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .evf-message.info {
            background-color: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        /* Focus management */
        .evf-input:focus {
            outline: none;
            border-color: var(--evf-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Button disabled state */
        .evf-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .evf-btn:disabled:hover {
            transform: none;
            box-shadow: var(--evf-shadow-sm);
        }

        @media (max-width: 640px) {
            .evf-form-actions {
                flex-direction: column;
            }

            .evf-email-tips {
                padding: 1rem;
            }

            .evf-success-icon {
                font-size: 3rem;
            }

            .evf-registration-card {
                margin: 1rem;
                padding: 1.5rem;
            }

            .evf-form-title {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 480px) {
            .evf-progress-bar {
                margin-bottom: 1.5rem;
            }

            .evf-progress-step {
                gap: 0.5rem;
            }

            .evf-progress-circle {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }

            .evf-progress-label {
                font-size: 12px;
            }

            .evf-form-title {
                font-size: 1.5rem;
            }

            .evf-form-subtitle {
                font-size: 14px;
            }
        }

        /* Print styles */
        @media print {
            .evf-registration-wrapper {
                background: white;
            }

            .evf-registration-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .evf-form-actions,
            .evf-btn {
                display: none;
            }
        }
    </style>

    <script>
        // Additional JavaScript for this template
        jQuery(document).ready(function($) {
            // Configuration object for localized strings
            const evfConfig = {
                messages: {
                    sending: <?php echo wp_json_encode(__('Gönderiliyor...', 'email-verification-forms')); ?>,
                    emailResent: <?php echo wp_json_encode(__('E-posta tekrar gönderildi!', 'email-verification-forms')); ?>,
                    emailError: <?php echo wp_json_encode(__('E-posta gönderilemedi. Lütfen tekrar deneyin.', 'email-verification-forms')); ?>,
                    generalError: <?php echo wp_json_encode(__('Bir hata oluştu. Lütfen tekrar deneyin.', 'email-verification-forms')); ?>,
                    resendEmail: <?php echo wp_json_encode(__('Tekrar Gönder', 'email-verification-forms')); ?>
                }
            };

            // Resend email functionality
            $('.evf-resend-email').on('click', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const $form = $('.evf-registration-form');
                const email = $form.find('.evf-email-input').val();

                if (!email) {
                    return;
                }

                // Set loading state
                $btn.prop('disabled', true).html('<span class="evf-spinner"></span> ' + evfConfig.messages.sending);

                // Make AJAX request
                $.ajax({
                    url: evf_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'evf_register_user',
                        nonce: evf_ajax.nonce,
                        email: email
                    },
                    success: function(response) {
                        if (response.success) {
                            EVF.showMessage('success', evfConfig.messages.emailResent);
                        } else {
                            EVF.showMessage('error', evfConfig.messages.emailError);
                        }
                    },
                    error: function() {
                        EVF.showMessage('error', evfConfig.messages.generalError);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(evfConfig.messages.resendEmail);
                    }
                });
            });

            // Auto-focus email input
            $('.evf-email-input').focus();

            // Email validation feedback
            $('.evf-email-input').on('input', function() {
                const $input = $(this);
                const email = $input.val().trim();

                if (email && EVF.isValidEmail(email)) {
                    $input.removeClass('error').addClass('success');
                } else if (email) {
                    $input.removeClass('success').addClass('error');
                } else {
                    $input.removeClass('error success');
                }
            });

            // Enhanced keyboard navigation
            $('.evf-registration-form').on('keydown', function(e) {
                if (e.key === 'Enter' && !$(e.target).is('button')) {
                    e.preventDefault();
                    $(this).find('.evf-submit-btn').click();
                }
            });

            // Form step navigation
            $('.evf-prev-step').on('click', function() {
                const step = $(this).data('step');

                if (step === 2) {
                    $('.evf-form-step-2').hide();
                    $('.evf-form-step-1').show();
                    $('.evf-progress-step').removeClass('completed');
                    $('.evf-progress-step:first-child').addClass('active');
                    $('.evf-email-input').focus();
                }
            });

            // Accessibility improvements
            $('.evf-btn').on('focus', function() {
                $(this).addClass('focus-visible');
            }).on('blur', function() {
                $(this).removeClass('focus-visible');
            });

            // Auto-clear messages after 5 seconds
            $(document).on('evf:message:shown', function() {
                setTimeout(function() {
                    $('#evf-message').fadeOut();
                }, 5000);
            });

            // Form validation on submit
            $('.evf-registration-form').on('submit', function(e) {
                const email = $('.evf-email-input').val().trim();

                if (!email) {
                    e.preventDefault();
                    $('.evf-email-input').focus().addClass('error');
                    EVF.showMessage('error', <?php echo wp_json_encode(__('Lütfen e-posta adresinizi girin.', 'email-verification-forms')); ?>);
                    return false;
                }

                if (!EVF.isValidEmail(email)) {
                    e.preventDefault();
                    $('.evf-email-input').focus().addClass('error');
                    EVF.showMessage('error', <?php echo wp_json_encode(__('Lütfen geçerli bir e-posta adresi girin.', 'email-verification-forms')); ?>);
                    return false;
                }
            });

            // Progressive enhancement for older browsers
            if (!window.fetch) {
                $('.evf-resend-email').on('click', function(e) {
                    e.preventDefault();
                    alert(<?php echo wp_json_encode(__('Bu özellik için daha güncel bir tarayıcı gerekiyor.', 'email-verification-forms')); ?>);
                });
            }
        });
    </script>

<?php get_footer(); ?>