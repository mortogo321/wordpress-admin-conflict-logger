<?php
/**
 * Admin page template.
 *
 * @package Admin_Conflict_Logger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap acl-wrap">
	<h1><?php esc_html_e( 'Conflict Logger', 'admin-conflict-logger' ); ?></h1>

	<div class="acl-header">
		<div class="acl-stats">
			<div class="acl-stat-box">
				<span class="acl-stat-number"><?php echo esc_html( $error_count ); ?></span>
				<span class="acl-stat-label"><?php esc_html_e( 'Total Errors', 'admin-conflict-logger' ); ?></span>
			</div>
			<div class="acl-stat-box">
				<span class="acl-stat-number"><?php echo esc_html( count( $by_plugin ) ); ?></span>
				<span class="acl-stat-label"><?php esc_html_e( 'Plugins Involved', 'admin-conflict-logger' ); ?></span>
			</div>
		</div>

		<div class="acl-actions">
			<button type="button" class="button button-secondary" id="acl-refresh">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Refresh', 'admin-conflict-logger' ); ?>
			</button>
			<?php $clear_disabled = 0 === $error_count ? 'disabled' : ''; ?>
			<button type="button" class="button button-secondary" id="acl-clear-logs" <?php echo esc_attr( $clear_disabled ); ?>>
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Clear All Logs', 'admin-conflict-logger' ); ?>
			</button>
		</div>
	</div>

	<?php if ( ! empty( $by_plugin ) ) : ?>
	<div class="acl-summary">
		<h2><?php esc_html_e( 'Errors by Source', 'admin-conflict-logger' ); ?></h2>
		<div class="acl-plugin-summary">
			<?php
			foreach ( $by_plugin as $plugin_name => $count ) :
				$badge_class = 'acl-badge-info';
				if ( $count > 5 ) {
					$badge_class = 'acl-badge-danger';
				} elseif ( $count > 2 ) {
					$badge_class = 'acl-badge-warning';
				}
				?>
				<div class="acl-plugin-badge <?php echo esc_attr( $badge_class ); ?>">
					<strong><?php echo esc_html( $plugin_name ); ?></strong>
					<span class="acl-badge-count"><?php echo esc_html( $count ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="acl-logs">
		<h2><?php esc_html_e( 'Error Log', 'admin-conflict-logger' ); ?></h2>

		<?php if ( empty( $logs ) ) : ?>
			<div class="acl-empty-state">
				<span class="dashicons dashicons-yes-alt"></span>
				<p><?php esc_html_e( 'No JavaScript errors have been logged yet.', 'admin-conflict-logger' ); ?></p>
				<p class="description"><?php esc_html_e( 'Errors will appear here automatically when they occur.', 'admin-conflict-logger' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped acl-table">
				<thead>
					<tr>
						<th class="column-time"><?php esc_html_e( 'Time', 'admin-conflict-logger' ); ?></th>
						<th class="column-error"><?php esc_html_e( 'Error', 'admin-conflict-logger' ); ?></th>
						<th class="column-source"><?php esc_html_e( 'Source', 'admin-conflict-logger' ); ?></th>
						<th class="column-suspect"><?php esc_html_e( 'Suspected Plugin', 'admin-conflict-logger' ); ?></th>
						<th class="column-page"><?php esc_html_e( 'Page', 'admin-conflict-logger' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'admin-conflict-logger' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $logs as $log ) :
						$log_id        = isset( $log['id'] ) ? $log['id'] : '';
						$log_timestamp = isset( $log['timestamp'] ) ? $log['timestamp'] : '';
						$log_message   = isset( $log['message'] ) ? $log['message'] : '';
						$log_stack     = isset( $log['stack'] ) ? $log['stack'] : '';
						$log_source    = isset( $log['source'] ) ? $log['source'] : '';
						$log_line      = isset( $log['line'] ) ? $log['line'] : 0;
						$log_page_hook = isset( $log['page_hook'] ) ? $log['page_hook'] : '';
						$log_page_url  = isset( $log['page_url'] ) ? $log['page_url'] : '';
						$log_suspect   = isset( $log['suspected_plugin'] ) ? $log['suspected_plugin'] : null;
						$log_is_admin  = ! isset( $log['is_admin'] ) || $log['is_admin'];
						if ( '' !== $log_timestamp ) {
							$log_time = strtotime( $log_timestamp );
						} else {
							$log_time = false;
						}
						// Bound the data attribute; the full stack stays in the DB log.
						if ( function_exists( 'mb_substr' ) ) {
							$stack_attr = mb_substr( $log_stack, 0, 4000 );
						} else {
							$stack_attr = substr( $log_stack, 0, 4000 );
						}
						if ( '' !== $log_page_hook ) {
							$page_label = $log_page_hook;
						} else {
							$page_label = (string) wp_parse_url( $log_page_url, PHP_URL_PATH );
						}
						$suspect_name = __( 'Unknown', 'admin-conflict-logger' );
						$suspect_conf = 'low';
						if ( ! empty( $log_suspect ) ) {
							if ( isset( $log_suspect['name'] ) ) {
								$suspect_name = $log_suspect['name'];
							}
							if ( isset( $log_suspect['confidence'] ) ) {
								$suspect_conf = $log_suspect['confidence'];
							}
						}
						if ( false !== $log_time ) {
							$time_label = human_time_diff( $log_time, time() );
						} else {
							$time_label = $log_timestamp;
						}
						?>
						<tr data-log-id="<?php echo esc_attr( $log_id ); ?>">
							<td class="column-time">
								<span class="acl-time" title="<?php echo esc_attr( $log_timestamp ); ?>">
									<?php echo esc_html( $time_label ); ?>
									<?php esc_html_e( 'ago', 'admin-conflict-logger' ); ?>
								</span>
							</td>
							<td class="column-error">
								<code class="acl-error-message"><?php echo esc_html( wp_trim_words( $log_message, 15 ) ); ?></code>
								<?php if ( '' !== $log_stack ) : ?>
									<button type="button" class="button-link acl-show-stack" data-stack="<?php echo esc_attr( $stack_attr ); ?>">
										<?php esc_html_e( 'Show Stack', 'admin-conflict-logger' ); ?>
									</button>
								<?php endif; ?>
							</td>
							<td class="column-source">
								<?php if ( '' !== $log_source ) : ?>
									<code class="acl-source"><?php echo esc_html( basename( $log_source ) ); ?>:<?php echo esc_html( $log_line ); ?></code>
								<?php else : ?>
									<span class="acl-unknown"><?php esc_html_e( 'Unknown', 'admin-conflict-logger' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-suspect">
								<?php if ( ! empty( $log_suspect ) ) : ?>
									<span class="acl-suspect acl-suspect-<?php echo esc_attr( $suspect_conf ); ?>">
										<?php echo esc_html( $suspect_name ); ?>
										<span class="acl-confidence">(<?php echo esc_html( $suspect_conf ); ?>)</span>
									</span>
								<?php else : ?>
									<span class="acl-unknown"><?php esc_html_e( 'Unknown', 'admin-conflict-logger' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="column-page">
								<?php if ( $log_is_admin ) : ?>
									<span class="acl-location acl-admin"><?php esc_html_e( 'Admin', 'admin-conflict-logger' ); ?></span>
								<?php else : ?>
									<span class="acl-location acl-frontend"><?php esc_html_e( 'Frontend', 'admin-conflict-logger' ); ?></span>
								<?php endif; ?>
								<code class="acl-page-hook"><?php echo esc_html( $page_label ); ?></code>
							</td>
							<td class="column-actions">
								<button type="button" class="button-link acl-delete-log" data-log-id="<?php echo esc_attr( $log_id ); ?>">
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
				<h3><?php esc_html_e( 'Stack Trace', 'admin-conflict-logger' ); ?></h3>
				<button type="button" class="acl-modal-close">&times;</button>
			</div>
			<div class="acl-modal-body">
				<pre id="acl-stack-content"></pre>
			</div>
		</div>
	</div>
</div>
