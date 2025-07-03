/**
 * IX Woo Xero Integration - Admin JavaScript
 * 
 * Handles all admin-side interactions including:
 * - Tab navigation
 * - Connection management
 * - Manual sync operations
 * - Status updates
 */

jQuery(document).ready(function($) {
    'use strict';

    // Plugin namespace
    const IXWooXero = {

        /**
         * Initialize all plugin functionality
         */
        init: function() {
            this.setupTabs();
            this.setupConnection();
            this.setupSyncButtons();
            this.setupTooltips();
            this.setupLogRefresh();
            this.setupBulkActions();
        },

        /**
         * Tab navigation system
         */
        setupTabs: function() {
            // Switch tabs
            $('.ix-tabs-nav .nav-tab').on('click', function(e) {
                e.preventDefault();

                // Activate clicked tab
                $('.ix-tabs-nav .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Show corresponding content
                const target = $(this).attr('href');
                $('.ix-tab-content').removeClass('active');
                $(target).addClass('active');

                // Update URL hash
                window.location.hash = $(this).attr('href');
            });

            // Check for hash on page load
            if (window.location.hash) {
                const hash = window.location.hash;
                $(`.ix-tabs-nav a[href="${hash}"]`).trigger('click');
            }
        },

        /**
         * Xero connection management
         */
        setupConnection: function() {
            // Disconnect button
            $('.ix-disconnect-xero').on('click', function(e) {
                e.preventDefault();
                
                if (confirm(ixWooXeroVars.i18n.confirm_disconnect)) {
                    window.location = $(this).attr('href');
                }
            });

            // Test connection button
            $('.ix-test-connection').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                
                $button.addClass('testing').prop('disabled', true);
                $button.find('.spinner').addClass('is-active');

                $.ajax({
                    url: ixWooXeroVars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ix_woo_xero_test_connection',
                        nonce: ixWooXeroVars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            IXWooXero.showNotice('success', response.data.message);
                        } else {
                            IXWooXero.showNotice('error', response.data);
                        }
                    },
                    error: function(xhr) {
                        IXWooXero.showNotice('error', xhr.responseJSON.data);
                    },
                    complete: function() {
                        $button.removeClass('testing').prop('disabled', false);
                        $button.find('.spinner').removeClass('is-active');
                    }
                });
            });
        },

        /**
         * Manual sync buttons
         */
        setupSyncButtons: function() {
            $('.ix-sync-button').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                const syncType = $button.data('sync-type');

                // Validate sync type
                if (!syncType) {
                    IXWooXero.showNotice('error', ixWooXeroVars.i18n.invalid_sync_type);
                    return;
                }

                // Prepare UI
                $button.addClass('syncing').prop('disabled', true);
                $button.find('.sync-label').text(ixWooXeroVars.i18n.syncing);
                $button.find('.spinner').addClass('is-active');

                // AJAX request
                $.ajax({
                    url: ixWooXeroVars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ix_woo_xero_sync_' + syncType,
                        nonce: ixWooXeroVars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            IXWooXero.showNotice('success', response.data.message || ixWooXeroVars.i18n.sync_complete);
                            
                            // Update stats if available
                            if (response.data.stats) {
                                IXWooXero.updateStats(response.data.stats);
                            }
                        } else {
                            IXWooXero.showNotice('error', response.data || ixWooXeroVars.i18n.sync_error);
                        }
                    },
                    error: function(xhr) {
                        IXWooXero.showNotice('error', xhr.responseJSON.data || ixWooXeroVars.i18n.sync_error);
                    },
                    complete: function() {
                        $button.removeClass('syncing').prop('disabled', false);
                        $button.find('.sync-label').text($button.data('original-text'));
                        $button.find('.spinner').removeClass('is-active');
                    }
                });
            });
        },

        /**
         * Tooltip initialization
         */
        setupTooltips: function() {
            // Initialize tooltips
            $('.ix-tooltip').tooltipster({
                theme: 'tooltipster-light',
                trigger: 'hover',
                animation: 'fade',
                delay: 200,
                interactive: true,
                contentAsHTML: true
            });
        },

        /**
         * Log viewer auto-refresh
         */
        setupLogRefresh: function() {
            const $logContainer = $('.ix-log-viewer');
            
            if ($logContainer.length && $('.ix-tab-content.logs').hasClass('active')) {
                // Auto-refresh logs every 30 seconds
                const logRefreshInterval = setInterval(function() {
                    $.ajax({
                        url: ixWooXeroVars.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'ix_woo_xero_get_logs',
                            nonce: ixWooXeroVars.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $logContainer.html(response.data.logs);
                            }
                        }
                    });
                }, 30000);

                // Clear interval when leaving logs tab
                $('.ix-tabs-nav .nav-tab').on('click', function() {
                    if (!$(this).hasClass('logs-tab')) {
                        clearInterval(logRefreshInterval);
                    }
                });
            }
        },

        /**
         * Bulk actions in list tables
         */
        setupBulkActions: function() {
            // Product bulk sync
            $('body').on('click', '.ix-bulk-sync', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $form = $button.closest('form');
                const postIds = $form.find('input[name="post[]"]:checked').map(function() {
                    return $(this).val();
                }).get();

                if (postIds.length === 0) {
                    alert(ixWooXeroVars.i18n.no_items_selected);
                    return;
                }

                $button.prop('disabled', true).text(ixWooXeroVars.i18n.syncing);

                $.ajax({
                    url: ixWooXeroVars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ix_woo_xero_bulk_sync',
                        post_ids: postIds,
                        nonce: ixWooXeroVars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message || ixWooXeroVars.i18n.bulk_sync_complete);
                            window.location.reload();
                        } else {
                            alert(response.data || ixWooXeroVars.i18n.bulk_sync_error);
                        }
                    },
                    error: function() {
                        alert(ixWooXeroVars.i18n.bulk_sync_error);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text($button.data('original-text'));
                    }
                });
            });
        },

        /**
         * Display admin notices
         * @param {string} type - 'success' or 'error'
         * @param {string} message - The notice message
         */
        showNotice: function(type, message) {
            const $notice = $('<div>', {
                class: `notice notice-${type} is-dismissible`,
                html: `<p>${message}</p>`
            });

            $('.ix-woo-xero-wrap').prepend($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Update stats dashboard
         * @param {object} stats - Key/value pairs of stats to update
         */
        updateStats: function(stats) {
            $.each(stats, function(key, value) {
                $(`.ix-stat-card[data-stat="${key}"] .stat-value`).text(value);
            });
        }
    };

    // Initialize the plugin
    IXWooXero.init();

    /**
     * WP List Table row actions
     */
    $(document).on('click', '.ix-sync-row-action', function(e) {
        e.preventDefault();
        
        const $link = $(this);
        const itemId = $link.data('item-id');
        const itemType = $link.data('item-type');

        if (!itemId || !itemType) {
            return;
        }

        $link.addClass('syncing').text(ixWooXeroVars.i18n.syncing);

        $.ajax({
            url: ixWooXeroVars.ajax_url,
            type: 'POST',
            data: {
                action: 'ix_woo_xero_sync_single',
                item_id: itemId,
                item_type: itemType,
                nonce: ixWooXeroVars.nonce
            },
            success: function(response) {
                if (response.success) {
                    IXWooXero.showNotice('success', response.data.message || ixWooXeroVars.i18n.sync_complete);
                } else {
                    IXWooXero.showNotice('error', response.data || ixWooXeroVars.i18n.sync_error);
                }
            },
            error: function() {
                IXWooXero.showNotice('error', ixWooXeroVars.i18n.sync_error);
            },
            complete: function() {
                $link.removeClass('syncing').text($link.data('original-text'));
            }
        });
    });
});