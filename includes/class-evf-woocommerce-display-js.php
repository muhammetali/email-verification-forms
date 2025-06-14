<?php
/**
 * EVF WooCommerce Display JavaScript Handler
 * JavaScript yönetimi ve kod oluşturma
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_Display_JS {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    /**
     * JavaScript hook'larını başlat
     */
    private function init_hooks() {
        // Dynamic JavaScript endpoint
        add_action('wp_ajax_evf_dynamic_js', array($this, 'serve_dynamic_js'));
        add_action('wp_ajax_nopriv_evf_dynamic_js', array($this, 'serve_dynamic_js'));
    }

    /**
     * Dynamic JavaScript serve et
     */
    public function serve_dynamic_js() {
        $type = sanitize_text_field($_GET['type'] ?? 'verification');

        // Cache headers
        $etag = md5($type . EVF_VERSION);
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        header('ETag: "' . $etag . '"');

        // ETag kontrolü
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
            http_response_code(304);
            exit;
        }

        // JavaScript'i generate et ve serve et
        switch ($type) {
            case 'verification':
                echo $this->get_verification_javascript();
                break;
            default:
                echo $this->get_default_javascript();
                break;
        }

        exit;
    }

    /**
     * Verification JavaScript'i generate et
     */
    public function get_verification_javascript($email = null, $remaining_seconds = 0, $resend_interval = 300, $max_attempts = 5, $code_expiry = 1800) {
        // Default değerler
        if (!$email) {
            $email = 'user@example.com'; // Placeholder
        }

        ob_start();
        ?>
        // EVF Enhanced Code Verification JavaScript
        (function() {
        'use strict';

        // Sayfa yüklendiğinde başlat
        if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVerification);
        } else {
        initVerification();
        }

        function initVerification() {
        console.log('EVF Code Verification: Enhanced version loaded');

        // Configuration
        const config = {
        email: <?php echo wp_json_encode($email); ?>,
        ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce('evf_nonce')); ?>,
        resendInterval: <?php echo intval($resend_interval); ?>,
        maxAttempts: <?php echo intval($max_attempts); ?>,
        codeExpiry: <?php echo intval($code_expiry); ?>,
        accountUrl: <?php echo wp_json_encode(evf_is_woocommerce_active() ? wc_get_page_permalink('myaccount') : home_url()); ?>,
        registerUrl: <?php echo wp_json_encode(evf_is_woocommerce_active() ? wc_get_page_permalink('myaccount') . '?action=register' : wp_registration_url()); ?>
        };

        // DOM elements
        const elements = {
        form: document.getElementById('evf-code-form'),
        input: document.getElementById('evf-code-input') || document.getElementById('code-input'),
        message: document.getElementById('evf-message'),
        submitBtn: document.getElementById('evf-submit-btn') || document.querySelector('button[type="submit"]'),
        submitText: document.getElementById('evf-submit-text'),
        submitLoading: document.getElementById('evf-submit-loading'),
        resendBtn: document.getElementById('evf-resend-btn'),
        resendText: document.getElementById('evf-resend-text'),
        resendCountdown: document.getElementById('evf-resend-countdown'),
        resendLoading: document.getElementById('evf-resend-loading'),
        timer: document.getElementById('evf-countdown-timer')
        };

        // Validation
        if (!elements.form || !elements.input || !elements.submitBtn) {
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
        if (elements.resendBtn) {
        elements.resendBtn.disabled = active;
        }
        if (elements.resendText) {
        elements.resendText.style.display = active ? 'none' : 'flex';
        }
        if (elements.resendCountdown) {
        elements.resendCountdown.style.display = active ? 'flex' : 'none';
        }
        if (elements.resendLoading) {
        elements.resendLoading.style.display = 'none';
        }
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
        elements.input.style.borderColor = 'var(--evf-error, #ef4444)';
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
        elements.input.style.borderColor = 'var(--evf-success, #10b981)';
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
        if (elements.submitText) {
        elements.submitText.style.display = loading ? 'none' : 'flex';
        }
        if (elements.submitLoading) {
        elements.submitLoading.style.display = loading ? 'flex' : 'none';
        }
        }

        // Resend functionality
        if (elements.resendBtn) {
        elements.resendBtn.addEventListener('click', function(e) {
        e.preventDefault();

        if (this.disabled) {
        return;
        }

        resendCode();
        });
        }

        function resendCode() {
        console.log('Resending verification code');

        if (elements.resendBtn) {
        elements.resendBtn.disabled = true;
        }
        if (elements.resendText) {
        elements.resendText.style.display = 'none';
        }
        if (elements.resendLoading) {
        elements.resendLoading.style.display = 'flex';
        }

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
        if (elements.resendBtn) {
        elements.resendBtn.disabled = false;
        }
        if (elements.resendText) {
        elements.resendText.style.display = 'flex';
        }
        if (elements.resendLoading) {
        elements.resendLoading.style.display = 'none';
        }
        if (elements.resendCountdown) {
        elements.resendCountdown.style.display = 'none';
        }
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

        // Focus input after a short delay
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
        }
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Basit verification JavaScript
     */
    public function get_simple_verification_javascript($email, $config = array()) {
        $defaults = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('evf_nonce'),
            'account_url' => evf_is_woocommerce_active() ? wc_get_page_permalink('myaccount') : home_url(),
            'register_url' => evf_is_woocommerce_active() ? wc_get_page_permalink('myaccount') . '?action=register' : wp_registration_url()
        );

        $config = wp_parse_args($config, $defaults);

        ob_start();
        ?>
        (function() {
        'use strict';

        document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('verification-form') || document.querySelector('form');
        const input = document.getElementById('code-input') || document.querySelector('input[type="text"]');
        const submitBtn = document.querySelector('button[type="submit"]');

        if (!form || !input || !submitBtn) {
        console.error('EVF: Required elements not found');
        return;
        }

        form.addEventListener('submit', function(e) {
        e.preventDefault();

        const code = input.value.trim();

        if (code.length !== 6 || !/^\d{6}$/.test(code)) {
        alert('Lütfen 6 haneli sayısal kod girin.');
        return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Doğrulanıyor...';

        fetch(<?php echo wp_json_encode($config['ajax_url']); ?>, {
        method: 'POST',
        headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
        action: 'evf_verify_code',
        nonce: <?php echo wp_json_encode($config['nonce']); ?>,
        email: <?php echo wp_json_encode($email); ?>,
        verification_code: code
        })
        })
        .then(response => response.json())
        .then(data => {
        if (data.success) {
        alert('Kod doğrulandı! Yönlendiriliyor...');
        window.location.href = data.data.redirect_url || <?php echo wp_json_encode($config['account_url']); ?>;
        } else {
        alert('Geçersiz kod. Lütfen tekrar deneyin.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Kodu Doğrula';
        }
        })
        .catch(error => {
        console.error('Error:', error);
        alert('Bir hata oluştu. Lütfen tekrar deneyin.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Kodu Doğrula';
        });
        });

        // Auto-format input
        input.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 6) {
        value = value.substr(0, 6);
        }
        this.value = value;

        // Auto-submit on 6 digits
        if (value.length === 6) {
        setTimeout(() => {
        if (!submitBtn.disabled) {
        form.dispatchEvent(new Event('submit'));
        }
        }, 300);
        }
        });

        // Focus input
        input.focus();
        });
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Success page JavaScript
     */
    public function get_success_javascript() {
        ob_start();
        ?>
        (function() {
        'use strict';

        document.addEventListener('DOMContentLoaded', function() {
        const card = document.querySelector('.evf-success-card');
        const icon = document.querySelector('.evf-success-icon-wrapper');

        if (card && icon) {
        setTimeout(() => {
        card.classList.add('evf-animate-in');
        icon.classList.add('evf-animate-check');
        }, 100);
        }

        // Auto-redirect after 10 seconds (optional)
        const autoRedirect = document.querySelector('[data-auto-redirect]');
        if (autoRedirect) {
        const url = autoRedirect.getAttribute('data-auto-redirect');
        const delay = parseInt(autoRedirect.getAttribute('data-delay') || '10') * 1000;

        setTimeout(() => {
        window.location.href = url;
        }, delay);
        }
        });
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Countdown timer JavaScript
     */
    public function get_countdown_javascript($seconds) {
        ob_start();
        ?>
        (function() {
        'use strict';

        function startCountdown(totalSeconds) {
        const timerElement = document.getElementById('evf-countdown-timer');
        const buttonElement = document.getElementById('evf-resend-btn');
        const textElement = document.getElementById('evf-resend-text');
        const countdownElement = document.getElementById('evf-resend-countdown');

        if (!timerElement || !buttonElement) {
        return;
        }

        let remaining = Math.floor(totalSeconds);

        function updateDisplay() {
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        const display = minutes > 0 ?
        `${minutes}:${seconds.toString().padStart(2, '0')}` :
        seconds.toString();

        timerElement.textContent = display;
        }

        function tick() {
        updateDisplay();

        if (remaining <= 0) {
        // Countdown finished
        buttonElement.disabled = false;
        if (textElement) textElement.style.display = 'flex';
        if (countdownElement) countdownElement.style.display = 'none';
        return;
        }

        remaining--;
        setTimeout(tick, 1000);
        }

        // Initialize countdown
        buttonElement.disabled = true;
        if (textElement) textElement.style.display = 'none';
        if (countdownElement) countdownElement.style.display = 'flex';

        tick();
        }

        // Start countdown when page loads
        document.addEventListener('DOMContentLoaded', function() {
        const initialSeconds = <?php echo intval($seconds); ?>;
        if (initialSeconds > 0) {
        startCountdown(initialSeconds);
        }
        });
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Form validation JavaScript
     */
    public function get_form_validation_javascript() {
        ob_start();
        ?>
        (function() {
        'use strict';

        document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.evf-form');

        forms.forEach(function(form) {
        // Real-time validation
        const inputs = form.querySelectorAll('input[required]');

        inputs.forEach(function(input) {
        input.addEventListener('blur', function() {
        validateInput(this);
        });

        input.addEventListener('input', function() {
        clearInputError(this);
        });
        });

        // Form submission validation
        form.addEventListener('submit', function(e) {
        let isValid = true;

        inputs.forEach(function(input) {
        if (!validateInput(input)) {
        isValid = false;
        }
        });

        if (!isValid) {
        e.preventDefault();
        }
        });
        });

        function validateInput(input) {
        const value = input.value.trim();
        let isValid = true;
        let message = '';

        // Required validation
        if (input.hasAttribute('required') && !value) {
        isValid = false;
        message = 'Bu alan zorunludur.';
        }

        // Email validation
        if (input.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
        isValid = false;
        message = 'Geçerli bir e-posta adresi girin.';
        }
        }

        // Pattern validation
        if (input.hasAttribute('pattern') && value) {
        const pattern = new RegExp(input.getAttribute('pattern'));
        if (!pattern.test(value)) {
        isValid = false;
        message = input.getAttribute('data-error-message') || 'Geçersiz format.';
        }
        }

        // Minlength validation
        if (input.hasAttribute('minlength') && value) {
        const minLength = parseInt(input.getAttribute('minlength'));
        if (value.length < minLength) {
        isValid = false;
        message = `En az ${minLength} karakter gerekli.`;
        }
        }

        // Show/hide error
        if (isValid) {
        clearInputError(input);
        } else {
        showInputError(input, message);
        }

        return isValid;
        }

        function showInputError(input, message) {
        input.classList.add('evf-input-error');

        // Remove existing error message
        const existingError = input.parentNode.querySelector('.evf-input-error-message');
        if (existingError) {
        existingError.remove();
        }

        // Add new error message
        const errorElement = document.createElement('div');
        errorElement.className = 'evf-input-error-message';
        errorElement.textContent = message;
        errorElement.style.color = 'var(--evf-error, #ef4444)';
        errorElement.style.fontSize = '12px';
        errorElement.style.marginTop = '4px';

        input.parentNode.appendChild(errorElement);
        }

        function clearInputError(input) {
        input.classList.remove('evf-input-error');

        const errorMessage = input.parentNode.querySelector('.evf-input-error-message');
        if (errorMessage) {
        errorMessage.remove();
        }
        }
        });
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Progress bar JavaScript
     */
    public function get_progress_javascript($currentStep = 2, $totalSteps = 3) {
        ob_start();
        ?>
        (function() {
        'use strict';

        document.addEventListener('DOMContentLoaded', function() {
        const steps = document.querySelectorAll('.evf-progress-step');
        const currentStep = <?php echo intval($currentStep); ?>;

        steps.forEach(function(step, index) {
        const stepNumber = index + 1;

        if (stepNumber < currentStep) {
        step.classList.add('evf-step-completed');
        } else if (stepNumber === currentStep) {
        step.classList.add('evf-step-active');
        }
        });

        // Animate progress bar
        setTimeout(function() {
        const progressBar = document.querySelector('.evf-progress-bar');
        if (progressBar) {
        progressBar.style.opacity = '1';
        progressBar.style.transform = 'translateY(0)';
        }
        }, 300);
        });
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Default JavaScript (minimal)
     */
    private function get_default_javascript() {
        ob_start();
        ?>
        (function() {
        'use strict';

        document.addEventListener('DOMContentLoaded', function() {
        console.log('EVF: JavaScript loaded');

        // Basic form handling
        const forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'İşleniyor...';
        }
        });
        });

        // Basic input formatting for code inputs
        const codeInputs = document.querySelectorAll('input[pattern*="[0-9]"]');
        codeInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
        });
        });
        });
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Utility: AJAX helper function
     */
    public function get_ajax_helper_javascript() {
        ob_start();
        ?>
        // EVF AJAX Helper
        window.EVF_Ajax = (function() {
        'use strict';

        function request(action, data, options) {
        options = options || {};

        const defaults = {
        method: 'POST',
        headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
        }
        };

        const config = Object.assign(defaults, options);

        // Prepare data
        const params = new URLSearchParams();
        params.append('action', action);

        if (window.evf_config && window.evf_config.nonce) {
        params.append('nonce', window.evf_config.nonce);
        }

        for (const key in data) {
        if (data.hasOwnProperty(key)) {
        params.append(key, data[key]);
        }
        }

        const ajaxUrl = (window.evf_config && window.evf_config.ajax_url) ||
        '/wp-admin/admin-ajax.php';

        return fetch(ajaxUrl, {
        method: config.method,
        headers: config.headers,
        body: params
        })
        .then(response => {
        if (!response.ok) {
        throw new Error('Network response was not ok');
        }
        return response.json();
        });
        }

        return {
        post: function(action, data, options) {
        return request(action, data, options);
        },

        get: function(action, data, options) {
        const getOptions = Object.assign({ method: 'GET' }, options);
        return request(action, data, getOptions);
        }
        };
        })();
        <?php
        return ob_get_clean();
    }

    /**
     * Generate all JavaScript files
     */
    public function generate_js_files() {
        $upload_dir = wp_upload_dir();
        $js_dir = $upload_dir['basedir'] . '/evf-js/';

        // Klasör oluştur
        if (!file_exists($js_dir)) {
            wp_mkdir_p($js_dir);
        }

        // Verification JavaScript file
        $verification_js = $this->get_verification_javascript();
        file_put_contents($js_dir . 'verification.js', $verification_js);

        // Success JavaScript file
        $success_js = $this->get_success_javascript();
        file_put_contents($js_dir . 'success.js', $success_js);

        // Form validation JavaScript file
        $validation_js = $this->get_form_validation_javascript();
        file_put_contents($js_dir . 'validation.js', $validation_js);

        // AJAX helper JavaScript file
        $ajax_js = $this->get_ajax_helper_javascript();
        file_put_contents($js_dir . 'ajax-helper.js', $ajax_js);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: JavaScript files generated successfully');
        }
    }
}