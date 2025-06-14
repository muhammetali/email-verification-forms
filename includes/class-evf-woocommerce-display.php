<?php
/**
 * EVF WooCommerce Display Handler - ANA SINIF
 * Template display işlemleri - CSS hatası düzeltildi ve bölümlere ayrıldı
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_Display {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->load_components();
    }

    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_verification_assets'), 100);
    }

    /**
     * Alt bileşenleri yükle
     */
    private function load_components() {
        // CSS Handler
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-display-css.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-display-css.php';
        }

        // JavaScript Handler
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-display-js.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-display-js.php';
        }

        // Template Handler
        if (file_exists(EVF_INCLUDES_PATH . 'class-evf-woocommerce-display-templates.php')) {
            require_once EVF_INCLUDES_PATH . 'class-evf-woocommerce-display-templates.php';
        }
    }

    /**
     * DÜZELTİLMİŞ: Verification sayfaları için CSS/JS assets
     */
    public function enqueue_verification_assets() {
        if (defined('EVF_CODE_VERIFICATION_PAGE')) {
            // CSS'i external file olarak yükle (inline değil)
            wp_enqueue_style(
                'evf-verification-style',
                $this->get_verification_css_url(),
                array(),
                EVF_VERSION
            );
        }
    }

    /**
     * CSS dosyası URL'ini getir
     */
    private function get_verification_css_url() {
        // CSS dosyası varsa onu kullan
        $css_file = EVF_ASSETS_URL . 'css/evf-verification.css';
        if (file_exists(str_replace(EVF_PLUGIN_URL, EVF_PLUGIN_DIR, $css_file))) {
            return $css_file;
        }

        // Fallback: Dynamic CSS
        return add_query_arg(array(
            'action' => 'evf_dynamic_css',
            'type' => 'verification',
            'v' => EVF_VERSION
        ), admin_url('admin-ajax.php'));
    }

    /**
     * Ana kod doğrulama sayfası handler'ı
     */
    public function show_code_verification_page($registration) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EVF WooCommerce Display: show_code_verification_page called for email: ' . $registration->email);
        }

        // Registration object mı email string mi kontrol et
        if (is_string($registration)) {
            $email = $registration;
            $registration = $this->get_registration_by_email($email);
        } else {
            $email = $registration->email;
        }

        if (!$registration) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce Display: No registration found, redirecting with error');
            }
            wp_redirect(add_query_arg('evf_error', 'registration_not_found', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Status kontrolü
        if ($registration->status === 'completed') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce Display: Registration already completed, showing already verified page');
            }
            $this->show_already_verified_page($email);
            return;
        }

        if ($registration->status !== 'pending') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EVF WooCommerce Display: Invalid registration status: ' . $registration->status);
            }
            wp_redirect(add_query_arg('evf_error', 'invalid_status', wc_get_page_permalink('myaccount')));
            exit;
        }

        // Template'i göster
        $this->render_code_verification_page($registration);
    }

    /**
     * Zaten doğrulanmış sayfası
     */
    public function show_already_verified_page($email) {
        $template_path = EVF_TEMPLATES_PATH . 'already-verified.php';

        if (file_exists($template_path)) {
            include $template_path;
            exit;
        } else {
            $this->render_already_verified_page($email);
        }
    }

    /**
     * Kod doğrulama sayfasını render et
     */
    private function render_code_verification_page($registration) {
        // Template handler'ı kullan
        if (class_exists('EVF_WooCommerce_Display_Templates')) {
            $template_handler = new EVF_WooCommerce_Display_Templates();
            $template_handler->render_code_verification($registration);
        } else {
            // Fallback - basic template
            $this->render_basic_code_verification($registration);
        }
    }

    /**
     * Zaten doğrulanmış sayfasını render et
     */
    private function render_already_verified_page($email) {
        // Template handler'ı kullan
        if (class_exists('EVF_WooCommerce_Display_Templates')) {
            $template_handler = new EVF_WooCommerce_Display_Templates();
            $template_handler->render_already_verified($email);
        } else {
            // Fallback - basic template
            $this->render_basic_already_verified($email);
        }
    }

    /**
     * Email ile registration'ı getir
     */
    private function get_registration_by_email($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE email = %s 
             AND verification_type = 'code' 
             ORDER BY created_at DESC 
             LIMIT 1",
            $email
        ));
    }

    /**
     * Basit kod doğrulama fallback
     */
    private function render_basic_code_verification($registration) {
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html(get_bloginfo('name')); ?> - E-posta Doğrulama</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f0f0f0;
                    padding: 20px;
                    margin: 0;
                }
                .container {
                    max-width: 400px;
                    margin: 0 auto;
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .title {
                    color: #333;
                    text-align: center;
                    margin-bottom: 20px;
                }
                .description {
                    text-align: center;
                    color: #666;
                    margin-bottom: 30px;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                .form-input {
                    width: 100%;
                    padding: 15px;
                    border: 2px solid #ddd;
                    border-radius: 6px;
                    font-size: 18px;
                    text-align: center;
                    box-sizing: border-box;
                }
                .btn {
                    width: 100%;
                    padding: 15px;
                    background: #3b82f6;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    font-size: 16px;
                    cursor: pointer;
                }
                .btn:hover {
                    background: #2563eb;
                }
                .email {
                    color: #3b82f6;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
        <div class="container">
            <h1 class="title">E-posta Doğrulama</h1>
            <p class="description">
                <span class="email"><?php echo esc_html($registration->email); ?></span>
                adresine gönderilen 6 haneli kodu girin.
            </p>
            <form id="verification-form">
                <div class="form-group">
                    <input type="text"
                           id="code-input"
                           class="form-input"
                           placeholder="123456"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           required>
                </div>
                <button type="submit" class="btn">Kodu Doğrula</button>
            </form>
        </div>

        <script>
            // Basic JavaScript functionality
            document.getElementById('verification-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const code = document.getElementById('code-input').value;

                if (code.length !== 6) {
                    alert('Lütfen 6 haneli kodu girin.');
                    return;
                }

                // AJAX call would go here
                console.log('Code submitted:', code);
            });
        </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Basit zaten doğrulanmış fallback
     */
    private function render_basic_already_verified($email) {
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <title><?php echo esc_html(get_bloginfo('name')); ?> - E-posta Doğrulandı</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f0f0f0;
                    padding: 20px;
                    margin: 0;
                }
                .container {
                    max-width: 400px;
                    margin: 0 auto;
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .success-icon {
                    font-size: 4rem;
                    color: #10b981;
                    margin-bottom: 20px;
                }
                .title {
                    color: #10b981;
                    margin-bottom: 20px;
                }
                .description {
                    color: #666;
                    margin-bottom: 30px;
                }
                .btn {
                    display: inline-block;
                    padding: 15px 30px;
                    background: #3b82f6;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    font-size: 16px;
                }
                .email {
                    color: #3b82f6;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
        <div class="container">
            <div class="success-icon">✅</div>
            <h1 class="title">E-posta Doğrulandı!</h1>
            <p class="description">
                <span class="email"><?php echo esc_html($email); ?></span>
                adresi zaten doğrulanmış.
            </p>
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="btn">
                Hesabıma Git
            </a>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}