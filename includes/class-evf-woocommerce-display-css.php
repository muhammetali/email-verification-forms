<?php
/**
 * EVF WooCommerce Display CSS Handler
 * CSS yönetimi - wp_add_inline_style hatası düzeltildi
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_Display_CSS {

    private static $instance = null;

    /**
     * CSS variables ve değerler
     */
    private $css_vars = array();

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_css_vars();
        $this->init_hooks();
    }

    /**
     * CSS hook'larını başlat
     */
    private function init_hooks() {
        // Dynamic CSS endpoint
        add_action('wp_ajax_evf_dynamic_css', array($this, 'serve_dynamic_css'));
        add_action('wp_ajax_nopriv_evf_dynamic_css', array($this, 'serve_dynamic_css'));

        // CSS file generation
        add_action('evf_generate_css_files', array($this, 'generate_css_files'));
    }

    /**
     * CSS değişkenlerini başlat
     */
    private function init_css_vars() {
        $this->css_vars = array(
            'primary' => get_option('evf_primary_color', '#3b82f6'),
            'primary_dark' => get_option('evf_primary_dark_color', '#1d4ed8'),
            'success' => '#10b981',
            'error' => '#ef4444',
            'warning' => '#f59e0b',
            'gray_50' => '#f9fafb',
            'gray_100' => '#f3f4f6',
            'gray_200' => '#e5e7eb',
            'gray_300' => '#d1d5db',
            'gray_400' => '#9ca3af',
            'gray_500' => '#6b7280',
            'gray_600' => '#4b5563',
            'gray_700' => '#374151',
            'gray_800' => '#1f2937',
            'gray_900' => '#111827',
            'white' => '#ffffff',
            'border_radius' => '12px',
            'transition' => 'all 0.2s ease-in-out'
        );
    }

    /**
     * Dynamic CSS serve et
     */
    public function serve_dynamic_css() {
        $type = sanitize_text_field($_GET['type'] ?? 'verification');

        // Cache headers
        $etag = md5($type . EVF_VERSION . serialize($this->css_vars));
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        header('ETag: "' . $etag . '"');

        // ETag kontrolü
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
            http_response_code(304);
            exit;
        }

        // CSS'i generate et ve serve et
        switch ($type) {
            case 'verification':
                echo $this->get_verification_css();
                break;
            default:
                echo $this->get_default_css();
                break;
        }

        exit;
    }

    /**
     * Ana verification CSS'i - DÜZELTİLMİŞ (style tagları yok)
     */
    public function get_verification_css() {
        $css = '';

        // CSS Variables
        $css .= ':root {';
        foreach ($this->css_vars as $key => $value) {
            $css .= '--evf-' . str_replace('_', '-', $key) . ': ' . esc_attr($value) . ';';
        }
        $css .= '--evf-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);';
        $css .= '--evf-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);';
        $css .= '--evf-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);';
        $css .= '}';

        // Base styles
        $css .= $this->get_base_styles();

        // Layout styles
        $css .= $this->get_layout_styles();

        // Form styles
        $css .= $this->get_form_styles();

        // Button styles
        $css .= $this->get_button_styles();

        // Message styles
        $css .= $this->get_message_styles();

        // Progress bar styles
        $css .= $this->get_progress_styles();

        // Animation styles
        $css .= $this->get_animation_styles();

        // Responsive styles
        $css .= $this->get_responsive_styles();

        // Accessibility styles
        $css .= $this->get_accessibility_styles();

        return $css;
    }

    /**
     * Base CSS styles
     */
    private function get_base_styles() {
        return '
        * { 
            box-sizing: border-box; 
        }

        .evf-verification-body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.6;
            color: var(--evf-gray-800);
        }

        .evf-success-page {
            background: linear-gradient(135deg, var(--evf-success) 0%, #059669 100%);
        }

        .evf-container {
            width: 100%;
            max-width: 480px;
        }

        .evf-card {
            background: var(--evf-white);
            border-radius: var(--evf-border-radius);
            box-shadow: var(--evf-shadow-lg);
            padding: 40px;
            position: relative;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            animation: evf-slide-in 0.5s ease-out forwards;
        }

        .evf-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--evf-primary), #6366f1);
        }
        ';
    }

    /**
     * Layout CSS styles
     */
    private function get_layout_styles() {
        return '
        .evf-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .evf-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            animation: evf-bounce 2s infinite;
        }

        .evf-title {
            color: var(--evf-gray-900);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 12px 0;
        }

        .evf-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin: 0;
            font-weight: 400;
        }

        .evf-description {
            color: var(--evf-gray-500);
            margin: 0;
            line-height: 1.5;
        }

        .evf-email {
            color: var(--evf-primary);
            font-weight: 600;
            word-break: break-word;
        }

        .evf-content {
            padding: 32px 24px;
        }

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
        ';
    }

    /**
     * Form CSS styles
     */
    private function get_form_styles() {
        return '
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
            font-family: "Monaco", "Consolas", "Courier New", monospace;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.25rem;
            background: var(--evf-gray-50);
            font-weight: 600;
            padding: 20px 16px;
        }

        .evf-code-input.error {
            border-color: var(--evf-error);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .evf-input-help {
            font-size: 12px;
            color: var(--evf-gray-500);
            text-align: center;
            margin-top: 8px;
        }

        .evf-code-input-wrapper {
            position: relative;
        }

        .evf-code-input-help {
            font-size: 12px;
            color: var(--evf-gray-500);
            margin-top: 4px;
            text-align: center;
        }
        ';
    }

    /**
     * Button CSS styles
     */
    private function get_button_styles() {
        return '
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
            line-height: 1.5;
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

        .evf-btn-text,
        .evf-btn-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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

        .evf-button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 32px 0;
            flex-wrap: wrap;
        }
        ';
    }

    /**
     * Message CSS styles
     */
    private function get_message_styles() {
        return '
        .evf-message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
            display: none;
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

        .evf-message.evf-message-info {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #3b82f6;
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
        ';
    }

    /**
     * Progress bar CSS styles
     */
    private function get_progress_styles() {
        return '
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
            content: "";
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
        ';
    }

    /**
     * Animation CSS styles
     */
    private function get_animation_styles() {
        return '
        @keyframes evf-slide-in {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes evf-bounce {
            0%, 20%, 53%, 80%, 100% { 
                transform: translate3d(0,0,0); 
            }
            40%, 43% { 
                transform: translate3d(0,-15px,0); 
            }
            70% { 
                transform: translate3d(0,-7px,0); 
            }
            90% { 
                transform: translate3d(0,-2px,0); 
            }
        }

        @keyframes evf-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes evf-scale-in {
            to { transform: scale(1); }
        }

        @keyframes evf-checkmark {
            to { stroke-dashoffset: 0; }
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

        .evf-success-icon-wrapper {
            margin-bottom: 24px;
        }

        .evf-success-card {
            text-align: center;
        }

        .evf-success-title {
            color: var(--evf-success);
            font-size: 1.75rem;
            margin-bottom: 16px;
        }

        .evf-additional-links {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--evf-gray-200);
        }
        ';
    }

    /**
     * Responsive CSS styles
     */
    private function get_responsive_styles() {
        return '
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

            .evf-content {
                padding: 24px 20px;
            }
            
            .evf-header {
                padding: 24px 20px;
            }
            
            .evf-title {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .evf-verification-body {
                padding: 10px;
            }
            
            .evf-card {
                padding: 20px 16px;
            }
        }
        ';
    }

    /**
     * Accessibility CSS styles
     */
    private function get_accessibility_styles() {
        return '
        @media (prefers-contrast: high) {
            :root {
                --evf-primary: #0000ff;
                --evf-success: #008000;
                --evf-error: #ff0000;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        .evf-help-details {
            margin-bottom: 24px;
            border: 1px solid var(--evf-gray-200);
            border-radius: 8px;
            overflow: hidden;
        }

        .evf-help-details summary {
            padding: 16px;
            background: var(--evf-gray-50);
            cursor: pointer;
            font-weight: 600;
            color: var(--evf-gray-700);
            user-select: none;
            list-style: none;
        }

        .evf-help-details summary:hover {
            background: var(--evf-gray-100);
        }

        .evf-help-details summary::-webkit-details-marker {
            display: none;
        }

        .evf-help-content {
            padding: 16px;
        }

        .evf-help-content ul {
            margin: 0;
            padding-left: 20px;
            color: var(--evf-gray-600);
        }

        .evf-help-content li {
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .evf-countdown-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--evf-gray-600);
        }

        .evf-countdown-timer {
            font-weight: 600;
            color: var(--evf-primary);
        }
        ';
    }

    /**
     * Default CSS (minimal)
     */
    private function get_default_css() {
        return '
        .evf-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .evf-card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .evf-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        .evf-btn:hover {
            background: #2563eb;
        }
        ';
    }

    /**
     * CSS files generate et
     */
    public function generate_css_files() {
        $upload_dir = wp_upload_dir();
        $css_dir = $upload_dir['basedir'] . '/evf-css/';

        // Klasör oluştur
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }

        // Verification CSS file
        $verification_css = $this->get_verification_css();
        file_put_contents($css_dir . 'verification.css', $verification_css);

        // .htaccess for caching
        $htaccess_content = "
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css \"access plus 1 month\"
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch \"\.(css)$\">
        Header set Cache-Control \"public, max-age=2592000\"
    </FilesMatch>
</IfModule>
        ";

        file_put_contents($css_dir . '.htaccess', $htaccess_content);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF: CSS files generated successfully');
        }
    }
}