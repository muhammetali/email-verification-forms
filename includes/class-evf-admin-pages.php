<?php
/**
 * EVF Admin Pages Class - DÜZELTME
 * Admin sayfaları (Kayıtlar, Loglar, Ayarlar, Araçlar)
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Admin_Pages {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Constructor boş - sadece static method'lar kullanıyoruz
    }

    /**
     * Kayıtlar sayfası
     */
    public static function registrations_page() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'evf_pending_registrations';

        // Sayfalama
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Filtreleme
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $where_clause = '';
        $where_values = array();

        if ($status_filter && in_array($status_filter, array('pending', 'email_verified', 'completed', 'expired'))) {
            $where_clause = 'WHERE status = %s';
            $where_values[] = $status_filter;
        }

        // Toplam kayıt sayısı
        $total_query = "SELECT COUNT(*) FROM $table_name $where_clause";
        $total_items = $where_values ? $wpdb->get_var($wpdb->prepare($total_query, $where_values)) : $wpdb->get_var($total_query);
        $total_pages = ceil($total_items / $per_page);

        // Verileri getir
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $registrations = $wpdb->get_results($wpdb->prepare($query, $query_values));
        ?>

        <div class="wrap evf-admin-wrap">
            <h1><?php esc_html_e('Kayıt Denemeleri', 'email-verification-forms'); ?></h1>

            <!-- Filters -->
            <div class="evf-filters">
                <form method="get">
                    <input type="hidden" name="page" value="evf-registrations">
                    <select name="status" onchange="this.form.submit()">
                        <option value=""><?php esc_html_e('Tüm Durumlar', 'email-verification-forms'); ?></option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Bekleyen', 'email-verification-forms'); ?></option>
                        <option value="email_verified" <?php selected($status_filter, 'email_verified'); ?>><?php esc_html_e('Email Doğrulandı', 'email-verification-forms'); ?></option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Tamamlandı', 'email-verification-forms'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Süresi Doldu', 'email-verification-forms'); ?></option>
                    </select>
                </form>
            </div>

            <!-- Table -->
            <table class="wp-list-table widefat fixed striped evf-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th><?php esc_html_e('E-posta', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('Durum', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('IP Adresi', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('Oluşturulma', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('Son Tarih', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('İşlemler', 'email-verification-forms'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($registrations)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">
                            <?php esc_html_e('Kayıt bulunamadı.', 'email-verification-forms'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registrations as $registration): ?>
                        <tr>
                            <td><?php echo esc_html($registration->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($registration->email); ?></strong>
                                <?php if ($registration->user_id): ?>
                                    <br><small>User ID: #<?php echo esc_html($registration->user_id); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                    <span class="evf-status evf-status-<?php echo esc_attr($registration->status); ?>">
                                        <?php
                                        switch ($registration->status) {
                                            case 'pending':
                                                esc_html_e('Bekleyen', 'email-verification-forms');
                                                break;
                                            case 'email_verified':
                                                esc_html_e('Email Doğrulandı', 'email-verification-forms');
                                                break;
                                            case 'completed':
                                                esc_html_e('Tamamlandı', 'email-verification-forms');
                                                break;
                                            case 'expired':
                                                esc_html_e('Süresi Doldu', 'email-verification-forms');
                                                break;
                                        }
                                        ?>
                                    </span>
                            </td>
                            <td><?php echo esc_html($registration->ip_address); ?></td>
                            <td><?php echo esc_html(gmdate('d.m.Y H:i', strtotime($registration->created_at))); ?></td>
                            <td><?php echo esc_html(gmdate('d.m.Y H:i', strtotime($registration->expires_at))); ?></td>
                            <td>
                                <div class="evf-actions">
                                    <?php if ($registration->status === 'pending'): ?>
                                        <button class="button button-small evf-resend-email" data-email="<?php echo esc_attr($registration->email); ?>">
                                            <?php esc_html_e('Yeniden Gönder', 'email-verification-forms'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button class="button button-small evf-view-details" data-id="<?php echo esc_attr($registration->id); ?>">
                                        <?php esc_html_e('Detay', 'email-verification-forms'); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = admin_url('admin.php?page=evf-registrations');
                        if ($status_filter) {
                            $base_url = add_query_arg('status', $status_filter, $base_url);
                        }

                        echo wp_kses_post(paginate_links(array(
                            'base' => add_query_arg('paged', '%#%', $base_url),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        )));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Email logs sayfası
     */
    public static function email_logs_page() {
        global $wpdb;

        $log_table = $wpdb->prefix . 'evf_email_logs';

        // Sayfalama
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Toplam log sayısı
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $log_table");
        $total_pages = ceil($total_items / $per_page);

        // Logları getir
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $log_table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        ?>

        <div class="wrap evf-admin-wrap">
            <h1><?php esc_html_e('Email Logları', 'email-verification-forms'); ?></h1>

            <table class="wp-list-table widefat fixed striped evf-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th><?php esc_html_e('E-posta', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('Tür', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('Durum', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('Tarih', 'email-verification-forms'); ?></th>
                    <th><?php esc_html_e('Hata', 'email-verification-forms'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">
                            <?php esc_html_e('Log bulunamadı.', 'email-verification-forms'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->id); ?></td>
                            <td><?php echo esc_html($log->email); ?></td>
                            <td>
                                    <span class="evf-email-type evf-type-<?php echo esc_attr($log->email_type); ?>">
                                        <?php
                                        switch ($log->email_type) {
                                            case 'verification':
                                                esc_html_e('Doğrulama', 'email-verification-forms');
                                                break;
                                            case 'welcome':
                                                esc_html_e('Hoş Geldin', 'email-verification-forms');
                                                break;
                                            case 'admin_notification':
                                                esc_html_e('Admin Bildirimi', 'email-verification-forms');
                                                break;
                                        }
                                        ?>
                                    </span>
                            </td>
                            <td>
                                    <span class="evf-email-status evf-status-<?php echo esc_attr($log->status); ?>">
                                        <?php echo esc_html($log->status === 'sent' ? __('Gönderildi', 'email-verification-forms') : __('Başarısız', 'email-verification-forms')); ?>
                                    </span>
                            </td>
                            <td><?php echo esc_html(gmdate('d.m.Y H:i:s', strtotime($log->created_at))); ?></td>
                            <td>
                                <?php if ($log->error_message): ?>
                                    <span class="evf-error-message" title="<?php echo esc_attr($log->error_message); ?>">
                                            <?php esc_html_e('Hata var', 'email-verification-forms'); ?>
                                        </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        )));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Ayarlar sayfası
     */
    public static function settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('evf_settings_nonce');

            // Mevcut ayarlar
            update_option('evf_token_expiry', intval($_POST['evf_token_expiry']));
            update_option('evf_rate_limit', intval($_POST['evf_rate_limit']));
            update_option('evf_min_password_length', intval($_POST['evf_min_password_length']));
            update_option('evf_require_strong_password', isset($_POST['evf_require_strong_password']));
            update_option('evf_admin_notifications', isset($_POST['evf_admin_notifications']));
            update_option('evf_redirect_after_login', esc_url_raw($_POST['evf_redirect_after_login']));
            update_option('evf_brand_color', sanitize_hex_color($_POST['evf_brand_color']));
            update_option('evf_email_from_name', sanitize_text_field($_POST['evf_email_from_name']));
            update_option('evf_email_from_email', sanitize_email($_POST['evf_email_from_email']));

            // YENİ AYARLAR - Kod Doğrulama
            update_option('evf_verification_type', sanitize_text_field($_POST['evf_verification_type']));
            update_option('evf_code_resend_interval', intval($_POST['evf_code_resend_interval']));
            update_option('evf_max_code_attempts', intval($_POST['evf_max_code_attempts']));
            update_option('evf_auto_delete_unverified', isset($_POST['evf_auto_delete_unverified']));
            update_option('evf_auto_delete_hours', intval($_POST['evf_auto_delete_hours']));

            echo '<div class="notice notice-success"><p>' . esc_html__('Ayarlar kaydedildi!', 'email-verification-forms') . '</p></div>';
        }

        // Mevcut ayarları getir (DÜZELTME: Tüm değişkenleri tanımla)
        $token_expiry = get_option('evf_token_expiry', 24);
        $rate_limit = get_option('evf_rate_limit', 15);
        $min_password_length = get_option('evf_min_password_length', 8);
        $require_strong_password = get_option('evf_require_strong_password', true);
        $admin_notifications = get_option('evf_admin_notifications', true);
        $redirect_after_login = get_option('evf_redirect_after_login', home_url());
        $brand_color = get_option('evf_brand_color', '#96588a');
        $email_from_name = get_option('evf_email_from_name', get_bloginfo('name'));
        $email_from_email = get_option('evf_email_from_email', get_option('admin_email'));

        // Yeni ayarlar
        $verification_type = get_option('evf_verification_type', 'link');
        $code_resend_interval = get_option('evf_code_resend_interval', 2);
        $max_code_attempts = get_option('evf_max_code_attempts', 5);
        $auto_delete_unverified = get_option('evf_auto_delete_unverified', false);
        $auto_delete_hours = get_option('evf_auto_delete_hours', 24);
        ?>

        <div class="wrap evf-admin-wrap">
            <h1><?php esc_html_e('Email Verification Ayarları', 'email-verification-forms'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('evf_settings_nonce'); ?>

                <div class="evf-settings-tabs">
                    <div class="evf-tab-nav">
                        <button type="button" class="evf-tab-btn active" data-tab="general"><?php esc_html_e('Genel', 'email-verification-forms'); ?></button>
                        <button type="button" class="evf-tab-btn" data-tab="email"><?php esc_html_e('E-posta', 'email-verification-forms'); ?></button>
                        <button type="button" class="evf-tab-btn" data-tab="security"><?php esc_html_e('Güvenlik', 'email-verification-forms'); ?></button>
                        <button type="button" class="evf-tab-btn" data-tab="code-verification"><?php esc_html_e('Kod Doğrulama', 'email-verification-forms'); ?></button>
                        <button type="button" class="evf-tab-btn" data-tab="design"><?php esc_html_e('Tasarım', 'email-verification-forms'); ?></button>
                    </div>

                    <script>
                        jQuery(document).ready(function($) {
                            // Doğrulama türü değiştiğinde kod ayarları tab'ının durumunu güncelle
                            function updateCodeVerificationTab() {
                                var verificationType = $('input[name="evf_verification_type"]:checked').val();
                                var $codeTab = $('.evf-tab-btn[data-tab="code-verification"]');

                                if (verificationType === 'code') {
                                    $codeTab.show().removeClass('disabled');
                                } else {
                                    $codeTab.hide();
                                    // Eğer kod tab'ı aktifse genel tab'a geç
                                    if ($codeTab.hasClass('active')) {
                                        $('.evf-tab-btn[data-tab="general"]').click();
                                    }
                                }
                            }

                            // Sayfa yüklendiğinde ve değiştiğinde kontrol et
                            updateCodeVerificationTab();
                            $('input[name="evf_verification_type"]').on('change', updateCodeVerificationTab);

                            // Tab'ların görünürlüğünü başlangıçta ayarla
                            setTimeout(updateCodeVerificationTab, 100);
                        });
                    </script>

                    <!-- General Tab -->
                    <div class="evf-tab-content active" id="tab-general">
                        <h3><?php esc_html_e('Genel Ayarlar', 'email-verification-forms'); ?></h3>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Token Geçerlilik Süresi', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_token_expiry" value="<?php echo esc_attr($token_expiry); ?>" min="1" max="168" />
                                    <p class="description"><?php esc_html_e('Doğrulama bağlantısının geçerlilik süresi (saat)', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Kayıt Sonrası Yönlendirme', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="url" name="evf_redirect_after_login" value="<?php echo esc_attr($redirect_after_login); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Kullanıcı başarıyla kayıt olduktan sonra yönlendirilecek sayfa', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Admin Bildirimleri', 'email-verification-forms'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="evf_admin_notifications" <?php checked($admin_notifications); ?> />
                                        <?php esc_html_e('Yeni kayıtlar için admin\'e e-posta gönder', 'email-verification-forms'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Doğrulama Türü', 'email-verification-forms'); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="radio" name="evf_verification_type" value="link" <?php checked($verification_type, 'link'); ?> />
                                            <strong><?php esc_html_e('E-posta Bağlantısı', 'email-verification-forms'); ?></strong>
                                            <br><small><?php esc_html_e('Kullanıcıya e-posta ile doğrulama bağlantısı gönderilir (Mevcut sistem)', 'email-verification-forms'); ?></small>
                                        </label>
                                        <br><br>
                                        <label>
                                            <input type="radio" name="evf_verification_type" value="code" <?php checked($verification_type, 'code'); ?> />
                                            <strong><?php esc_html_e('Doğrulama Kodu', 'email-verification-forms'); ?></strong>
                                            <br><small><?php esc_html_e('Kullanıcıya e-posta ile 6 haneli doğrulama kodu gönderilir', 'email-verification-forms'); ?></small>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Email Tab -->
                    <div class="evf-tab-content" id="tab-email">
                        <h3><?php esc_html_e('E-posta Ayarları', 'email-verification-forms'); ?></h3>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Gönderen Adı', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="text" name="evf_email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text" />
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Gönderen E-posta', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="email" name="evf_email_from_email" value="<?php echo esc_attr($email_from_email); ?>" class="regular-text" />
                                </td>
                            </tr>
                        </table>

                        <div class="evf-test-email-section">
                            <h4><?php esc_html_e('E-posta Testi', 'email-verification-forms'); ?></h4>
                            <p><?php esc_html_e('E-posta şablonlarını test etmek için bir e-posta adresi girin:', 'email-verification-forms'); ?></p>
                            <input type="email" id="test-email-address" placeholder="test@example.com" />
                            <select id="test-email-type">
                                <option value="verification"><?php esc_html_e('Doğrulama E-postası', 'email-verification-forms'); ?></option>
                                <option value="welcome"><?php esc_html_e('Hoş Geldin E-postası', 'email-verification-forms'); ?></option>
                                <option value="admin-notification"><?php esc_html_e('Admin Bildirimi', 'email-verification-forms'); ?></option>
                            </select>
                            <button type="button" class="button" id="send-test-email"><?php esc_html_e('Test E-postası Gönder', 'email-verification-forms'); ?></button>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="evf-tab-content" id="tab-security">
                        <h3><?php esc_html_e('Güvenlik Ayarları', 'email-verification-forms'); ?></h3>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Rate Limiting', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="60" />
                                    <p class="description"><?php esc_html_e('Aynı kullanıcının tekrar kayıt denemesi için bekleme süresi (dakika)', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Minimum Parola Uzunluğu', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_min_password_length" value="<?php echo esc_attr($min_password_length); ?>" min="6" max="50" />
                                    <p class="description"><?php esc_html_e('Kullanıcıların seçebileceği minimum parola uzunluğu', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Güçlü Parola Zorunluluğu', 'email-verification-forms'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="evf_require_strong_password" <?php checked($require_strong_password); ?> />
                                        <?php esc_html_e('Parolada büyük harf, küçük harf ve rakam zorunlu olsun', 'email-verification-forms'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Design Tab -->
                    <div class="evf-tab-content" id="tab-design">
                        <h3><?php esc_html_e('Tasarım Ayarları', 'email-verification-forms'); ?></h3>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Marka Rengi', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="color" name="evf_brand_color" value="<?php echo esc_attr($brand_color); ?>" />
                                    <p class="description"><?php esc_html_e('E-posta şablonları ve formlar için ana renk', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <div class="evf-color-preview">
                            <h4><?php esc_html_e('Renk Önizleme', 'email-verification-forms'); ?></h4>
                            <div class="evf-preview-button" style="background-color: <?php echo esc_attr($brand_color); ?>;">
                                <?php esc_html_e('Örnek Buton', 'email-verification-forms'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="evf-tab-content" id="tab-code-verification">
                        <h3><?php esc_html_e('Kod Doğrulama Ayarları', 'email-verification-forms'); ?></h3>

                        <div id="code-verification-notice" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
                            <p><strong><?php esc_html_e('Bilgi:', 'email-verification-forms'); ?></strong> <?php esc_html_e('Bu ayarlar sadece "Doğrulama Kodu" seçeneği aktif olduğunda geçerlidir.', 'email-verification-forms'); ?></p>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Kod Tekrar Gönderme Aralığı', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_code_resend_interval" value="<?php echo esc_attr($code_resend_interval); ?>" min="1" max="10" /> <?php esc_html_e('dakika', 'email-verification-forms'); ?>
                                    <p class="description"><?php esc_html_e('Kullanıcı kaç dakikada bir yeni kod isteyebilir', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Maksimum Kod Deneme Sayısı', 'email-verification-forms'); ?></th>
                                <td>
                                    <input type="number" name="evf_max_code_attempts" value="<?php echo esc_attr($max_code_attempts); ?>" min="3" max="10" />
                                    <p class="description"><?php esc_html_e('Kullanıcı kaç kere yanlış kod girebilir (sonrasında kayıt iptal olur)', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Otomatik Hesap Silme', 'email-verification-forms'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="evf_auto_delete_unverified" <?php checked($auto_delete_unverified); ?> />
                                        <?php esc_html_e('Doğrulanmayan hesapları otomatik sil', 'email-verification-forms'); ?>
                                    </label>
                                    <br><br>
                                    <label>
                                        <?php esc_html_e('Silme süresi:', 'email-verification-forms'); ?>
                                        <select name="evf_auto_delete_hours">
                                            <option value="24" <?php selected($auto_delete_hours, 24); ?>><?php esc_html_e('24 saat', 'email-verification-forms'); ?></option>
                                            <option value="48" <?php selected($auto_delete_hours, 48); ?>><?php esc_html_e('48 saat', 'email-verification-forms'); ?></option>
                                            <option value="72" <?php selected($auto_delete_hours, 72); ?>><?php esc_html_e('72 saat', 'email-verification-forms'); ?></option>
                                            <option value="168" <?php selected($auto_delete_hours, 168); ?>><?php esc_html_e('1 hafta', 'email-verification-forms'); ?></option>
                                        </select>
                                    </label>
                                    <p class="description"><?php esc_html_e('Bu süre sonunda doğrulanmayan hesaplar tamamen silinir', 'email-verification-forms'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="<?php esc_attr_e('Ayarları Kaydet', 'email-verification-forms'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Araçlar sayfası
     */
    public static function tools_page() {
        ?>
        <div class="wrap evf-admin-wrap">
            <h1><?php esc_html_e('Araçlar', 'email-verification-forms'); ?></h1>

            <div class="evf-tools-grid">
                <!-- Export Tool -->
                <div class="evf-tool-card">
                    <h3><?php esc_html_e('Veri Dışa Aktarma', 'email-verification-forms'); ?></h3>
                    <p><?php esc_html_e('Kayıt verilerini CSV formatında dışa aktarın.', 'email-verification-forms'); ?></p>
                    <form method="post" class="evf-export-form">
                        <label>
                            <input type="radio" name="export_type" value="all" checked>
                            <?php esc_html_e('Tüm kayıtlar', 'email-verification-forms'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="export_type" value="completed">
                            <?php esc_html_e('Sadece tamamlanan kayıtlar', 'email-verification-forms'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="export_type" value="pending">
                            <?php esc_html_e('Sadece bekleyen kayıtlar', 'email-verification-forms'); ?>
                        </label><br><br>
                        <button type="button" class="button button-primary" id="export-data">
                            <?php esc_html_e('Dışa Aktar', 'email-verification-forms'); ?>
                        </button>
                    </form>
                </div>

                <!-- Cleanup Tool -->
                <div class="evf-tool-card">
                    <h3><?php esc_html_e('Veritabanı Temizliği', 'email-verification-forms'); ?></h3>
                    <p><?php esc_html_e('Süresi dolmuş kayıtları ve eski logları temizleyin.', 'email-verification-forms'); ?></p>
                    <button type="button" class="button" id="cleanup-database">
                        <?php esc_html_e('Temizlemeyi Çalıştır', 'email-verification-forms'); ?>
                    </button>
                </div>

                <!-- Stats Reset -->
                <div class="evf-tool-card">
                    <h3><?php esc_html_e('İstatistik Sıfırlama', 'email-verification-forms'); ?></h3>
                    <p><?php esc_html_e('Tüm istatistikleri ve logları sıfırlayın.', 'email-verification-forms'); ?></p>
                    <button type="button" class="button button-secondary" id="reset-stats">
                        <?php esc_html_e('İstatistikleri Sıfırla', 'email-verification-forms'); ?>
                    </button>
                </div>

                <!-- System Info -->
                <div class="evf-tool-card evf-full-width">
                    <h3><?php esc_html_e('Sistem Bilgileri', 'email-verification-forms'); ?></h3>
                    <?php self::render_system_info(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Sistem bilgilerini render et
     */
    private static function render_system_info() {
        global $wpdb;

        $info = array(
            'WordPress Version' => get_bloginfo('version'),
            'PHP Version' => PHP_VERSION,
            'MySQL Version' => $wpdb->db_version(),
            'Plugin Version' => defined('EVF_VERSION') ? EVF_VERSION : '1.0.0',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . ' seconds',
            'Mail Function' => function_exists('mail') ? 'Available' : 'Not Available',
            'WP Mail Function' => function_exists('wp_mail') ? 'Available' : 'Not Available'
        );

        echo '<table class="evf-system-info">';
        foreach ($info as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($key) . ':</strong></td>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
}