/**
 * Email Verification Forms - Admin JavaScript
 */

(function($) {
    'use strict';

    // Admin EVF object
    window.EVF_Admin = {
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initColorPicker();
            this.initCharts();
        },

        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.evf-tab-btn', this.handleTabClick);
            
            // Test email
            $(document).on('click', '#send-test-email', this.sendTestEmail);
            
            // Export data
            $(document).on('click', '#export-data', this.exportData);
            
            // Cleanup database
            $(document).on('click', '#cleanup-database', this.cleanupDatabase);
            
            // Reset stats
            $(document).on('click', '#reset-stats', this.resetStats);
            
            // Resend email
            $(document).on('click', '.evf-resend-email', this.resendEmail);
            
            // View details
            $(document).on('click', '.evf-view-details', this.viewDetails);
            
            // Color picker change
            $(document).on('change', 'input[name="evf_brand_color"]', this.updateColorPreview);
        },

        handleTabClick: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const tabId = $btn.data('tab');
            
            // Update active tab button
            $('.evf-tab-btn').removeClass('active');
            $btn.addClass('active');
            
            // Show corresponding tab content
            $('.evf-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');
            
            // Save active tab in localStorage
            localStorage.setItem('evf_active_tab', tabId);
        },

        initTabs: function() {
            // Restore active tab from localStorage
            const activeTab = localStorage.getItem('evf_active_tab');
            if (activeTab) {
                $('.evf-tab-btn[data-tab="' + activeTab + '"]').click();
            }
        },

        initColorPicker: function() {
            // Initialize color preview
            this.updateColorPreview();
        },

        updateColorPreview: function() {
            const color = $('input[name="evf_brand_color"]').val();
            $('.evf-preview-button').css('background-color', color);
        },

        sendTestEmail: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const email = $('#test-email-address').val();
            const type = $('#test-email-type').val();
            
            if (!email) {
                alert('Lütfen bir e-posta adresi girin.');
                return;
            }
            
            if (!EVF_Admin.isValidEmail(email)) {
                alert('Geçerli bir e-posta adresi girin.');
                return;
            }
            
            // Set loading state
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Gönderiliyor...');
            
            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_test_email',
                    nonce: evf_admin.nonce,
                    email: email,
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', evf_admin.messages.test_email_sent);
                    } else {
                        EVF_Admin.showNotice('error', evf_admin.messages.test_email_failed);
                    }
                },
                error: function() {
                    EVF_Admin.showNotice('error', evf_admin.messages.test_email_failed);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test E-postası Gönder');
                }
            });
        },

        exportData: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const exportType = $('input[name="export_type"]:checked').val();
            
            if (!exportType) {
                alert('Lütfen bir dışa aktarma türü seçin.');
                return;
            }
            
            if (!confirm('Veriler CSV formatında indirilecek. Devam etmek istiyor musunuz?')) {
                return;
            }
            
            // Set loading state
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Hazırlanıyor...');
            
            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: evf_admin.ajax_url,
                style: 'display: none;'
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'evf_export_data'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: evf_admin.nonce
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'export_type',
                value: exportType
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            // Reset button after delay
            setTimeout(function() {
                $btn.prop('disabled', false).text('Dışa Aktar');
            }, 2000);
        },

        cleanupDatabase: function(e) {
            e.preventDefault();
            
            if (!confirm('Bu işlem süresi dolmuş kayıtları ve eski logları silecek. Devam etmek istiyor musunuz?')) {
                return;
            }
            
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Temizleniyor...');
            
            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_cleanup_database',
                    nonce: evf_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', 'Veritabanı temizlendi!');
                        location.reload();
                    } else {
                        EVF_Admin.showNotice('error', 'Temizleme işlemi başarısız.');
                    }
                },
                error: function() {
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Temizlemeyi Çalıştır');
                }
            });
        },

        resetStats: function(e) {
            e.preventDefault();
            
            if (!confirm(evf_admin.messages.confirm_delete)) {
                return;
            }
            
            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Sıfırlanıyor...');
            
            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_reset_stats',
                    nonce: evf_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', 'İstatistikler sıfırlandı!');
                        location.reload();
                    } else {
                        EVF_Admin.showNotice('error', 'Sıfırlama işlemi başarısız.');
                    }
                },
                error: function() {
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('İstatistikleri Sıfırla');
                }
            });
        },

        resendEmail: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const email = $btn.data('email');
            
            if (!confirm('Bu e-posta adresine doğrulama e-postası tekrar gönderilecek. Devam etmek istiyor musunuz?')) {
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Gönderiliyor...');
            
            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_resend_verification',
                    nonce: evf_admin.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', 'E-posta tekrar gönderildi!');
                    } else {
                        EVF_Admin.showNotice('error', 'E-posta gönderilemedi.');
                    }
                },
                error: function() {
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Yeniden Gönder');
                }
            });
        },

        viewDetails: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const registrationId = $btn.data('id');
            
            // Create modal
            const modal = $(`
                <div class="evf-modal-overlay">
                    <div class="evf-modal">
                        <div class="evf-modal-header">
                            <h3>Kayıt Detayları</h3>
                            <button class="evf-modal-close">&times;</button>
                        </div>
                        <div class="evf-modal-content">
                            <div class="evf-loading-spinner">
                                <span class="evf-spinner-admin"></span>
                                Yükleniyor...
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            // Load registration details
            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_get_registration_details',
                    nonce: evf_admin.nonce,
                    id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        modal.find('.evf-modal-content').html(response.data.html);
                    } else {
                        modal.find('.evf-modal-content').html('<p>Detaylar yüklenemedi.</p>');
                    }
                },
                error: function() {
                    modal.find('.evf-modal-content').html('<p>Bir hata oluştu.</p>');
                }
            });
            
            // Close modal events
            modal.on('click', '.evf-modal-close, .evf-modal-overlay', function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });
        },

        initCharts: function() {
            // Chart initialization is handled in the PHP template
            // This is a placeholder for any additional chart customization
        },

        // Utility functions
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        showNotice: function(type, message) {
            // Remove existing notices
            $('.evf-admin-notice').remove();
            
            const notice = $('<div>', {
                class: 'notice notice-' + type + ' evf-admin-notice is-dismissible',
                html: '<p>' + message + '</p>'
            });
            
            // Add dismiss button
            notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Bu bildirimi kapat</span></button>');
            
            // Insert notice
            $('.evf-admin-wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Handle dismiss button
            notice.on('click', '.notice-dismiss', function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        },

        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        // Real-time updates
        initRealTimeUpdates: function() {
            // Poll for updates every 30 seconds
            setInterval(function() {
                EVF_Admin.updateDashboardStats();
            }, 30000);
        },

        updateDashboardStats: function() {
            if ($('.evf-stats-grid').length === 0) {
                return;
            }
            
            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_get_dashboard_stats',
                    nonce: evf_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.updateStatsCards(response.data);
                    }
                }
            });
        },

        updateStatsCards: function(stats) {
            // Update stats cards with new data
            $('.evf-stat-card').each(function() {
                const $card = $(this);
                const statType = $card.data('stat-type');
                
                if (stats[statType]) {
                    $card.find('h3').text(EVF_Admin.formatNumber(stats[statType]));
                }
            });
        },

        // Data table enhancements
        initDataTable: function() {
            // Add sorting functionality
            $('.evf-table th').on('click', function() {
                const $th = $(this);
                const column = $th.data('column');
                
                if (!column) return;
                
                const $table = $th.closest('table');
                const $tbody = $table.find('tbody');
                const rows = $tbody.find('tr').toArray();
                
                const isAscending = $th.hasClass('sorted-asc');
                
                // Remove existing sort classes
                $table.find('th').removeClass('sorted-asc sorted-desc');
                
                // Add new sort class
                $th.addClass(isAscending ? 'sorted-desc' : 'sorted-asc');
                
                // Sort rows
                rows.sort(function(a, b) {
                    const aVal = $(a).find('td').eq($th.index()).text().trim();
                    const bVal = $(b).find('td').eq($th.index()).text().trim();
                    
                    let comparison = 0;
                    if (aVal > bVal) comparison = 1;
                    if (aVal < bVal) comparison = -1;
                    
                    return isAscending ? -comparison : comparison;
                });
                
                // Update table
                $tbody.empty().append(rows);
            });
        },

        // Keyboard shortcuts
        initKeyboardShortcuts: function() {
            $(document).on('keydown', function(e) {
                // Ctrl + S to save settings
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    $('input[type="submit"]').click();
                }
                
                // Ctrl + E to export data
                if (e.ctrlKey && e.key === 'e') {
                    e.preventDefault();
                    $('#export-data').click();
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    $('.evf-modal-overlay').remove();
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EVF_Admin.init();
        EVF_Admin.initDataTable();
        EVF_Admin.initKeyboardShortcuts();
        EVF_Admin.initRealTimeUpdates();
        
        // Handle responsive table scrolling
        $('.evf-table').wrap('<div class="evf-table-wrapper"></div>');
        
        // Auto-refresh for specific pages
        if (window.location.href.indexOf('evf-registrations') > -1) {
            // Auto-refresh registrations page every 60 seconds
            setTimeout(function() {
                location.reload();
            }, 60000);
        }
    });

    // Handle window resize
    $(window).on('resize', function() {
        // Update chart dimensions if needed
        if (typeof Chart !== 'undefined') {
            // Chart.js v3+ için güncellenmiş kod
            Object.values(Chart.instances || {}).forEach(function(chart) {
                if (chart && typeof chart.resize === 'function') {
                    chart.resize();
                }
            });
        }
    });

})(jQuery);

// Additional CSS for modal and enhanced features
const additionalCSS = `
    .evf-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 100000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .evf-modal {
        background: white;
        border-radius: 8px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    .evf-modal-header {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .evf-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }
    
    .evf-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #6b7280;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }
    
    .evf-modal-close:hover {
        background: #f3f4f6;
        color: #374151;
    }
    
    .evf-modal-content {
        padding: 20px;
        overflow-y: auto;
        max-height: calc(80vh - 80px);
    }
    
    .evf-loading-spinner {
        text-align: center;
        padding: 40px;
        color: #6b7280;
    }
    
    .evf-table-wrapper {
        overflow-x: auto;
        margin: 20px 0;
    }
    
    .evf-table th.sorted-asc::after {
        content: ' ↑';
    }
    
    .evf-table th.sorted-desc::after {
        content: ' ↓';
    }
    
    .evf-table th[data-column] {
        cursor: pointer;
        user-select: none;
    }
    
    .evf-table th[data-column]:hover {
        background: #e5e7eb;
    }
    
    @media (max-width: 768px) {
        .evf-modal {
            width: 95%;
            max-height: 90vh;
        }
        
        .evf-modal-header,
        .evf-modal-content {
            padding: 15px;
        }
    }
`;

// Add additional CSS to head
if (document.head) {
    const style = document.createElement('style');
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}