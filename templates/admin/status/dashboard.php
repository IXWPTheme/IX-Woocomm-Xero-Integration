<?php
/**
 * Dashboard Status Template
 *
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get sync statistics
$stats = IX_Helper::get_sync_stats();
$last_sync = get_option('ix_woo_xero_last_sync', false);
$connection_status = get_option('ix_woo_xero_connection_status', 'not_connected');
$settings = IX_Helper::get_settings();
?>

<div class="wrap ix-status-dashboard">
    <h1><?php esc_html_e('Xero Integration Status', 'ix-woo-xero'); ?></h1>

    <div class="ix-status-cards">
        <!-- Connection Status Card -->
        <div class="ix-card">
            <h2><?php esc_html_e('Connection Status', 'ix-woo-xero'); ?></h2>
            <div class="ix-card-content">
                <?php if ('connected' === $connection_status) : ?>
                    <div class="ix-status-indicator connected">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span><?php esc_html_e('Connected to Xero', 'ix-woo-xero'); ?></span>
                    </div>
                    <?php if (!empty($settings['api_mode'])) : ?>
                        <div class="ix-api-mode">
                            <strong><?php esc_html_e('API Mode:', 'ix-woo-xero'); ?></strong>
                            <span class="mode-<?php echo esc_attr($settings['api_mode']); ?>">
                                <?php echo ('live' === $settings['api_mode']) ? esc_html__('Live', 'ix-woo-xero') : esc_html__('Test', 'ix-woo-xero'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="ix-status-indicator disconnected">
                        <span class="dashicons dashicons-no-alt"></span>
                        <span><?php esc_html_e('Not Connected', 'ix-woo-xero'); ?></span>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero')); ?>" class="button button-primary">
                        <?php esc_html_e('Configure Connection', 'ix-woo-xero'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Last Sync Card -->
        <div class="ix-card">
            <h2><?php esc_html_e('Last Synchronization', 'ix-woo-xero'); ?></h2>
            <div class="ix-card-content">
                <?php if ($last_sync) : ?>
                    <div class="ix-last-sync-time">
                        <?php echo esc_html(date_i18n('F j, Y g:i a', strtotime($last_sync))); ?>
                    </div>
                    <div class="ix-sync-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&action=sync_now')); ?>" class="button">
                            <?php esc_html_e('Sync Now', 'ix-woo-xero'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&tab=logs')); ?>" class="button">
                            <?php esc_html_e('View Logs', 'ix-woo-xero'); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <div class="ix-no-sync">
                        <?php esc_html_e('No synchronization has been performed yet.', 'ix-woo-xero'); ?>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&action=sync_now')); ?>" class="button button-primary">
                        <?php esc_html_e('Run First Sync', 'ix-woo-xero'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sync Statistics Card -->
        <div class="ix-card">
            <h2><?php esc_html_e('Sync Statistics', 'ix-woo-xero'); ?></h2>
            <div class="ix-card-content">
                <div class="ix-stats-grid">
                    <div class="ix-stat-item">
                        <div class="ix-stat-number"><?php echo esc_html($stats['products']); ?></div>
                        <div class="ix-stat-label"><?php esc_html_e('Products', 'ix-woo-xero'); ?></div>
                    </div>
                    <div class="ix-stat-item">
                        <div class="ix-stat-number"><?php echo esc_html($stats['customers']); ?></div>
                        <div class="ix-stat-label"><?php esc_html_e('Customers', 'ix-woo-xero'); ?></div>
                    </div>
                    <div class="ix-stat-item">
                        <div class="ix-stat-number"><?php echo esc_html($stats['invoices']); ?></div>
                        <div class="ix-stat-label"><?php esc_html_e('Invoices', 'ix-woo-xero'); ?></div>
                    </div>
                    <div class="ix-stat-item">
                        <div class="ix-stat-number"><?php echo esc_html($stats['errors']); ?></div>
                        <div class="ix-stat-label"><?php esc_html_e('Errors', 'ix-woo-xero'); ?></div>
                    </div>
                </div>
                <div class="ix-stats-footer">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&tab=logs')); ?>">
                        <?php esc_html_e('View detailed statistics', 'ix-woo-xero'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="ix-recent-activity">
        <h2><?php esc_html_e('Recent Activity', 'ix-woo-xero'); ?></h2>
        <?php $recent_activity = IX_Helper::get_recent_activity(5); ?>
        
        <?php if (!empty($recent_activity)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'ix-woo-xero'); ?></th>
                        <th><?php esc_html_e('Type', 'ix-woo-xero'); ?></th>
                        <th><?php esc_html_e('Item', 'ix-woo-xero'); ?></th>
                        <th><?php esc_html_e('Status', 'ix-woo-xero'); ?></th>
                        <th><?php esc_html_e('Message', 'ix-woo-xero'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activity as $activity) : ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('M j, H:i', strtotime($activity->sync_date))); ?></td>
                            <td><?php echo esc_html(ucfirst($activity->object_type)); ?></td>
                            <td>
                                <?php if ('order' === $activity->object_type) : ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($activity->object_id)); ?>">
                                        #<?php echo esc_html($activity->object_id); ?>
                                    </a>
                                <?php elseif ('product' === $activity->object_type) : ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($activity->object_id)); ?>">
                                        <?php echo esc_html(get_the_title($activity->object_id)); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo esc_html($activity->object_id); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ix-status-<?php echo esc_attr($activity->status); ?>">
                                    <?php echo esc_html(ucfirst($activity->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($activity->message); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="ix-activity-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&tab=logs')); ?>" class="button">
                    <?php esc_html_e('View Full Activity Log', 'ix-woo-xero'); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="ix-no-activity">
                <?php esc_html_e('No recent activity found.', 'ix-woo-xero'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.ix-status-dashboard {
    max-width: 1200px;
}

.ix-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.ix-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    border-radius: 3px;
}

.ix-card h2 {
    font-size: 14px;
    padding: 8px 12px;
    margin: 0;
    border-bottom: 1px solid #ccd0d4;
}

.ix-card-content {
    padding: 12px;
}

.ix-status-indicator {
    display: flex;
    align-items: center;
    font-weight: 600;
    margin-bottom: 10px;
}

.ix-status-indicator .dashicons {
    font-size: 18px;
    margin-right: 8px;
}

.ix-status-indicator.connected {
    color: #46b450;
}

.ix-status-indicator.disconnected {
    color: #dc3232;
}

.ix-api-mode {
    margin-top: 8px;
}

.ix-api-mode .mode-live {
    color: #46b450;
}

.ix-api-mode .mode-test {
    color: #ffb900;
}

.ix-last-sync-time {
    font-size: 24px;
    font-weight: 400;
    margin-bottom: 10px;
}

.ix-sync-actions {
    display: flex;
    gap: 8px;
}

.ix-no-sync {
    margin-bottom: 10px;
}

.ix-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.ix-stat-item {
    text-align: center;
    padding: 10px;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
}

.ix-stat-number {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 5px;
}

.ix-stat-label {
    color: #646970;
}

.ix-stats-footer {
    margin-top: 15px;
    text-align: center;
}

.ix-recent-activity {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    border-radius: 3px;
    padding: 0;
}

.ix-recent-activity h2 {
    font-size: 14px;
    padding: 8px 12px;
    margin: 0;
    border-bottom: 1px solid #ccd0d4;
}

.ix-recent-activity .wp-list-table {
    margin: 0;
    border: none;
}

.ix-activity-footer {
    padding: 12px;
    border-top: 1px solid #ccd0d4;
}

.ix-no-activity {
    padding: 20px;
    text-align: center;
    color: #646970;
}

.ix-status-success {
    color: #46b450;
}

.ix-status-error {
    color: #dc3232;
}

.ix-status-pending {
    color: #ffb900;
}
</style>