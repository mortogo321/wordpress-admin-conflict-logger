<?php
/**
 * Plugin Name: Admin Conflict Logger
 * Plugin URI: https://github.com/mortogo321/wordpress-admin-conflict-logger
 * Description: Automatically logs JavaScript errors with plugin context to help identify conflicts quickly.
 * Version: 1.0.0
 * Author: Mor
 * Author URI: https://github.com/mortogo321
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: admin-conflict-logger
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ACL_VERSION', '1.0.0');
define('ACL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Admin_Conflict_Logger {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Error log option name
     */
    const OPTION_NAME = 'acl_error_logs';

    /**
     * Max errors to store
     */
    const MAX_ERRORS = 100;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('wp_ajax_acl_log_error', [$this, 'ajax_log_error']);
        add_action('wp_ajax_nopriv_acl_log_error', [$this, 'ajax_log_error']);
        add_action('wp_ajax_acl_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_acl_delete_log', [$this, 'ajax_delete_log']);

        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Translations are loaded automatically by WordPress.org
    }

    /**
     * Activation hook
     */
    public function activate() {
        if (!get_option(self::OPTION_NAME)) {
            add_option(self::OPTION_NAME, []);
        }
    }

    /**
     * Deactivation hook
     */
    public function deactivate() {
        // Optionally clear logs on deactivation
        // delete_option(self::OPTION_NAME);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Conflict Logger', 'admin-conflict-logger'),
            __('Conflict Logger', 'admin-conflict-logger'),
            'manage_options',
            'admin-conflict-logger',
            [$this, 'render_admin_page'],
            'dashicons-warning',
            80
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Error logger on all admin pages
        wp_enqueue_script(
            'acl-error-logger',
            ACL_PLUGIN_URL . 'assets/js/error-logger.js',
            [],
            ACL_VERSION,
            true
        );

        wp_localize_script('acl-error-logger', 'aclConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acl_log_error'),
            'activePlugins' => $this->get_active_plugins_info(),
            'currentPage' => $hook,
            'isAdmin' => true,
        ]);

        // Admin page styles
        if ($hook === 'toplevel_page_admin-conflict-logger') {
            wp_enqueue_style(
                'acl-admin-styles',
                ACL_PLUGIN_URL . 'assets/css/admin.css',
                [],
                ACL_VERSION
            );

            wp_enqueue_script(
                'acl-admin-scripts',
                ACL_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                ACL_VERSION,
                true
            );

            wp_localize_script('acl-admin-scripts', 'aclAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('acl_admin_action'),
                'confirmClear' => __('Are you sure you want to clear all logs?', 'admin-conflict-logger'),
                'confirmDelete' => __('Are you sure you want to delete this log entry?', 'admin-conflict-logger'),
            ]);
        }
    }

    /**
     * Enqueue frontend scripts (optional - for catching frontend errors too)
     */
    public function enqueue_frontend_scripts() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_script(
            'acl-error-logger',
            ACL_PLUGIN_URL . 'assets/js/error-logger.js',
            [],
            ACL_VERSION,
            true
        );

        wp_localize_script('acl-error-logger', 'aclConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acl_log_error'),
            'activePlugins' => $this->get_active_plugins_info(),
            'currentPage' => 'frontend',
            'isAdmin' => false,
        ]);
    }

    /**
     * Get active plugins info
     */
    private function get_active_plugins_info() {
        $active_plugins = get_option('active_plugins', []);
        $plugins_info = [];

        foreach ($active_plugins as $plugin_path) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path, false, false);
            $plugins_info[] = [
                'path' => $plugin_path,
                'name' => $plugin_data['Name'] ?: basename($plugin_path, '.php'),
                'version' => $plugin_data['Version'] ?: 'unknown',
            ];
        }

        return $plugins_info;
    }

    /**
     * AJAX handler for logging errors
     */
    public function ajax_log_error() {
        check_ajax_referer('acl_log_error', 'nonce');

        $error_data = [
            'timestamp' => current_time('mysql'),
            'message' => sanitize_text_field(wp_unslash($_POST['message'] ?? '')),
            'source' => esc_url_raw(wp_unslash($_POST['source'] ?? '')),
            'line' => absint($_POST['line'] ?? 0),
            'column' => absint($_POST['column'] ?? 0),
            'stack' => sanitize_textarea_field(wp_unslash($_POST['stack'] ?? '')),
            'page_url' => esc_url_raw(wp_unslash($_POST['pageUrl'] ?? '')),
            'page_hook' => sanitize_text_field(wp_unslash($_POST['pageHook'] ?? '')),
            'is_admin' => !empty($_POST['isAdmin']),
            'user_agent' => sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'active_plugins' => $this->get_active_plugins_info(),
            'suspected_plugin' => $this->detect_suspected_plugin(
                sanitize_text_field(wp_unslash($_POST['source'] ?? '')),
                sanitize_textarea_field(wp_unslash($_POST['stack'] ?? ''))
            ),
        ];

        $this->save_error_log($error_data);

        wp_send_json_success(['logged' => true]);
    }

    /**
     * Detect suspected plugin from error source/stack
     */
    private function detect_suspected_plugin($source, $stack) {
        $combined = $source . ' ' . $stack;
        $active_plugins = get_option('active_plugins', []);

        foreach ($active_plugins as $plugin_path) {
            $plugin_folder = dirname($plugin_path);
            if ($plugin_folder !== '.' && stripos($combined, $plugin_folder) !== false) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_path, false, false);
                return [
                    'path' => $plugin_path,
                    'name' => $plugin_data['Name'] ?: $plugin_folder,
                    'confidence' => 'high',
                ];
            }
        }

        // Check theme
        $theme = wp_get_theme();
        if (stripos($combined, $theme->get_stylesheet()) !== false) {
            return [
                'path' => 'theme:' . $theme->get_stylesheet(),
                'name' => $theme->get('Name'),
                'confidence' => 'high',
            ];
        }

        return null;
    }

    /**
     * Save error to log
     */
    private function save_error_log($error_data) {
        $logs = get_option(self::OPTION_NAME, []);

        // Generate unique ID
        $error_data['id'] = uniqid('acl_');

        // Add to beginning of array
        array_unshift($logs, $error_data);

        // Limit to max errors
        $logs = array_slice($logs, 0, self::MAX_ERRORS);

        update_option(self::OPTION_NAME, $logs);
    }

    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('acl_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        update_option(self::OPTION_NAME, []);

        wp_send_json_success(['cleared' => true]);
    }

    /**
     * AJAX handler for deleting single log
     */
    public function ajax_delete_log() {
        check_ajax_referer('acl_admin_action', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $log_id = sanitize_text_field(wp_unslash($_POST['log_id'] ?? ''));
        $logs = get_option(self::OPTION_NAME, []);

        $logs = array_filter($logs, function($log) use ($log_id) {
            return $log['id'] !== $log_id;
        });

        update_option(self::OPTION_NAME, array_values($logs));

        wp_send_json_success(['deleted' => true]);
    }

    /**
     * Get error logs
     */
    public function get_error_logs() {
        return get_option(self::OPTION_NAME, []);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $logs = $this->get_error_logs();
        $error_count = count($logs);

        // Group by suspected plugin
        $by_plugin = [];
        foreach ($logs as $log) {
            $plugin_name = $log['suspected_plugin']['name'] ?? __('Unknown', 'admin-conflict-logger');
            if (!isset($by_plugin[$plugin_name])) {
                $by_plugin[$plugin_name] = 0;
            }
            $by_plugin[$plugin_name]++;
        }
        arsort($by_plugin);

        include ACL_PLUGIN_DIR . 'includes/admin-page.php';
    }
}

// Initialize plugin
Admin_Conflict_Logger::get_instance();
