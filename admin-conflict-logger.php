<?php
/**
 * Plugin Name: Admin Conflict Logger
 * Plugin URI: https://github.com/mortogo321/wordpress-admin-conflict-logger
 * Description: Logs JS errors with plugin context to find conflicts fast.
 * Version: 1.1.0
 * Author: Mor
 * Author URI: https://github.com/mortogo321
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: admin-conflict-logger
 * Requires at least: 6.4
 * Requires PHP: 8.2
 *
 * @package Admin_Conflict_Logger
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'ACL_VERSION', '1.1.0' );
define( 'ACL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class Admin_Conflict_Logger {

	/**
	 * Single instance.
	 *
	 * @var Admin_Conflict_Logger|null
	 */
	private static $instance = null;

	/**
	 * Error log option name.
	 */
	const OPTION_NAME = 'acl_error_logs';

	/**
	 * Max errors to store.
	 */
	const MAX_ERRORS = 100;

	/**
	 * Per-field input caps.
	 *
	 * Server-side enforcement; the JS logger truncates
	 * to the same limits before sending.
	 */
	const MAX_MESSAGE_LENGTH = 2000;
	const MAX_STACK_LENGTH   = 10000;
	const MAX_URL_LENGTH     = 2000;

	/**
	 * Rate limit: max logged events per window, per user.
	 */
	const RATE_LIMIT          = 20;
	const RATE_WINDOW_SECONDS = 60;

	/**
	 * Get instance.
	 *
	 * @return Admin_Conflict_Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		$hook = 'admin_enqueue_scripts';
		add_action( $hook, array( $this, 'enqueue_admin_scripts' ) );
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'enqueue_frontend_scripts' )
		);
		$ajax = 'wp_ajax_acl_log_error';
		add_action( $ajax, array( $this, 'ajax_log_error' ) );
		$clear = 'wp_ajax_acl_clear_logs';
		add_action( $clear, array( $this, 'ajax_clear_logs' ) );
		$delete = 'wp_ajax_acl_delete_log';
		add_action( $delete, array( $this, 'ajax_delete_log' ) );

		// Activation/deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		$deactivate = array( $this, 'deactivate' );
		register_deactivation_hook( __FILE__, $deactivate );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Translations are loaded automatically by WordPress.org.
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		if ( ! get_option( self::OPTION_NAME ) ) {
			add_option( self::OPTION_NAME, array() );
		}
	}

	/**
	 * Deactivation hook.
	 *
	 * Logs are intentionally kept so history survives reactivation.
	 */
	public function deactivate() {
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Conflict Logger', 'admin-conflict-logger' ),
			__( 'Conflict Logger', 'admin-conflict-logger' ),
			'manage_options',
			'admin-conflict-logger',
			array( $this, 'render_admin_page' ),
			'dashicons-warning',
			80
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Error logger on all admin pages.
		wp_enqueue_script(
			'acl-error-logger',
			ACL_PLUGIN_URL . 'assets/js/error-logger.js',
			array(),
			ACL_VERSION,
			true
		);

		wp_localize_script(
			'acl-error-logger',
			'aclConfig',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'acl_log_error' ),
				'activePlugins' => $this->get_active_plugins_info(),
				'currentPage'   => $hook,
				'isAdmin'       => true,
			)
		);

		// Admin page styles.
		$screen = 'toplevel_page_admin-conflict-logger';
		if ( $screen === $hook ) {
			wp_enqueue_style(
				'acl-admin-styles',
				ACL_PLUGIN_URL . 'assets/css/admin.css',
				array(),
				ACL_VERSION
			);

			wp_enqueue_script(
				'acl-admin-scripts',
				ACL_PLUGIN_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				ACL_VERSION,
				true
			);

			$clear_msg  = __(
				'Are you sure you want to clear all logs?',
				'admin-conflict-logger'
			);
			$delete_msg = __(
				'Are you sure you want to delete this log entry?',
				'admin-conflict-logger'
			);
			wp_localize_script(
				'acl-admin-scripts',
				'aclAdmin',
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'acl_admin_action' ),
					'confirmClear'  => $clear_msg,
					'confirmDelete' => $delete_msg,
				)
			);
		}
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * Only for administrators; catches frontend errors too.
	 */
	public function enqueue_frontend_scripts() {
		$can_log = is_user_logged_in();
		$can_log = $can_log && current_user_can( 'manage_options' );
		if ( ! $can_log ) {
			return;
		}

		wp_enqueue_script(
			'acl-error-logger',
			ACL_PLUGIN_URL . 'assets/js/error-logger.js',
			array(),
			ACL_VERSION,
			true
		);

		wp_localize_script(
			'acl-error-logger',
			'aclConfig',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'acl_log_error' ),
				'activePlugins' => $this->get_active_plugins_info(),
				'currentPage'   => 'frontend',
				'isAdmin'       => false,
			)
		);
	}

	/**
	 * Get active plugins info.
	 *
	 * @return array[] Each entry has path, name, and version keys.
	 */
	private function get_active_plugins_info() {
		$active_plugins = get_option( 'active_plugins', array() );
		$plugins_info   = array();

		foreach ( $active_plugins as $plugin_path ) {
			$file = WP_PLUGIN_DIR . '/' . $plugin_path;
			$data = get_plugin_data( $file, false, false );

			$name = basename( $plugin_path, '.php' );
			if ( ! empty( $data['Name'] ) ) {
				$name = $data['Name'];
			}

			$version = 'unknown';
			if ( ! empty( $data['Version'] ) ) {
				$version = $data['Version'];
			}

			$plugins_info[] = array(
				'path'    => $plugin_path,
				'name'    => $name,
				'version' => $version,
			);
		}

		return $plugins_info;
	}

	/**
	 * AJAX handler for logging errors.
	 */
	public function ajax_log_error() {
		check_ajax_referer( 'acl_log_error', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$forbidden = array( 'message' => 'Forbidden' );
			wp_send_json_error( $forbidden, 403 );
		}

		if ( ! $this->check_rate_limit() ) {
			$limited = array( 'message' => 'Rate limit exceeded' );
			wp_send_json_error( $limited, 429 );
		}

		$raw_source = '';
		if ( isset( $_POST['source'] ) ) {
			$raw_source = sanitize_text_field(
				wp_unslash( $_POST['source'] )
			);
		}
		$raw_stack = '';
		if ( isset( $_POST['stack'] ) ) {
			$raw_stack = sanitize_textarea_field(
				wp_unslash( $_POST['stack'] )
			);
		}
		$source = self::truncate( $raw_source, self::MAX_URL_LENGTH );
		$stack  = self::truncate( $raw_stack, self::MAX_STACK_LENGTH );

		$raw_message = '';
		if ( isset( $_POST['message'] ) ) {
			$raw_message = sanitize_text_field(
				wp_unslash( $_POST['message'] )
			);
		}
		$message = self::truncate(
			$raw_message,
			self::MAX_MESSAGE_LENGTH
		);

		$raw_page = '';
		if ( isset( $_POST['pageUrl'] ) ) {
			$raw_page = esc_url_raw( wp_unslash( $_POST['pageUrl'] ) );
		}
		$page_url = self::truncate( $raw_page, self::MAX_URL_LENGTH );

		$page_hook = '';
		if ( isset( $_POST['pageHook'] ) ) {
			$page_hook = sanitize_text_field(
				wp_unslash( $_POST['pageHook'] )
			);
		}

		$is_admin = ! empty( $_POST['isAdmin'] );
		$is_admin = $is_admin && '0' !== $_POST['isAdmin'];

		$raw_agent = '';
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$raw_agent = sanitize_text_field(
				wp_unslash( $_SERVER['HTTP_USER_AGENT'] )
			);
		}
		$user_agent = self::truncate( $raw_agent, 1000 );

		$line = 0;
		if ( isset( $_POST['line'] ) ) {
			$line = absint( $_POST['line'] );
		}
		$column = 0;
		if ( isset( $_POST['column'] ) ) {
			$column = absint( $_POST['column'] );
		}

		$error_data = array(
			'timestamp'        => current_time( 'mysql' ),
			'message'          => $message,
			'source'           => $source,
			'line'             => $line,
			'column'           => $column,
			'stack'            => $stack,
			'page_url'         => $page_url,
			'page_hook'        => $page_hook,
			'is_admin'         => $is_admin,
			'user_agent'       => $user_agent,
			'active_plugins'   => $this->get_active_plugins_info(),
			'suspected_plugin' => $this->detect_suspected_plugin(
				$source,
				$stack
			),
		);

		$this->save_error_log( $error_data );

		wp_send_json_success( array( 'logged' => true ) );
	}

	/**
	 * Truncate a string to a max length (multibyte-safe).
	 *
	 * @param mixed $value      Value to truncate.
	 * @param int   $max_length Maximum length in characters.
	 * @return string
	 */
	public static function truncate( $value, $max_length ) {
		$value  = (string) $value;
		$has_mb = function_exists( 'mb_strlen' );
		$has_mb = $has_mb && function_exists( 'mb_substr' );
		if ( $has_mb ) {
			$too_long = mb_strlen( $value ) > $max_length;
			if ( $too_long ) {
				return mb_substr( $value, 0, $max_length );
			}
			return $value;
		}

		if ( strlen( $value ) > $max_length ) {
			return substr( $value, 0, $max_length );
		}
		return $value;
	}

	/**
	 * Sliding-window rate limit, keyed per user.
	 *
	 * Falls back to IP for edge cases. Returns false when over limit.
	 *
	 * @return bool
	 */
	private function check_rate_limit() {
		$key = 'acl_rate_' . get_current_user_id();
		if ( 0 === get_current_user_id() ) {
			$raw_ip = 'unknown';
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				$raw_ip = sanitize_text_field(
					wp_unslash( $_SERVER['REMOTE_ADDR'] )
				);
			}
			$key = 'acl_rate_ip_' . md5( $raw_ip );
		}

		$hits = (int) get_transient( $key );
		if ( $hits >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $hits + 1, self::RATE_WINDOW_SECONDS );
		return true;
	}

	/**
	 * Detect suspected plugin from error source/stack.
	 *
	 * @param string $source Error source URL.
	 * @param string $stack  Error stack trace.
	 * @return array|null
	 */
	private function detect_suspected_plugin( $source, $stack ) {
		$combined       = $source . ' ' . $stack;
		$active_plugins = get_option( 'active_plugins', array() );

		$names = array();
		foreach ( $active_plugins as $plugin_path ) {
			$file = WP_PLUGIN_DIR . '/' . $plugin_path;
			$data = get_plugin_data( $file, false, false );
			$dir  = dirname( $plugin_path );

			$folder = '';
			if ( '.' !== $dir ) {
				$folder = $dir;
			}

			$name = basename( $plugin_path, '.php' );
			if ( ! empty( $data['Name'] ) ) {
				$name = $data['Name'];
			}

			$names[ $plugin_path ] = array(
				'folder' => $folder,
				'name'   => $name,
			);
		}

		$theme = wp_get_theme();

		return self::detect_suspect(
			$combined,
			$names,
			$theme->get_stylesheet(),
			$theme->get( 'Name' )
		);
	}

	/**
	 * Pure suspect-detection helper (no WordPress I/O).
	 *
	 * Keeps the heuristic unit-testable. $plugins maps plugin
	 * path => [ 'folder' => ..., 'name' => ... ].
	 *
	 * @param string $combined   Source URL plus stack trace.
	 * @param array  $plugins    Plugin path map.
	 * @param string $stylesheet Active theme stylesheet slug.
	 * @param string $theme_name Active theme display name.
	 * @return array|null
	 */
	public static function detect_suspect(
		$combined,
		array $plugins,
		$stylesheet,
		$theme_name
	) {
		foreach ( $plugins as $plugin_path => $info ) {
			$folder = isset( $info['folder'] ) ? $info['folder'] : '';
			if ( '' !== $folder ) {
				$found = stripos( $combined, $folder );
				if ( false !== $found ) {
					$name = isset( $info['name'] ) ? $info['name'] : $folder;
					return array(
						'path'       => $plugin_path,
						'name'       => $name,
						'confidence' => 'high',
					);
				}
			}
		}

		// Check theme.
		if ( '' !== $stylesheet ) {
			$found = stripos( $combined, $stylesheet );
			if ( false !== $found ) {
				return array(
					'path'       => 'theme:' . $stylesheet,
					'name'       => $theme_name,
					'confidence' => 'high',
				);
			}
		}

		return null;
	}

	/**
	 * Save error to log.
	 *
	 * @param array $error_data Sanitized error fields.
	 */
	private function save_error_log( $error_data ) {
		$logs = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Generate unique ID.
		$error_data['id'] = $this->generate_log_id();

		// Add to beginning of array.
		array_unshift( $logs, $error_data );

		// Limit to max errors.
		$logs = array_slice( $logs, 0, self::MAX_ERRORS );

		update_option( self::OPTION_NAME, $logs );
	}

	/**
	 * Generate a unique log ID (UUID-based when available).
	 *
	 * @return string
	 */
	private function generate_log_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			$uuid = wp_generate_uuid4();
			return 'acl_' . str_replace( '-', '', $uuid );
		}
		return 'acl_' . bin2hex( random_bytes( 8 ) );
	}

	/**
	 * AJAX handler for clearing logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'acl_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$denied = array( 'message' => 'Unauthorized' );
			wp_send_json_error( $denied );
		}

		update_option( self::OPTION_NAME, array() );

		wp_send_json_success( array( 'cleared' => true ) );
	}

	/**
	 * AJAX handler for deleting single log.
	 */
	public function ajax_delete_log() {
		check_ajax_referer( 'acl_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			$denied = array( 'message' => 'Unauthorized' );
			wp_send_json_error( $denied );
		}

		$log_id = '';
		if ( isset( $_POST['log_id'] ) ) {
			$log_id = sanitize_text_field(
				wp_unslash( $_POST['log_id'] )
			);
		}
		$logs = get_option( self::OPTION_NAME, array() );

		$logs = array_filter(
			$logs,
			function ( $log ) use ( $log_id ) {
				return $log['id'] !== $log_id;
			}
		);

		update_option( self::OPTION_NAME, array_values( $logs ) );

		wp_send_json_success( array( 'deleted' => true ) );
	}

	/**
	 * Get error logs.
	 *
	 * @return array
	 */
	public function get_error_logs() {
		return get_option( self::OPTION_NAME, array() );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$logs        = $this->get_error_logs();
		$error_count = count( $logs );

		// Group by suspected plugin.
		$unknown   = __( 'Unknown', 'admin-conflict-logger' );
		$by_plugin = array();
		foreach ( $logs as $log ) {
			if ( isset( $log['suspected_plugin']['name'] ) ) {
				$plugin_name = $log['suspected_plugin']['name'];
			} else {
				$plugin_name = $unknown;
			}
			if ( ! isset( $by_plugin[ $plugin_name ] ) ) {
				$by_plugin[ $plugin_name ] = 0;
			}
			++$by_plugin[ $plugin_name ];
		}
		arsort( $by_plugin );

		include ACL_PLUGIN_DIR . 'includes/admin-page.php';
	}
}

// Initialize plugin.
Admin_Conflict_Logger::get_instance();
