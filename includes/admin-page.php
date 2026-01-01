<?php
/**
 * Admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap acl-wrap">
    <h1><?php esc_html_e('Conflict Logger', 'admin-conflict-logger'); ?></h1>

    <div class="acl-header">
        <div class="acl-stats">
            <div class="acl-stat-box">
                <span class="acl-stat-number"><?php echo esc_html($error_count); ?></span>
                <span class="acl-stat-label"><?php esc_html_e('Total Errors', 'admin-conflict-logger'); ?></span>
            </div>
            <div class="acl-stat-box">
                <span class="acl-stat-number"><?php echo esc_html(count($by_plugin)); ?></span>
                <span class="acl-stat-label"><?php esc_html_e('Plugins Involved', 'admin-conflict-logger'); ?></span>
            </div>
        </div>

        <div class="acl-actions">
            <button type="button" class="button button-secondary" id="acl-refresh">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Refresh', 'admin-conflict-logger'); ?>
            </button>
            <button type="button" class="button button-secondary" id="acl-clear-logs" <?php echo $error_count === 0 ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Clear All Logs', 'admin-conflict-logger'); ?>
            </button>
        </div>
    </div>

    <?php if (!empty($by_plugin)) : ?>
    <div class="acl-summary">
        <h2><?php esc_html_e('Errors by Source', 'admin-conflict-logger'); ?></h2>
        <div class="acl-plugin-summary">
            <?php foreach ($by_plugin as $plugin_name => $count) : ?>
                <div class="acl-plugin-badge <?php echo $count > 5 ? 'acl-badge-danger' : ($count > 2 ? 'acl-badge-warning' : 'acl-badge-info'); ?>">
                    <strong><?php echo esc_html($plugin_name); ?></strong>
                    <span class="acl-badge-count"><?php echo esc_html($count); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="acl-logs">
        <h2><?php esc_html_e('Error Log', 'admin-conflict-logger'); ?></h2>

        <?php if (empty($logs)) : ?>
            <div class="acl-empty-state">
                <span class="dashicons dashicons-yes-alt"></span>
                <p><?php esc_html_e('No JavaScript errors have been logged yet.', 'admin-conflict-logger'); ?></p>
                <p class="description"><?php esc_html_e('Errors will appear here automatically when they occur.', 'admin-conflict-logger'); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped acl-table">
                <thead>
                    <tr>
                        <th class="column-time"><?php esc_html_e('Time', 'admin-conflict-logger'); ?></th>
                        <th class="column-error"><?php esc_html_e('Error', 'admin-conflict-logger'); ?></th>
                        <th class="column-source"><?php esc_html_e('Source', 'admin-conflict-logger'); ?></th>
                        <th class="column-suspect"><?php esc_html_e('Suspected Plugin', 'admin-conflict-logger'); ?></th>
                        <th class="column-page"><?php esc_html_e('Page', 'admin-conflict-logger'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'admin-conflict-logger'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr data-log-id="<?php echo esc_attr($log['id']); ?>">
                            <td class="column-time">
                                <span class="acl-time" title="<?php echo esc_attr($log['timestamp']); ?>">
                                    <?php echo esc_html(human_time_diff(strtotime($log['timestamp']), current_time('timestamp'))); ?>
                                    <?php esc_html_e('ago', 'admin-conflict-logger'); ?>
                                </span>
                            </td>
                            <td class="column-error">
                                <code class="acl-error-message"><?php echo esc_html(wp_trim_words($log['message'], 15)); ?></code>
                                <?php if (!empty($log['stack'])) : ?>
                                    <button type="button" class="button-link acl-show-stack" data-stack="<?php echo esc_attr($log['stack']); ?>">
                                        <?php esc_html_e('Show Stack', 'admin-conflict-logger'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="column-source">
                                <?php if (!empty($log['source'])) : ?>
                                    <code class="acl-source"><?php echo esc_html(basename($log['source'])); ?>:<?php echo esc_html($log['line']); ?></code>
                                <?php else : ?>
                                    <span class="acl-unknown"><?php esc_html_e('Unknown', 'admin-conflict-logger'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-suspect">
                                <?php if (!empty($log['suspected_plugin'])) : ?>
                                    <span class="acl-suspect acl-suspect-<?php echo esc_attr($log['suspected_plugin']['confidence']); ?>">
                                        <?php echo esc_html($log['suspected_plugin']['name']); ?>
                                        <span class="acl-confidence">(<?php echo esc_html($log['suspected_plugin']['confidence']); ?>)</span>
                                    </span>
                                <?php else : ?>
                                    <span class="acl-unknown"><?php esc_html_e('Unknown', 'admin-conflict-logger'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-page">
                                <?php if ($log['is_admin']) : ?>
                                    <span class="acl-location acl-admin"><?php esc_html_e('Admin', 'admin-conflict-logger'); ?></span>
                                <?php else : ?>
                                    <span class="acl-location acl-frontend"><?php esc_html_e('Frontend', 'admin-conflict-logger'); ?></span>
                                <?php endif; ?>
                                <code class="acl-page-hook"><?php echo esc_html($log['page_hook'] ?: wp_parse_url($log['page_url'], PHP_URL_PATH)); ?></code>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button-link acl-delete-log" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Stack trace modal -->
    <div id="acl-stack-modal" class="acl-modal" style="display: none;">
        <div class="acl-modal-content">
            <div class="acl-modal-header">
                <h3><?php esc_html_e('Stack Trace', 'admin-conflict-logger'); ?></h3>
                <button type="button" class="acl-modal-close">&times;</button>
            </div>
            <div class="acl-modal-body">
                <pre id="acl-stack-content"></pre>
            </div>
        </div>
    </div>
</div>
