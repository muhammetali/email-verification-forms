<?php
/**
 * EVF WooCommerce UI Class
 * WooCommerce kullanıcı arayüzü ve My Account işlemleri
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_WooCommerce_UI {

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
     * Hook'ları başlat
     */
    private function init_hooks() {
        // My Account menü ve içerik
        add_filter('woocommerce_account_menu_items', array($this, 'add_verification_menu_item'), 40);
        add_action('woocommerce_account_email-verification_endpoint', array($this, 'verification_endpoint_content'));

        // Dashboard ve form hooks
        add_action('woocommerce_register_form_end', array($this, 'add_registration_notice'));
        add_action('woocommerce_account_dashboard', array($this, 'show_verification_notice'), 5);

        // Kısıtlamalar
        add_action('woocommerce_account_content', array($this, 'restrict_account_sections'), 5);
        add_filter('woocommerce_account_menu_items', array($this, 'filter_account_menu'), 10, 1);
        add_action('woocommerce_save_account_details_errors', array($this, 'restrict_profile_updates'), 10, 1);

        // AJAX handlers
        add_action('wp_ajax_evf_wc_resend_verification', array($this, 'ajax_resend_verification'));
        add_action('wp_ajax_nopriv_evf_wc_resend_verification', array($this, 'ajax_resend_verification'));

        // Admin hooks
        add_action('show_user_profile', array($this, 'add_verification_status_field'));
        add_action('edit_user_profile', array($this, 'add_verification_status_field'));
        add_action('personal_options_update', array($this, 'save_verification_status_field'));
        add_action('edit_user_profile_update', array($this, 'save_verification_status_field'));
    }

    /**
     * My Account menüsüne verification item ekle
     */
    public function add_verification_menu_item($menu_items) {
        // Sadece unverified kullanıcılar için göster
        if (!evf_is_user_verified()) {
            $menu_items['email-verification'] = __('E-posta Doğrulama', 'email-verification-forms');
        }

        return $menu_items;
    }

    /**
     * Verification endpoint içeriği
     */
    public function verification_endpoint_content() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        $is_verified = evf_is_user_verified($user_id);

        if ($is_verified) {
            // Zaten doğrulanmış
            echo '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info">';
            echo '<strong>✅ ' . __('E-posta adresiniz doğrulanmış!', 'email-verification-forms') . '</strong><br>';
            echo __('Hesabınızın tüm özellikleri aktif.', 'email-verification-forms');
            echo '</div>';
            return;
        }

        // Verification pending
        $user = wp_get_current_user();
        $last_sent = get_user_meta($user_id, 'evf_verification_sent_at', true);
        $can_resend = true;

        if ($last_sent) {
            $time_diff = current_time('timestamp') - $last_sent;
            if ($time_diff < (get_option('evf_rate_limit', 15) * 60)) {
                $can_resend = false;
                $wait_time = ceil(((get_option('evf_rate_limit', 15) * 60) - $time_diff) / 60);
            }
        }

        echo '<div class="evf-wc-verification-section">';

        // Status box
        echo '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info evf-verification-notice">';
        echo '<h3 style="margin-top: 0;">🛡️ ' . __('E-posta Doğrulaması Gerekli', 'email-verification-forms') . '</h3>';
        /* translators: %s: User email address (wrapped in <strong> tags) */
        echo '<p>' . sprintf(__('Hesabınızın güvenliği için %s adresini doğrulamanız gerekmektedir.', 'email-verification-forms'), '<strong>' . esc_html($user->user_email) . '</strong>') . '</p>';
        echo '</div>';

        // Resend section
        echo '<div class="evf-resend-section" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 4px;">';
        echo '<h4>' . __('Doğrulama E-postası', 'email-verification-forms') . '</h4>';

        if ($can_resend) {
            echo '<p>' . __('E-posta gelmedi mi? Yeni bir doğrulama e-postası gönderebilirsiniz.', 'email-verification-forms') . '</p>';
            echo '<button type="button" class="button alt evf-resend-verification" data-user-id="' . $user_id . '">';
            echo '📧 ' . __('Doğrulama E-postası Gönder', 'email-verification-forms');
            echo '</button>';
        } else {
            /* translators: %d: Number of minutes to wait */
            echo '<p style="color: #666;">' . sprintf(__('Yeni e-posta göndermek için %d dakika beklemeniz gerekiyor.', 'email-verification-forms'), $wait_time) . '</p>';
            echo '<button type="button" class="button" disabled>';
            /* translators: %d: Number of minutes to wait */
            echo '⏳ ' . sprintf(__('%d dakika bekleyin', 'email-verification-forms'), $wait_time);
            echo '</button>';
        }

        echo '</div>';

        // Help section
        echo '<div class="evf-help-section" style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
        echo '<h4 style="margin-top: 0;">💡 ' . __('E-posta gelmedi mi?', 'email-verification-forms') . '</h4>';
        echo '<ul style="margin: 10px 0 0 20px;">';
        echo '<li>' . __('Spam/Junk klasörünüzü kontrol edin', 'email-verification-forms') . '</li>';
        echo '<li>' . __('E-posta adresinizi doğru yazdığınızdan emin olun', 'email-verification-forms') . '</li>';
        echo '<li>' . __('Birkaç dakika bekleyin, e-posta gelmesi zaman alabilir', 'email-verification-forms') . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>';

        // JavaScript for AJAX
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('.evf-resend-verification').on('click', function(e) {
                    e.preventDefault();

                    var $btn = $(this);
                    var userId = $btn.data('user-id');

                    $btn.prop('disabled', true).html('📤 Gönderiliyor...');

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'evf_wc_resend_verification',
                            user_id: userId,
                            nonce: '<?php echo wp_create_nonce('evf_wc_resend'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $btn.html('✅ Gönderildi!');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $btn.html('❌ Hata oluştu').prop('disabled', false);
                            }
                        },
                        error: function() {
                            $btn.html('❌ Hata oluştu').prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * My Account dashboard'da verification notice göster
     */
    public function show_verification_notice() {
        if (evf_is_user_verified()) {
            return;
        }

        $user = wp_get_current_user();

        echo '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info evf-verification-notice" style="border-left-color: #f39c12; background-color: #fef9e7;">';
        echo '<div style="display: flex; align-items: center; gap: 15px;">';
        echo '<div style="font-size: 24px;">🛡️</div>';
        echo '<div style="flex: 1;">';
        echo '<strong>' . __('E-posta Doğrulaması Gerekli', 'email-verification-forms') . '</strong><br>';
        /* translators: %s: User email address (wrapped in <strong> tags) */
        echo sprintf(__('Hesabınızın güvenliği için %s adresini doğrulamanız gerekmektedir.', 'email-verification-forms'), '<strong>' . esc_html($user->user_email) . '</strong>');
        echo '</div>';
        echo '<div>';
        echo '<a href="' . esc_url(wc_get_account_endpoint_url('email-verification')) . '" class="button alt" style="white-space: nowrap;">';
        echo '📧 ' . __('Doğrula', 'email-verification-forms');
        echo '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Kayıt formuna notice ekle
     */
    public function add_registration_notice() {
        echo '<div class="evf-registration-notice" style="margin: 15px 0; padding: 15px; background: #e8f4fd; border-left: 4px solid #2196f3; border-radius: 4px;">';
        echo '<p style="margin: 0; color: #1976d2; font-size: 14px;">';
        echo '<strong>🛡️ ' . __('Güvenlik Bildirimi:', 'email-verification-forms') . '</strong> ';
        echo __('Kayıt işlemi sonrasında e-posta adresinize bir doğrulama bağlantısı gönderilecektir.', 'email-verification-forms');
        echo '</p>';
        echo '</div>';
    }

    /**
     * Unverified kullanıcılar için hesap bölümlerine kısıtlama
     */
    public function restrict_account_sections() {
        if (evf_is_user_verified()) {
            return;
        }

        // Belirli endpoint'lerde restriction göster
        global $wp;
        $current_endpoint = isset($wp->query_vars) ? key($wp->query_vars) : '';

        $restricted_endpoints = array('edit-account', 'payment-methods', 'edit-address');

        if (in_array($current_endpoint, $restricted_endpoints)) {
            echo '<div class="woocommerce-message woocommerce-message--error woocommerce-Message woocommerce-Message--error">';
            echo '<strong>🔒 ' . __('E-posta Doğrulaması Gerekli', 'email-verification-forms') . '</strong><br>';
            echo __('Bu bölüme erişmek için önce e-posta adresinizi doğrulamanız gerekmektedir.', 'email-verification-forms') . ' ';
            echo '<a href="' . esc_url(wc_get_account_endpoint_url('email-verification')) . '">' . __('Şimdi Doğrula', 'email-verification-forms') . '</a>';
            echo '</div>';

            // İçeriği gizle
            echo '<style>.woocommerce-MyAccount-content > *:not(.woocommerce-message):not(.woocommerce-Message) { display: none !important; }</style>';
        }
    }

    /**
     * Account menüsünü filtrele
     */
    public function filter_account_menu($menu_items) {
        if (evf_is_user_verified()) {
            return $menu_items;
        }

        // Unverified kullanıcılar için menü itemlerini işaretle
        $restricted_items = array('edit-account', 'payment-methods', 'edit-address');

        foreach ($restricted_items as $item) {
            if (isset($menu_items[$item])) {
                $menu_items[$item] = $menu_items[$item] . ' 🔒';
            }
        }

        return $menu_items;
    }

    /**
     * Profil güncellemelerini kısıtla
     */
    public function restrict_profile_updates($errors) {
        if (!evf_is_user_verified()) {
            $errors->add('evf_verification_required', __('Profil bilgilerinizi güncellemek için önce e-posta doğrulaması yapmanız gerekmektedir.', 'email-verification-forms'));
        }
    }

    /**
     * Admin'de verification status field ekle
     */
    public function add_verification_status_field($user) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_verified = evf_is_user_verified($user->ID);
        $verification_sent = get_user_meta($user->ID, 'evf_verification_sent_at', true);
        ?>
        <h3><?php _e('Email Verification Status', 'email-verification-forms'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php _e('E-posta Doğrulandı', 'email-verification-forms'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="evf_email_verified" value="1" <?php checked($is_verified); ?> />
                        <?php _e('E-posta adresi doğrulanmış', 'email-verification-forms'); ?>
                    </label>
                    <br>
                    <?php if ($verification_sent): ?>
                        <small style="color: #666;">
                            <?php
                            /* translators: %s: Formatted date and time */
                            printf(__('Son doğrulama: %s', 'email-verification-forms'), date('d.m.Y H:i', $verification_sent));
                            ?>
                        </small>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Admin'de verification status field'ını kaydet
     */
    public function save_verification_status_field($user_id) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $verified = isset($_POST['evf_email_verified']) ? 1 : 0;
        update_user_meta($user_id, 'evf_email_verified', $verified);

        if ($verified) {
            update_user_meta($user_id, 'evf_verified_at', current_time('mysql'));
        }
    }

    /**
     * AJAX: Verification email'i yeniden gönder
     */
    public function ajax_resend_verification() {
        if (!wp_verify_nonce($_POST['nonce'], 'evf_wc_resend')) {
            wp_send_json_error('invalid_nonce');
        }

        $user_id = intval($_POST['user_id']);

        if (!$user_id || $user_id !== get_current_user_id()) {
            wp_send_json_error('invalid_user');
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error('user_not_found');
        }

        // Rate limiting check
        $last_sent = get_user_meta($user_id, 'evf_verification_sent_at', true);
        if ($last_sent) {
            $time_diff = current_time('timestamp') - $last_sent;
            if ($time_diff < (get_option('evf_rate_limit', 15) * 60)) {
                wp_send_json_error('rate_limit');
            }
        }

        // Yeni verification başlat - Main WooCommerce sınıfını kullan
        EVF_WooCommerce::instance()->start_email_verification($user_id, $user->user_email, array(
            'context' => 'resend'
        ));

        wp_send_json_success();
    }
}