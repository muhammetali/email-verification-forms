/**
 * Email Verification Forms - Admin JavaScript
 * Temizlenmiş ve optimize edilmiş versiyon
 */

(function($) {
    'use strict';

    // Admin EVF object
    window.EVF_Admin = {

        // Initialization
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initColorPicker();
            this.initCharts();
            this.initTooltips();
        },

        // Event bindings
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.evf-tab-btn', this.handleTabClick);

            // Test email functionality
            $(document).on('click', '#send-test-email', this.sendTestEmail);

            // Export and database operations
            $(document).on('click', '#export-data', this.exportData);
            $(document).on('click', '#cleanup-database', this.cleanupDatabase);
            $(document).on('click', '#reset-stats', this.resetStats);

            // Email operations
            $(document).on('click', '.evf-resend-email', this.resendEmail);
            $(document).on('click', '.evf-delete-registration', this.deleteRegistration);

            // View details
            $(document).on('click', '.evf-view-details', this.viewDetails);

            // Color picker
            $(document).on('change', 'input[name="evf_brand_color"]', this.updateColorPreview);

            // Settings form
            $(document).on('change', '.evf-toggle-setting', this.toggleSetting);

            // Refresh stats
            $(document).on('click', '#refresh-stats', this.refreshStats);

            // Bulk actions
            $(document).on('click', '#bulk-action-apply', this.applyBulkAction);
            $(document).on('change', '.evf-bulk-select-all', this.toggleBulkSelect);
        },

        // Tab management
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

            // Save active tab
            localStorage.setItem('evf_active_tab', tabId);

            // Trigger tab change event
            $(document).trigger('evf:tab-changed', [tabId]);
        },

        initTabs: function() {
            // Restore active tab from localStorage
            const activeTab = localStorage.getItem('evf_active_tab');
            if (activeTab && $('.evf-tab-btn[data-tab="' + activeTab + '"]').length) {
                $('.evf-tab-btn[data-tab="' + activeTab + '"]').click();
            }
        },

        // Color picker functionality
        initColorPicker: function() {
            if (typeof $.fn.wpColorPicker !== 'undefined') {
                $('input[name="evf_brand_color"]').wpColorPicker({
                    change: this.updateColorPreview,
                    clear: this.updateColorPreview
                });
            }
            this.updateColorPreview();
        },

        updateColorPreview: function() {
            const color = $('input[name="evf_brand_color"]').val() || '#3b82f6';
            $('.evf-preview-button').css('background-color', color);
            $('.evf-color-preview').css('background-color', color);
        },

        // Test email functionality
        sendTestEmail: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const email = $('#test-email-address').val();
            const type = $('#test-email-type').val();

            // Validation
            if (!email) {
                EVF_Admin.showNotice('error', 'Lütfen bir e-posta adresi girin.');
                return;
            }

            if (!EVF_Admin.isValidEmail(email)) {
                EVF_Admin.showNotice('error', 'Geçerli bir e-posta adresi girin.');
                return;
            }

            // Set loading state
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Gönderiliyor...');

            // Send test email
            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_send_test_email',
                    nonce: evf_admin.nonce,
                    email: email,
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', 'Test e-postası başarıyla gönderildi!');
                        $('#test-email-address').val('');
                    } else {
                        EVF_Admin.showNotice('error', response.data || 'E-posta gönderilemedi.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Test email error:', error);
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test E-postası Gönder');
                }
            });
        },

        // Data export functionality
        exportData: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const exportType = $('input[name="export_type"]:checked').val();

            if (!exportType) {
                EVF_Admin.showNotice('error', 'Lütfen bir export türü seçin.');
                return;
            }

            if (!confirm('Veriler CSV formatında indirilecek. Devam etmek istiyor musunuz?')) {
                return;
            }

            // Set loading state
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Hazırlanıyor...');

            // Create and submit form
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

            // Reset button
            setTimeout(function() {
                $btn.prop('disabled', false).text('Dışa Aktar');
            }, 2000);
        },

        // Database cleanup
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
                        EVF_Admin.showNotice('success', 'Veritabanı temizlendi! ' + (response.data.message || ''));
                        // Refresh page after cleanup
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        EVF_Admin.showNotice('error', response.data || 'Temizleme işlemi başarısız.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Cleanup error:', error);
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Temizlemeyi Çalıştır');
                }
            });
        },

        // Reset statistics
        resetStats: function(e) {
            e.preventDefault();

            if (!confirm('Tüm istatistikler sıfırlanacak. Bu işlem geri alınamaz. Devam etmek istiyor musunuz?')) {
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
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        EVF_Admin.showNotice('error', response.data || 'Sıfırlama işlemi başarısız.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Reset stats error:', error);
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('İstatistikleri Sıfırla');
                }
            });
        },

        // Resend email
        resendEmail: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const email = $btn.data('email');
            const registrationId = $btn.data('id');

            if (!email) {
                EVF_Admin.showNotice('error', 'E-posta adresi bulunamadı.');
                return;
            }

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
                    email: email,
                    registration_id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', 'E-posta tekrar gönderildi!');
                    } else {
                        EVF_Admin.showNotice('error', response.data || 'E-posta gönderilemedi.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Resend email error:', error);
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('📧 Tekrar Gönder');
                }
            });
        },

        // Delete registration
        deleteRegistration: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const registrationId = $btn.data('id');
            const email = $btn.data('email');

            if (!confirm('Bu kayıt silinecek: ' + email + '. Devam etmek istiyor musunuz?')) {
                return;
            }

            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Siliniyor...');

            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_delete_registration',
                    nonce: evf_admin.nonce,
                    registration_id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', 'Kayıt silindi!');
                        $btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        EVF_Admin.showNotice('error', response.data || 'Kayıt silinemedi.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete registration error:', error);
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('🗑️ Sil');
                }
            });
        },

        // View details modal
        viewDetails: function(e) {
            e.preventDefault();

            const $btn = $(this);
            const registrationId = $btn.data('id');

            // Show loading
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span>');

            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_get_registration_details',
                    nonce: evf_admin.nonce,
                    registration_id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showModal('Kayıt Detayları', response.data.html);
                    } else {
                        EVF_Admin.showNotice('error', 'Detaylar yüklenemedi.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('View details error:', error);
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('👁️ Detay');
                }
            });
        },

        // Settings toggle
        toggleSetting: function(e) {
            const $toggle = $(this);
            const setting = $toggle.data('setting');
            const value = $toggle.is(':checked') ? 1 : 0;

            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_toggle_setting',
                    nonce: evf_admin.nonce,
                    setting: setting,
                    value: value
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', 'Ayar güncellendi!', 2000);
                    } else {
                        $toggle.prop('checked', !$toggle.is(':checked'));
                        EVF_Admin.showNotice('error', 'Ayar güncellenemedi.');
                    }
                },
                error: function() {
                    $toggle.prop('checked', !$toggle.is(':checked'));
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                }
            });
        },

        // Refresh stats
        refreshStats: function(e) {
            e.preventDefault();

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> Yenileniyor...');

            location.reload();
        },

        // Bulk actions
        applyBulkAction: function(e) {
            e.preventDefault();

            const action = $('#bulk-action-selector').val();
            const selected = $('.evf-bulk-select:checked').map(function() {
                return $(this).val();
            }).get();

            if (!action) {
                EVF_Admin.showNotice('error', 'Lütfen bir işlem seçin.');
                return;
            }

            if (selected.length === 0) {
                EVF_Admin.showNotice('error', 'Lütfen en az bir kayıt seçin.');
                return;
            }

            const actionText = $('#bulk-action-selector option:selected').text();
            if (!confirm(selected.length + ' kayıt için "' + actionText + '" işlemi uygulanacak. Devam etmek istiyor musunuz?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<span class="evf-spinner-admin"></span> İşleniyor...');

            $.ajax({
                url: evf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'evf_bulk_action',
                    nonce: evf_admin.nonce,
                    bulk_action: action,
                    selected_items: selected
                },
                success: function(response) {
                    if (response.success) {
                        EVF_Admin.showNotice('success', response.data.message || 'İşlem tamamlandı!');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        EVF_Admin.showNotice('error', response.data || 'İşlem başarısız.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Bulk action error:', error);
                    EVF_Admin.showNotice('error', 'Bir hata oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Uygula');
                }
            });
        },

        // Toggle bulk select
        toggleBulkSelect: function() {
            const checked = $(this).is(':checked');
            $('.evf-bulk-select').prop('checked', checked);
        },

        // Charts initialization
        initCharts: function() {
            if (typeof Chart === 'undefined' || !$('#evf-registration-chart').length) {
                return;
            }

            // Registration trend chart
            const ctx = document.getElementById('evf-registration-chart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: window.evf_chart_data || {},
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        // Tooltips initialization
        initTooltips: function() {
            if (typeof $.fn.tooltip !== 'undefined') {
                $('[data-tooltip]').tooltip({
                    placement: 'top',
                    trigger: 'hover'
                });
            }
        },

        // Utility functions
        isValidEmail: function(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        showNotice: function(type, message, duration) {
            duration = duration || 5000;

            const notice = $('<div class="evf-admin-notice evf-notice-' + type + '">' +
                '<span class="evf-notice-message">' + message + '</span>' +
                '<button class="evf-notice-close">&times;</button>' +
                '</div>');

            // Remove existing notices
            $('.evf-admin-notice').remove();

            // Add new notice
            $('body').append(notice);

            // Auto hide
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, duration);

            // Manual close
            notice.on('click', '.evf-notice-close', function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            });
        },

        showModal: function(title, content) {
            const modal = $('<div class="evf-modal-overlay">' +
                '<div class="evf-modal">' +
                '<div class="evf-modal-header">' +
                '<h3>' + title + '</h3>' +
                '<button class="evf-modal-close">&times;</button>' +
                '</div>' +
                '<div class="evf-modal-body">' + content + '</div>' +
                '</div>' +
                '</div>');

            $('body').append(modal);

            // Close events
            modal.on('click', '.evf-modal-close, .evf-modal-overlay', function(e) {
                if (e.target === this) {
                    modal.fadeOut(function() {
                        modal.remove();
                    });
                }
            });

            // Escape key
            $(document).on('keydown.evf-modal', function(e) {
                if (e.keyCode === 27) {
                    modal.fadeOut(function() {
                        modal.remove();
                    });
                    $(document).off('keydown.evf-modal');
                }
            });
        },

        // Debug helper
        debug: function(message, data) {
            if (window.console && evf_admin.debug) {
                console.log('[EVF Admin Debug]', message, data || '');
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        EVF_Admin.init();
        EVF_Admin.debug('Admin script initialized');
    });

    // Export for global access
    window.EVF_Admin = EVF_Admin;

})(jQuery);