<?php
/**
 * EVF Admin Dashboard Class
 * Admin dashboard sayfası işlemleri
 */

if (!defined('ABSPATH')) {
    exit;
}

class EVF_Admin_Dashboard {

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
     * Dashboard sayfası
     */
    public static function dashboard_page() {
        $database = EVF_Database::instance();
        $stats = $database->get_registration_stats(30);
        $email_stats = $database->get_email_stats(30);
        $daily_trend = $database->get_daily_registration_trend(30);
        ?>
        <div class="wrap evf-admin-wrap">
            <h1><?php esc_html_e('Email Verification Dashboard', 'email-verification-forms'); ?></h1>

            <!-- Stats Cards -->
            <div class="evf-stats-grid">
                <div class="evf-stat-card">
                    <div class="evf-stat-icon">📊</div>
                    <div class="evf-stat-content">
                        <h3><?php echo esc_html(number_format($stats['total_attempts'])); ?></h3>
                        <p><?php esc_html_e('Toplam Deneme', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php esc_html_e('Son 30 gün', 'email-verification-forms'); ?></span>
                    </div>
                </div>

                <div class="evf-stat-card">
                    <div class="evf-stat-icon">✅</div>
                    <div class="evf-stat-content">
                        <h3><?php echo esc_html(number_format($stats['completed'])); ?></h3>
                        <p><?php esc_html_e('Tamamlanan Kayıt', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php echo esc_html($stats['success_rate']); ?>% başarı oranı</span>
                    </div>
                </div>

                <div class="evf-stat-card">
                    <div class="evf-stat-icon">📧</div>
                    <div class="evf-stat-content">
                        <h3><?php echo esc_html(number_format($stats['email_verified'])); ?></h3>
                        <p><?php esc_html_e('Email Doğrulandı', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php echo esc_html($stats['email_verification_rate']); ?>% doğrulama oranı</span>
                    </div>
                </div>

                <div class="evf-stat-card">
                    <div class="evf-stat-icon">⏳</div>
                    <div class="evf-stat-content">
                        <h3><?php echo esc_html(number_format($stats['pending'])); ?></h3>
                        <p><?php esc_html_e('Bekleyen', 'email-verification-forms'); ?></p>
                        <span class="evf-stat-period"><?php esc_html_e('Doğrulama bekliyor', 'email-verification-forms'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="evf-charts-grid">
                <div class="evf-chart-container">
                    <h3><?php esc_html_e('Kayıt Trendi (Son 30 Gün)', 'email-verification-forms'); ?></h3>
                    <canvas id="evf-trend-chart"></canvas>
                </div>

                <div class="evf-chart-container">
                    <h3><?php esc_html_e('Email Durumu', 'email-verification-forms'); ?></h3>
                    <canvas id="evf-email-chart"></canvas>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="evf-recent-activity">
                <h3><?php esc_html_e('Son Aktiviteler', 'email-verification-forms'); ?></h3>
                <?php self::render_recent_activity(); ?>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                // Trend Chart
                const trendData = <?php echo json_encode($daily_trend); ?>;
                const trendCtx = document.getElementById('evf-trend-chart').getContext('2d');

                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendData.map(item => item.date),
                        datasets: [{
                            label: '<?php esc_html_e('Toplam Deneme', 'email-verification-forms'); ?>',
                            data: trendData.map(item => item.total_attempts),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.3
                        }, {
                            label: '<?php esc_html_e('Tamamlanan', 'email-verification-forms'); ?>',
                            data: trendData.map(item => item.completed),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Email Status Chart
                const emailCtx = document.getElementById('evf-email-chart').getContext('2d');

                new Chart(emailCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['<?php esc_html_e('Başarılı', 'email-verification-forms'); ?>', '<?php esc_html_e('Başarısız', 'email-verification-forms'); ?>'],
                        datasets: [{
                            data: [<?php echo esc_js($email_stats['successful_emails']); ?>, <?php echo esc_js($email_stats['failed_emails']); ?>],
                            backgroundColor: ['#10b981', '#ef4444']
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Son aktiviteleri render et
     */
    private static function render_recent_activity() {
        $database = EVF_Database::instance();
        $recent = $database->get_recent_registrations(10);

        if (empty($recent)) {
            echo '<p>' . esc_html__('Henüz aktivite yok.', 'email-verification-forms') . '</p>';
            return;
        }

        echo '<div class="evf-activity-list">';
        foreach ($recent as $activity) {
            $status_class = 'evf-activity-' . esc_attr($activity->status);
            $status_text = '';

            switch ($activity->status) {
                case 'pending':
                    $status_text = esc_html__('kayıt denemesi başlattı', 'email-verification-forms');
                    break;
                case 'email_verified':
                    $status_text = esc_html__('e-postasını doğruladı', 'email-verification-forms');
                    break;
                case 'completed':
                    $status_text = esc_html__('kaydını tamamladı', 'email-verification-forms');
                    break;
                case 'expired':
                    $status_text = esc_html__('kaydının süresi doldu', 'email-verification-forms');
                    break;
            }

            echo '<div class="evf-activity-item ' . esc_attr($status_class) . '">';
            echo '<div class="evf-activity-content">';
            echo '<strong>' . esc_html($activity->email) . '</strong> ' . esc_html($status_text);
            echo '<span class="evf-activity-time">' . esc_html(human_time_diff(strtotime($activity->created_at))) . ' ' . esc_html__('önce', 'email-verification-forms') . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
}