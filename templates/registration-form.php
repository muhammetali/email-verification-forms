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
                <div class="evf-progress-label"><?php _e('E-posta', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step">
                <div class="evf-progress-circle">2</div>
                <div class="evf-progress-label"><?php _e('Doğrulama', 'email-verification-forms'); ?></div>
            </div>
            <div class="evf-progress-step">
                <div class="evf-progress-circle">3</div>
                <div class="evf-progress-label"><?php _e('Parola', 'email-verification-forms'); ?></div>
            </div>
        </div>

        <!-- Form Header -->
        <div class="evf-form-header">
            <h1 class="evf-form-title"><?php _e('Hesap Oluştur', 'email-verification-forms'); ?></h1>
            <p class="evf-form-subtitle">
                <?php _e('E-posta adresinizi girin ve size gönderilecek doğrulama bağlantısını takip edin.', 'email-verification-forms'); ?>
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
                        <?php _e('E-posta Adresi', 'email-verification-forms'); ?>
                        <span class="required" aria-label="<?php _e('zorunlu', 'email-verification-forms'); ?>">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="evf-email" 
                        name="email" 
                        class="evf-input evf-email-input" 
                        required 
                        autocomplete="email"
                        placeholder="ornek@email.com"
                        aria-describedby="evf-email-help"
                    >
                    <div id="evf-email-help" class="evf-help-text">
                        <?php _e('Bu e-posta adresine doğrulama bağlantısı gönderilecektir.', 'email-verification-forms'); ?>
                    </div>
                </div>

                <div class="evf-form-group">
                    <button type="submit" class="evf-btn evf-btn-primary evf-btn-full evf-submit-btn">
                        <span class="evf-btn-text"><?php _e('Doğrulama E-postası Gönder', 'email-verification-forms'); ?></span>
                        <span class="evf-btn-loading" style="display: none;">
                            <span class="evf-spinner"></span>
                            <?php _e('Gönderiliyor...', 'email-verification-forms'); ?>
                        </span>
                    </button>
                </div>
            </div>

            <!-- Step 2: Email Sent Confirmation -->
            <div class="evf-form-step evf-form-step-2" style="display: none;">
                <div class="evf-success-content">
                    <div class="evf-success-icon">✉️</div>
                    <h2 class="evf-success-title"><?php _e('E-posta Gönderildi!', 'email-verification-forms'); ?></h2>
                    <p class="evf-success-message">
                        <?php _e('E-posta adresinize bir doğrulama bağlantısı gönderdik. Lütfen e-postanızı kontrol edin ve bağlantıya tıklayarak kayıt işleminizi tamamlayın.', 'email-verification-forms'); ?>
                    </p>
                    
                    <div class="evf-email-tips">
                        <h3><?php _e('E-posta gelmedi mi?', 'email-verification-forms'); ?></h3>
                        <ul>
                            <li><?php _e('Spam/Junk klasörünüzü kontrol edin', 'email-verification-forms'); ?></li>
                            <li><?php _e('E-posta adresinizi doğru yazdığınızdan emin olun', 'email-verification-forms'); ?></li>
                            <li><?php _e('Birkaç dakika bekleyin, e-posta gelmesi zaman alabilir', 'email-verification-forms'); ?></li>
                        </ul>
                    </div>

                    <div class="evf-form-actions">
                        <button type="button" class="evf-btn evf-btn-secondary evf-prev-step" data-step="2">
                            <?php _e('← Geri Dön', 'email-verification-forms'); ?>
                        </button>
                        <button type="button" class="evf-btn evf-btn-primary evf-resend-email">
                            <?php _e('Tekrar Gönder', 'email-verification-forms'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Login Link -->
        <div class="evf-form-footer">
            <p class="evf-login-link">
                <?php _e('Zaten hesabınız var mı?', 'email-verification-forms'); ?>
                <a href="<?php echo wp_login_url(); ?>" class="evf-link">
                    <?php _e('Giriş Yapın', 'email-verification-forms'); ?>
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
}
</style>

<script>
// Additional JavaScript for this template
jQuery(document).ready(function($) {
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
        $btn.prop('disabled', true).html('<span class="evf-spinner"></span> <?php _e('Gönderiliyor...', 'email-verification-forms'); ?>');
        
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
                    EVF.showMessage('success', '<?php _e('E-posta tekrar gönderildi!', 'email-verification-forms'); ?>');
                } else {
                    EVF.showMessage('error', '<?php _e('E-posta gönderilemedi. Lütfen tekrar deneyin.', 'email-verification-forms'); ?>');
                }
            },
            error: function() {
                EVF.showMessage('error', '<?php _e('Bir hata oluştu. Lütfen tekrar deneyin.', 'email-verification-forms'); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php _e('Tekrar Gönder', 'email-verification-forms'); ?>');
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
});
</script>

<?php get_footer(); ?>