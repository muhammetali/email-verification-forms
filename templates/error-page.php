<?php
/**
 * Error Page Template
 * Hata sayfası şablonu
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get error message (passed from calling function)
$error_message = isset($message) ? $message : __('Bir hata oluştu.', 'email-verification-forms');
$error_title = isset($title) ? $title : __('Hata', 'email-verification-forms');

// Güvenli IP adresi alma
$client_ip = '';
if (isset($_SERVER['REMOTE_ADDR'])) {
    $client_ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
}

// Güvenli User Agent alma
$user_agent = '';
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $user_agent = sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
}

// Güvenli token alma (sadece görüntüleme için - güvenlik açısından kısaltılmış)
$token_display = '';
if (isset($_GET['evf_token'])) {
    $token_full = sanitize_text_field(wp_unslash($_GET['evf_token']));
    $token_display = substr($token_full, 0, 8) . '...';
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($error_title); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="evf-error-page">

<div class="evf-error-wrapper">
    <div class="evf-error-card evf-fade-in">

        <!-- Error Icon -->
        <div class="evf-error-icon">
            ⚠️
        </div>

        <!-- Error Content -->
        <div class="evf-error-content">
            <h1 class="evf-error-title"><?php echo esc_html($error_title); ?></h1>
            <p class="evf-error-message"><?php echo esc_html($error_message); ?></p>

            <!-- Common Error Types -->
            <div class="evf-error-help">
                <h3><?php esc_html_e('Ne yapabilirsiniz?', 'email-verification-forms'); ?></h3>
                <ul class="evf-help-list">
                    <li>
                        <strong><?php esc_html_e('Bağlantı süresi dolmuşsa:', 'email-verification-forms'); ?></strong>
                        <?php esc_html_e('Yeni bir kayıt denemesi yapın', 'email-verification-forms'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Bağlantı geçersizse:', 'email-verification-forms'); ?></strong>
                        <?php esc_html_e('E-postanızı kontrol edin ve doğru bağlantıya tıklayın', 'email-verification-forms'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Teknik sorun varsa:', 'email-verification-forms'); ?></strong>
                        <?php esc_html_e('Lütfen site yöneticisi ile iletişime geçin', 'email-verification-forms'); ?>
                    </li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="evf-error-actions">
                <?php if (function_exists('wp_registration_url')): ?>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="evf-btn evf-btn-primary">
                        <?php _e('Yeni Kayıt Denemesi', 'email-verification-forms'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(wp_login_url() . '?action=register'); ?>" class="evf-btn evf-btn-primary">
                        <?php _e('Yeni Kayıt Denemesi', 'email-verification-forms'); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(home_url()); ?>" class="evf-btn evf-btn-secondary">
                    <?php _e('Ana Sayfaya Dön', 'email-verification-forms'); ?>
                </a>
            </div>

            <!-- Contact Info -->
            <div class="evf-contact-info">
                <p>
                    <?php _e('Sorun devam ediyorsa:', 'email-verification-forms'); ?>
                    <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>" class="evf-contact-link">
                        <?php echo esc_html(get_option('admin_email')); ?>
                    </a>
                </p>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="evf-error-details">
            <details class="evf-technical-details">
                <summary><?php esc_html_e('Teknik Detaylar', 'email-verification-forms'); ?></summary>
                <div class="evf-details-content">
                    <p><strong><?php esc_html_e('Zaman:', 'email-verification-forms'); ?></strong> <?php echo esc_html(current_time('d.m.Y H:i:s')); ?></p>
                    <?php if (!empty($client_ip)): ?>
                        <p><strong><?php esc_html_e('IP Adresi:', 'email-verification-forms'); ?></strong> <?php echo esc_html($client_ip); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($user_agent)): ?>
                        <p><strong><?php esc_html_e('User Agent:', 'email-verification-forms'); ?></strong> <?php echo esc_html($user_agent); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($token_display)): ?>
                        <p><strong><?php esc_html_e('Token:', 'email-verification-forms'); ?></strong> <?php echo esc_html($token_display); ?></p>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    </div>
</div>

<style>
    /* Error page specific styles */
    .evf-error-page {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        min-height: 100vh;
    }

    .evf-error-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }

    .evf-error-card {
        background: var(--evf-white, #ffffff);
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        padding: 3rem 2.5rem;
        width: 100%;
        max-width: 600px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .evf-error-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #ef4444, #dc2626);
    }

    .evf-error-icon {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        display: block;
    }

    .evf-error-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 1rem 0;
    }

    .evf-error-message {
        font-size: 1.125rem;
        color: #4b5563;
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .evf-error-help {
        background: #f9fafb;
        border-radius: 8px;
        padding: 1.5rem;
        margin: 2rem 0;
        text-align: left;
    }

    .evf-error-help h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1f2937;
        margin: 0 0 1rem 0;
    }

    .evf-help-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .evf-help-list li {
        margin-bottom: 0.75rem;
        padding-left: 1rem;
        position: relative;
        color: #4b5563;
        line-height: 1.5;
    }

    .evf-help-list li::before {
        content: '•';
        color: #ef4444;
        font-weight: bold;
        position: absolute;
        left: 0;
    }

    .evf-help-list li:last-child {
        margin-bottom: 0;
    }

    .evf-error-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin: 2rem 0;
        flex-wrap: wrap;
    }

    .evf-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        min-width: 150px;
    }

    .evf-btn-primary {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
    }

    .evf-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 15px -3px rgba(59, 130, 246, 0.3);
        color: white;
        text-decoration: none;
    }

    .evf-btn-secondary {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .evf-btn-secondary:hover {
        background: #e5e7eb;
        color: #374151;
        text-decoration: none;
        transform: translateY(-1px);
    }

    .evf-contact-info {
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
    }

    .evf-contact-info p {
        color: #6b7280;
        font-size: 0.9rem;
        margin: 0;
    }

    .evf-contact-link {
        color: #3b82f6;
        text-decoration: none;
        font-weight: 600;
    }

    .evf-contact-link:hover {
        text-decoration: underline;
    }

    .evf-error-details {
        margin-top: 2rem;
        text-align: left;
    }

    .evf-technical-details {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 1rem;
    }

    .evf-technical-details summary {
        font-weight: 600;
        color: #4a5568;
        cursor: pointer;
        padding: 0.5rem 0;
        outline: none;
    }

    .evf-technical-details summary:hover {
        color: #2d3748;
    }

    .evf-details-content {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }

    .evf-details-content p {
        font-size: 0.875rem;
        color: #718096;
        margin: 0.5rem 0;
        word-break: break-all;
    }

    .evf-details-content strong {
        color: #4a5568;
    }

    /* Responsive design */
    @media (max-width: 640px) {
        .evf-error-card {
            padding: 2rem 1.5rem;
            margin: 1rem;
        }

        .evf-error-title {
            font-size: 1.5rem;
        }

        .evf-error-message {
            font-size: 1rem;
        }

        .evf-error-actions {
            flex-direction: column;
            align-items: center;
        }

        .evf-btn {
            width: 100%;
            max-width: 250px;
        }

        .evf-error-help {
            padding: 1rem;
        }

        .evf-error-icon {
            font-size: 3rem;
        }
    }

    @media (max-width: 480px) {
        .evf-error-wrapper {
            padding: 1rem;
        }

        .evf-error-card {
            padding: 1.5rem 1rem;
            margin: 0.5rem;
        }

        .evf-error-icon {
            font-size: 2.5rem;
        }

        .evf-error-title {
            font-size: 1.25rem;
        }

        .evf-help-list li {
            font-size: 0.9rem;
        }
    }

    /* Animation */
    .evf-fade-in {
        animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Focus states for accessibility */
    .evf-btn:focus,
    .evf-contact-link:focus,
    .evf-technical-details summary:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    /* Print styles */
    @media print {
        .evf-error-wrapper {
            background: white;
            min-height: auto;
        }

        .evf-error-card {
            box-shadow: none;
            border: 1px solid #ddd;
        }

        .evf-error-actions {
            display: none;
        }
    }
</style>

<script>
    // Auto-refresh attempt after 5 minutes for expired tokens
    document.addEventListener('DOMContentLoaded', function() {
        const errorMessage = <?php echo wp_json_encode($error_message); ?>;

        if (errorMessage.includes('süresi dolmuş') || errorMessage.includes('expired')) {
            setTimeout(function() {
                if (confirm(<?php echo wp_json_encode(__('Sayfa yeniden yüklensin mi?', 'email-verification-forms')); ?>)) {
                    window.location.reload();
                }
            }, 300000); // 5 minutes
        }

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName === 'SUMMARY') {
                e.target.click();
            }
        });

        // Auto-hide technical details on mobile for better UX
        if (window.innerWidth < 640) {
            const details = document.querySelector('.evf-technical-details');
            if (details) {
                details.removeAttribute('open');
            }
        }

        // Add error reporting functionality (optional)
        const reportBtn = document.createElement('button');
        reportBtn.textContent = <?php echo wp_json_encode(__('Hatayı Bildir', 'email-verification-forms')); ?>;
        reportBtn.className = 'evf-btn evf-btn-secondary';
        reportBtn.style.fontSize = '0.875rem';
        reportBtn.style.minWidth = 'auto';
        reportBtn.style.padding = '0.5rem 1rem';
        reportBtn.style.marginTop = '1rem';

        reportBtn.addEventListener('click', function() {
            const subject = encodeURIComponent('EVF Error Report: ' + <?php echo wp_json_encode($error_title); ?>);
            const body = encodeURIComponent(
                'Error Details:\n' +
                'Time: ' + <?php echo wp_json_encode(current_time('d.m.Y H:i:s')); ?> + '\n' +
                'Message: ' + errorMessage + '\n' +
                'URL: ' + window.location.href + '\n' +
                <?php if (!empty($client_ip)): ?>
                'IP: ' + <?php echo wp_json_encode($client_ip); ?> + '\n' +
                <?php endif; ?>
                <?php if (!empty($token_display)): ?>
                'Token: ' + <?php echo wp_json_encode($token_display); ?> + '\n' +
                <?php endif; ?>
                '\nPlease provide additional details about what you were trying to do when this error occurred.'
            );

            window.location.href = 'mailto:' + <?php echo wp_json_encode(get_option('admin_email')); ?> +
                '?subject=' + subject + '&body=' + body;
        });

        const contactInfo = document.querySelector('.evf-contact-info');
        if (contactInfo) {
            contactInfo.appendChild(reportBtn);
        }
    });
</script>

<?php wp_footer(); ?>

</body>
</html>