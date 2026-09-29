<?php
/**
 * PHPUnit bootstrap: minimal WordPress function stubs so the plugin class
 * can be loaded and unit-tested without a full WordPress install.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins');
}

$GLOBALS['acl_test_options'] = [];
$GLOBALS['acl_test_transients'] = [];
$GLOBALS['acl_test_can_manage'] = true;
$GLOBALS['acl_test_user_id'] = 1;

function add_action(...$args)
{
}

function add_filter(...$args)
{
}

function register_activation_hook(...$args)
{
}

function register_deactivation_hook(...$args)
{
}

function plugin_dir_path($file)
{
    return rtrim(dirname($file), '/\\') . '/';
}

function plugin_dir_url($file)
{
    return 'http://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
}

function plugin_basename($file)
{
    return basename(dirname($file)) . '/' . basename($file);
}

function get_option($name, $default = false)
{
    return array_key_exists($name, $GLOBALS['acl_test_options'])
        ? $GLOBALS['acl_test_options'][$name]
        : $default;
}

function update_option($name, $value)
{
    $GLOBALS['acl_test_options'][$name] = $value;
    return true;
}

function add_option($name, $value)
{
    if (!array_key_exists($name, $GLOBALS['acl_test_options'])) {
        $GLOBALS['acl_test_options'][$name] = $value;
    }
    return true;
}

function delete_option($name)
{
    unset($GLOBALS['acl_test_options'][$name]);
    return true;
}

function get_transient($name)
{
    return $GLOBALS['acl_test_transients'][$name] ?? false;
}

function set_transient($name, $value, $expiration = 0)
{
    $GLOBALS['acl_test_transients'][$name] = $value;
    return true;
}

function wp_create_nonce($action)
{
    return 'test-nonce';
}

function check_ajax_referer(...$args)
{
    return true;
}

function current_user_can($cap)
{
    return $cap === 'manage_options' ? (bool) $GLOBALS['acl_test_can_manage'] : false;
}

function get_current_user_id()
{
    return (int) $GLOBALS['acl_test_user_id'];
}

class ACL_Test_JSON_Response extends Exception
{
    public $payload;

    public function __construct($payload)
    {
        $this->payload = $payload;
        parent::__construct('wp_send_json');
    }
}

function wp_send_json_success($data = null, $status_code = null)
{
    throw new ACL_Test_JSON_Response(['success' => true, 'data' => $data, 'status' => $status_code]);
}

function wp_send_json_error($data = null, $status_code = null)
{
    throw new ACL_Test_JSON_Response(['success' => false, 'data' => $data, 'status' => $status_code]);
}

function sanitize_text_field($value)
{
    $value = is_scalar($value) ? (string) $value : '';
    $value = strip_tags($value);
    $value = preg_replace('/[\r\n\t ]+/', ' ', $value);
    return trim($value);
}

function sanitize_textarea_field($value)
{
    $value = is_scalar($value) ? (string) $value : '';
    return trim(strip_tags($value));
}

function wp_unslash($value)
{
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }
    return is_string($value) ? stripslashes($value) : $value;
}

function esc_url_raw($url)
{
    $url = trim((string) $url);
    return preg_match('#^(https?://|/)#i', $url) ? $url : '';
}

function absint($value)
{
    return abs((int) $value);
}

function current_time($type)
{
    return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
}

function wp_generate_uuid4()
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_plugin_data($file, $markup = true, $translate = true)
{
    return ['Name' => '', 'Version' => ''];
}

class ACL_Test_Theme
{
    public function get_stylesheet()
    {
        return 'twentytwentyfive';
    }

    public function get($key)
    {
        return $key === 'Name' ? 'Twenty Twenty-Five' : '';
    }
}

function wp_get_theme()
{
    return new ACL_Test_Theme();
}

function admin_url($path = '')
{
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function is_user_logged_in()
{
    return true;
}

function wp_enqueue_script(...$args)
{
}

function wp_enqueue_style(...$args)
{
}

function wp_localize_script(...$args)
{
}

function add_menu_page(...$args)
{
}

function __($text, $domain = null)
{
    return $text;
}

require_once dirname(__DIR__) . '/admin-conflict-logger.php';
