/**
 * Email Verification Forms - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Global EVF object
    window.EVF = {
        init: function() {
            this.bindEvents();
            this.initPasswordStrength();
            this.initEmailValidation();
        },

        bindEvents: function() {
            // Registration form submission
            $(document).on('submit', '.evf-registration-form', this.handleRegistration);
            
            // Password setup form submission
            $(document).on('submit', '.evf-password-setup-form', this.handlePasswordSetup);
            
            // Email input validation
            $(document).on('blur', '.evf-email-input', this.validateEmail);
            $(document).on('input', '.evf-email-input', this.clearEmailValidation);
            
            // Password input events
            $(document).on('input', '.evf-password-input', this.checkPasswordStrength);
            $(document).on('input', '.evf-password-confirm', this.checkPasswordMatch);
            
            // Form navigation
            $(document).on('click', '.evf-next-step', this.nextStep);
            $(document).on('click', '.evf-prev-step', this.prevStep);
        },

        handleRegistration: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('.evf-submit-btn');
            const $emailInput = $form.find('.evf-email-input');
            const email = $emailInput.val().trim();
            
            // Validate email
            if (!EVF.isValidEmail(email)) {
                EVF.showMessage('error', evf_ajax.messages.invalid_email);
                $emailInput.addClass('error');
                return;
            }
            
            // Check if already checking
            if ($submitBtn.hasClass('loading')) {
                return;
            }
            
            // Set loading state
            EVF.setButtonLoading($submitBtn, true);
            EVF.hideMessage();
            
            // AJAX request
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
                        EVF.showMessage('success', evf_ajax.messages.email_sent);
                        $form.find('.evf-form-step-1').hide();
                        $form.find('.evf-form-step-2').show();
                        EVF.updateProgressBar(2);
                    } else {
                        let errorMsg = evf_ajax.messages.error;
                        
                        switch (response.data) {
                            case 'email_exists':
                                errorMsg = evf_ajax.messages.email_exists;
                                break;
                            case 'rate_limit':
                                errorMsg = evf_ajax.messages.rate_limit;
                                break;
                            case 'invalid_email':
                                errorMsg = evf_ajax.messages.invalid_email;
                                break;
                        }
                        
                        EVF.showMessage('error', errorMsg);
                    }
                },
                error: function() {
                    EVF.showMessage('error', evf_ajax.messages.error);
                },
                complete: function() {
                    EVF.setButtonLoading($submitBtn, false);
                }
            });
        },

        handlePasswordSetup: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('.evf-submit-btn');
            const password = $form.find('.evf-password-input').val();
            const passwordConfirm = $form.find('.evf-password-confirm').val();
            const token = $form.find('input[name="token"]').val();
            
            // Validation
            if (!password || !passwordConfirm) {
                EVF.showMessage('error', evf_ajax.messages.password_required);
                return;
            }
            
            if (password !== passwordConfirm) {
                EVF.showMessage('error', evf_ajax.messages.passwords_not_match);
                return;
            }
            
            if (!EVF.isPasswordStrong(password)) {
                EVF.showMessage('error', evf_ajax.messages.password_weak);
                return;
            }
            
            // Check if already submitting
            if ($submitBtn.hasClass('loading')) {
                return;
            }
            
            // Set loading state
            EVF.setButtonLoading($submitBtn, true);
            EVF.hideMessage();
            
            // AJAX request
            $.ajax({
                url: evf_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_set_password',
                    nonce: evf_ajax.nonce,
                    token: token,
                    password: password,
                    password_confirm: passwordConfirm
                },
                success: function(response) {
                    if (response.success) {
                        EVF.showMessage('success', evf_ajax.messages.password_set);
                        
                        // Redirect after 2 seconds
                        setTimeout(function() {
                            window.location.href = response.data.login_url || evf_ajax.settings.login_url;
                        }, 2000);
                        
                    } else {
                        let errorMsg = evf_ajax.messages.error;
                        
                        switch (response.data) {
                            case 'token_invalid':
                                errorMsg = evf_ajax.messages.token_invalid;
                                break;
                            case 'token_expired':
                                errorMsg = evf_ajax.messages.token_expired;
                                break;
                            case 'passwords_not_match':
                                errorMsg = evf_ajax.messages.passwords_not_match;
                                break;
                            case 'password_weak':
                                errorMsg = evf_ajax.messages.password_weak;
                                break;
                        }
                        
                        EVF.showMessage('error', errorMsg);
                    }
                },
                error: function() {
                    EVF.showMessage('error', evf_ajax.messages.error);
                },
                complete: function() {
                    EVF.setButtonLoading($submitBtn, false);
                }
            });
        },

        validateEmail: function() {
            const $input = $(this);
            const email = $input.val().trim();
            
            $input.removeClass('error success checking');
            
            if (!email) {
                return;
            }
            
            if (!EVF.isValidEmail(email)) {
                $input.addClass('error');
                return;
            }
            
            // Check if email exists
            $input.addClass('checking');
            
            $.ajax({
                url: evf_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_check_email',
                    nonce: evf_ajax.nonce,
                    email: email
                },
                success: function(response) {
                    $input.removeClass('checking');
                    
                    if (response.success) {
                        $input.addClass('success');
                    } else {
                        $input.addClass('error');
                        if (response.data === 'email_exists') {
                            EVF.showMessage('warning', evf_ajax.messages.email_exists);
                        }
                    }
                },
                error: function() {
                    $input.removeClass('checking');
                }
            });
        },

        clearEmailValidation: function() {
            $(this).removeClass('error success');
            EVF.hideMessage();
        },

        checkPasswordStrength: function() {
            const $input = $(this);
            const password = $input.val();
            const $strengthMeter = $input.siblings('.evf-password-strength');
            
            if (!$strengthMeter.length) {
                return;
            }
            
            const strength = EVF.getPasswordStrength(password);
            const $strengthFill = $strengthMeter.find('.evf-strength-fill');
            const $strengthText = $strengthMeter.find('.evf-strength-text');
            
            // Update strength bar
            $strengthFill.removeClass('weak medium strong very-strong');
            $strengthFill.addClass(strength.class);
            
            // Update strength text
            $strengthText.text(strength.text);
            
            // Show/hide strength meter
            if (password.length > 0) {
                $strengthMeter.show();
            } else {
                $strengthMeter.hide();
            }
        },

        checkPasswordMatch: function() {
            const $confirmInput = $(this);
            const $passwordInput = $('.evf-password-input');
            const password = $passwordInput.val();
            const confirmPassword = $confirmInput.val();
            
            $confirmInput.removeClass('error success');
            
            if (confirmPassword.length === 0) {
                return;
            }
            
            if (password === confirmPassword) {
                $confirmInput.addClass('success');
            } else {
                $confirmInput.addClass('error');
            }
        },

        getPasswordStrength: function(password) {
            if (password.length === 0) {
                return { class: '', text: '' };
            }
            
            let score = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            
            // Character variety
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            // Common patterns (reduce score)
            if (/(.)\1{2,}/.test(password)) score--; // Repeated characters
            if (/123|abc|qwe/i.test(password)) score--; // Sequential characters
            
            // Determine strength
            if (score < 2) {
                return { class: 'weak', text: 'Zayıf' };
            } else if (score < 4) {
                return { class: 'medium', text: 'Orta' };
            } else if (score < 6) {
                return { class: 'strong', text: 'Güçlü' };
            } else {
                return { class: 'very-strong', text: 'Çok Güçlü' };
            }
        },

        isPasswordStrong: function(password) {
            const minLength = evf_ajax.settings.min_password_length || 8;
            const requireStrong = evf_ajax.settings.require_strong_password;
            
            if (password.length < minLength) {
                return false;
            }
            
            if (!requireStrong) {
                return true;
            }
            
            // Strong password requirements
            const hasLower = /[a-z]/.test(password);
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            
            return hasLower && hasUpper && hasNumber;
        },

        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        showMessage: function(type, message) {
            const $messageContainer = $('.evf-message');
            
            if (!$messageContainer.length) {
                return;
            }
            
            $messageContainer
                .removeClass('success error warning')
                .addClass(type)
                .text(message)
                .addClass('show');
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    EVF.hideMessage();
                }, 5000);
            }
        },

        hideMessage: function() {
            $('.evf-message').removeClass('show');
        },

        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.addClass('loading').prop('disabled', true);
                const originalText = $button.text();
                $button.data('original-text', originalText);
                $button.html('<span class="evf-spinner"></span>' + evf_ajax.messages.loading);
            } else {
                $button.removeClass('loading').prop('disabled', false);
                const originalText = $button.data('original-text');
                if (originalText) {
                    $button.text(originalText);
                }
            }
        },

        updateProgressBar: function(step) {
            $('.evf-progress-step').each(function(index) {
                const $step = $(this);
                const stepNumber = index + 1;
                
                $step.removeClass('active completed');
                
                if (stepNumber < step) {
                    $step.addClass('completed');
                } else if (stepNumber === step) {
                    $step.addClass('active');
                }
            });
        },

        nextStep: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const currentStep = parseInt($button.data('step'));
            const nextStep = currentStep + 1;
            
            // Hide current step
            $('.evf-form-step-' + currentStep).hide();
            
            // Show next step
            $('.evf-form-step-' + nextStep).show();
            
            // Update progress bar
            EVF.updateProgressBar(nextStep);
        },

        prevStep: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const currentStep = parseInt($button.data('step'));
            const prevStep = currentStep - 1;
            
            // Hide current step
            $('.evf-form-step-' + currentStep).hide();
            
            // Show previous step
            $('.evf-form-step-' + prevStep).show();
            
            // Update progress bar
            EVF.updateProgressBar(prevStep);
        },

        initPasswordStrength: function() {
            // Add password strength meter to password inputs
            $('.evf-password-input').each(function() {
                const $input = $(this);
                if ($input.siblings('.evf-password-strength').length === 0) {
                    const strengthHtml = `
                        <div class="evf-password-strength" style="display: none;">
                            <div class="evf-strength-bar">
                                <div class="evf-strength-fill"></div>
                            </div>
                            <div class="evf-strength-text"></div>
                        </div>
                    `;
                    $input.after(strengthHtml);
                }
            });
        },

        initEmailValidation: function() {
            // Add real-time email validation
            $('.evf-email-input').each(function() {
                const $input = $(this);
                
                // Add validation icon container
                if ($input.siblings('.evf-validation-icon').length === 0) {
                    $input.after('<span class="evf-validation-icon"></span>');
                }
            });
        },

        // Utility functions
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        // Form validation helper
        validateForm: function($form) {
            let isValid = true;
            
            $form.find('input[required]').each(function() {
                const $input = $(this);
                const value = $input.val().trim();
                
                $input.removeClass('error');
                
                if (!value) {
                    $input.addClass('error');
                    isValid = false;
                }
                
                // Email validation
                if ($input.attr('type') === 'email' && value && !EVF.isValidEmail(value)) {
                    $input.addClass('error');
                    isValid = false;
                }
            });
            
            return isValid;
        },

        // Accessibility helpers
        announceToScreen: function(message) {
            const $announcer = $('#evf-screen-reader-announcer');
            if ($announcer.length) {
                $announcer.text(message);
            } else {
                $('body').append('<div id="evf-screen-reader-announcer" class="sr-only" aria-live="polite">' + message + '</div>');
            }
        },

        // Local storage helpers (for remembering form state)
        saveFormState: function(formId, data) {
            if (typeof Storage !== 'undefined') {
                localStorage.setItem('evf_form_' + formId, JSON.stringify(data));
            }
        },

        loadFormState: function(formId) {
            if (typeof Storage !== 'undefined') {
                const saved = localStorage.getItem('evf_form_' + formId);
                return saved ? JSON.parse(saved) : null;
            }
            return null;
        },

        clearFormState: function(formId) {
            if (typeof Storage !== 'undefined') {
                localStorage.removeItem('evf_form_' + formId);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EVF.init();
        
        // Add screen reader announcer
        if ($('#evf-screen-reader-announcer').length === 0) {
            $('body').append('<div id="evf-screen-reader-announcer" class="sr-only" aria-live="polite"></div>');
        }
        
        // Handle browser back/forward buttons
        $(window).on('popstate', function(e) {
            if (e.originalEvent.state && e.originalEvent.state.evfStep) {
                EVF.updateProgressBar(e.originalEvent.state.evfStep);
            }
        });
        
        // Auto-focus first input
        $('.evf-registration-form input:visible:first, .evf-password-setup-form input:visible:first').focus();
        
        // Handle form autofill detection
        setTimeout(function() {
            $('input:-webkit-autofill').each(function() {
                $(this).trigger('input');
            });
        }, 100);
    });

    // Handle window resize for responsive design
    $(window).on('resize', EVF.debounce(function() {
        // Any responsive adjustments can go here
    }, 250));

})(jQuery);

// Add CSS classes for screen readers
const screenReaderCSS = `
    .sr-only {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }
`;

// Add screen reader styles to head
if (document.head) {
    const style = document.createElement('style');
    style.textContent = screenReaderCSS;
    document.head.appendChild(style);
}