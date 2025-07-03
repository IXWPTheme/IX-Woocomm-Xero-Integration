<?php
/**
 * Logs Status Template
 *
 * @package IX_Woo_Xero_Integration
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get log entries
$log_entries = IX_Helper::get_log_entries();
$log_levels = array(
    'error' => __('Error', 'ix-woo-xero'),
    'warning' => __('Warning', 'ix-woo-xero'),
    'notice' => __('Notice', 'ix-woo-xero'),
    'info' => __('Info', 'ix-woo-xero'),
    'debug' => __('Debug', 'ix-woo-xero')
);
$current_level = isset($_GET['log_level']) ? sanitize_text_field($_GET['log_level']) : 'all';
?>

<div class="wrap ix-logs-page">
    <h1><?php esc_html_e('Xero Integration Logs', 'ix-woo-xero'); ?></h1>

    <!-- Log Level Filter -->
    <div class="ix-log-filters">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="ix-woo-xero-status">
            <input type="hidden" name="tab" value="logs">
            
            <label for="log_level"><?php esc_html_e('Log Level:', 'ix-woo-xero'); ?></label>
            <select id="log_level" name="log_level" onchange="this.form.submit()">
                <option value="all" <?php selected($current_level, 'all'); ?>><?php esc_html_e('All Levels', 'ix-woo-xero'); ?></option>
                <?php foreach ($log_levels as $level => $label) : ?>
                    <option value="<?php echo esc_attr($level); ?>" <?php selected($current_level, $level); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if (!empty($_GET['log_level'])) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=ix-woo-xero-status&tab=logs')); ?>" class="button">
                    <?php esc_html_e('Clear Filter', 'ix-woo-xero'); ?>
                </a>
            <?php endif; ?>
        </form>
        
        <div class="ix-log-actions">
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ix-woo-xero-status&tab=logs&action=download_logs'), 'download_logs')); ?>" class="button">
                <?php esc_html_e('Download Logs', 'ix-woo-xero'); ?>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ix-woo-xero-status&tab=logs&action=clear_logs'), 'clear_logs')); ?>" class="button" onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'ix-woo-xero'); ?>')">
                <?php esc_html_e('Clear Logs', 'ix-woo-xero'); ?>
            </a>
        </div>
    </div>

    <!-- Log Table -->
    <div class="ix-log-table-container">
        <?php if (!empty($log_entries)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-date"><?php esc_html_e('Date', 'ix-woo-xero'); ?></th>
                        <th class="column-level"><?php esc_html_e('Level', 'ix-woo-xero'); ?></th>
                        <th class="column-context"><?php esc_html_e('Context', 'ix-woo-xero'); ?></th>
                        <th class="column-message"><?php esc_html_e('Message', 'ix-woo-xero'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log_entries as $entry) : 
                        if ('all' !== $current_level && $entry['level'] !== $current_level) {
                            continue;
                        }
                        ?>
                        <tr class="ix-log-level-<?php echo esc_attr($entry['level']); ?>">
                            <td class="column-date">
                                <?php echo esc_html(date_i18n('Y-m-d H:i:s', $entry['timestamp'])); ?>
                            </td>
                            <td class="column-level">
                                <span class="ix-log-level-badge level-<?php echo esc_attr($entry['level']); ?>">
                                    <?php echo esc_html(isset($log_levels[$entry['level']]) ? $log_levels[$entry['level']] : $entry['level']); ?>
                                </span>
                            </td>
                            <td class="column-context">
                                <?php echo esc_html($entry['context']); ?>
                            </td>
                            <td class="column-message">
                                <?php echo esc_html($entry['message']); ?>
                                <?php if (!empty($entry['data'])) : ?>
                                    <a href="#" class="ix-view-details" data-details="<?php echo esc_attr(wp_json_encode($entry['data'])); ?>">
                                        <?php esc_html_e('View Details', 'ix-woo-xero'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Log Details Modal -->
            <div id="ix-log-details-modal" class="ix-modal">
                <div class="ix-modal-content">
                    <div class="ix-modal-header">
                        <h3><?php esc_html_e('Log Entry Details', 'ix-woo-xero'); ?></h3>
                        <span class="ix-modal-close">&times;</span>
                    </div>
                    <div class="ix-modal-body">
                        <pre class="ix-log-details-content"></pre>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="ix-no-logs">
                <?php esc_html_e('No log entries found.', 'ix-woo-xero'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if (!empty($log_entries)) : ?>
        <div class="ix-log-pagination">
            <?php
            $big = 999999999; // need an unlikely integer
            echo paginate_links(array(
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1),
                'total' => ceil(count($log_entries) / 20) // 20 items per page
            ));
            ?>
        </div>
    <?php endif; ?>
</div>

<style>
.ix-logs-page {
    max-width: 1200px;
}

.ix-log-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    background: #fff;
    padding: 15px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.ix-log-filters select {
    margin-right: 10px;
}

.ix-log-actions {
    display: flex;
    gap: 8px;
}

.ix-log-table-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    margin-bottom: 20px;
}

.ix-log-table-container .wp-list-table {
    margin: 0;
    border: none;
}

.ix-no-logs {
    padding: 20px;
    text-align: center;
    color: #646970;
}

.ix-log-level-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.level-error {
    background-color: #f8d7da;
    color: #721c24;
}

.level-warning {
    background-color: #fff3cd;
    color: #856404;
}

.level-notice {
    background-color: #cce5ff;
    color: #004085;
}

.level-info {
    background-color: #d1ecf1;
    color: #0c5460;
}

.level-debug {
    background-color: #e2e3e5;
    color: #383d41;
}

.ix-view-details {
    margin-left: 10px;
    font-size: 12px;
}

.ix-log-pagination {
    text-align: center;
    margin-top: 20px;
}

/* Modal Styles */
.ix-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.ix-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 800px;
    box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
}

.ix-modal-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ix-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.ix-modal-close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.ix-modal-close:hover {
    color: #000;
}

.ix-modal-body {
    padding: 15px;
    max-height: 60vh;
    overflow: auto;
}

.ix-log-details-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 3px;
    white-space: pre-wrap;
    word-wrap: break-word;
    margin: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Open modal when view details is clicked
    $('.ix-view-details').on('click', function(e) {
        e.preventDefault();
        var details = $(this).data('details');
        $('#ix-log-details-modal .ix-log-details-content').text(JSON.stringify(JSON.parse(details), null, 2));
        $('#ix-log-details-modal').show();
    });
    
    // Close modal when X is clicked
    $('.ix-modal-close').on('click', function() {
        $('#ix-log-details-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).is('#ix-log-details-modal')) {
            $('#ix-log-details-modal').hide();
        }
    });
});
</script>