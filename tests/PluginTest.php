<?php

use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['acl_test_options'] = [];
        $GLOBALS['acl_test_transients'] = [];
        $GLOBALS['acl_test_can_manage'] = true;
        $GLOBALS['acl_test_user_id'] = 1;
        $_POST = [];
    }

    private function plugins(): array
    {
        return [
            'contact-form-7/wp-contact-form-7.php' => ['folder' => 'contact-form-7', 'name' => 'Contact Form 7'],
            'woocommerce/woocommerce.php' => ['folder' => 'woocommerce', 'name' => 'WooCommerce'],
            'hello.php' => ['folder' => '', 'name' => 'Hello Dolly'],
        ];
    }

    public function test_detect_suspect_matches_plugin_folder_in_source(): void
    {
        $result = Admin_Conflict_Logger::detect_suspect(
            'https://example.test/wp-content/plugins/woocommerce/assets/js/frontend.js TypeError',
            $this->plugins(),
            'twentytwentyfive',
            'Twenty Twenty-Five'
        );

        $this->assertSame('woocommerce/woocommerce.php', $result['path']);
        $this->assertSame('WooCommerce', $result['name']);
        $this->assertSame('high', $result['confidence']);
    }

    public function test_detect_suspect_matches_plugin_folder_in_stack(): void
    {
        $result = Admin_Conflict_Logger::detect_suspect(
            'https://example.test/wp-content/plugins/x.js Error',
            $this->plugins(),
            'twentytwentyfive',
            'Twenty Twenty-Five'
        );

        // 'x.js' source matches nothing; stack carries the plugin path.
        $this->assertNull($result);

        $result = Admin_Conflict_Logger::detect_suspect(
            'Error at foo (https://example.test/wp-content/plugins/contact-form-7/includes/js/scripts.js:10:5)',
            $this->plugins(),
            'twentytwentyfive',
            'Twenty Twenty-Five'
        );

        $this->assertSame('contact-form-7/wp-contact-form-7.php', $result['path']);
    }

    public function test_detect_suspect_matches_theme(): void
    {
        $result = Admin_Conflict_Logger::detect_suspect(
            'Error in https://example.test/wp-content/themes/twentytwentyfive/assets/theme.js',
            $this->plugins(),
            'twentytwentyfive',
            'Twenty Twenty-Five'
        );

        $this->assertSame('theme:twentytwentyfive', $result['path']);
        $this->assertSame('Twenty Twenty-Five', $result['name']);
    }

    public function test_detect_suspect_returns_null_for_unknown_source(): void
    {
        $result = Admin_Conflict_Logger::detect_suspect(
            'https://cdn.example.net/lib/react.min.js ResizeObserver loop completed',
            $this->plugins(),
            'twentytwentyfive',
            'Twenty Twenty-Five'
        );

        $this->assertNull($result);
    }

    public function test_detect_suspect_is_case_insensitive(): void
    {
        $result = Admin_Conflict_Logger::detect_suspect(
            'https://example.test/wp-content/plugins/WooCommerce/assets/x.js boom',
            $this->plugins(),
            'twentytwentyfive',
            'Twenty Twenty-Five'
        );

        $this->assertSame('woocommerce/woocommerce.php', $result['path']);
    }

    public function test_detect_suspect_prefers_plugin_over_theme(): void
    {
        $result = Admin_Conflict_Logger::detect_suspect(
            'woocommerce broke twentytwentyfive layout',
            $this->plugins(),
            'twentytwentyfive',
            'Twenty Twenty-Five'
        );

        $this->assertSame('woocommerce/woocommerce.php', $result['path']);
    }

    public function test_truncate_short_string_unchanged(): void
    {
        $this->assertSame('hello', Admin_Conflict_Logger::truncate('hello', 10));
    }

    public function test_truncate_long_string_capped(): void
    {
        $long = str_repeat('a', 5000);
        $this->assertSame(2000, strlen(Admin_Conflict_Logger::truncate($long, 2000)));
    }

    public function test_truncate_multibyte_safe(): void
    {
        $value = str_repeat('é', 100);
        $result = Admin_Conflict_Logger::truncate($value, 10);
        $this->assertSame(10, mb_strlen($result));
    }

    public function test_save_error_log_caps_at_max_errors(): void
    {
        $plugin = Admin_Conflict_Logger::get_instance();
        $method = new ReflectionMethod(Admin_Conflict_Logger::class, 'save_error_log');

        for ($i = 0; $i < 120; $i++) {
            $method->invoke($plugin, ['message' => "error {$i}"]);
        }

        $logs = get_option(Admin_Conflict_Logger::OPTION_NAME, []);
        $this->assertCount(100, $logs);
        // Newest first.
        $this->assertSame('error 119', $logs[0]['message']);
        $this->assertSame('error 20', $logs[99]['message']);
    }

    public function test_save_error_log_assigns_unique_ids(): void
    {
        $plugin = Admin_Conflict_Logger::get_instance();
        $method = new ReflectionMethod(Admin_Conflict_Logger::class, 'save_error_log');

        $method->invoke($plugin, ['message' => 'one']);
        $method->invoke($plugin, ['message' => 'two']);

        $logs = get_option(Admin_Conflict_Logger::OPTION_NAME, []);
        $this->assertStringStartsWith('acl_', $logs[0]['id']);
        $this->assertNotSame($logs[0]['id'], $logs[1]['id']);
    }

    public function test_ajax_log_error_requires_capability(): void
    {
        $GLOBALS['acl_test_can_manage'] = false;
        $plugin = Admin_Conflict_Logger::get_instance();

        try {
            $plugin->ajax_log_error();
            $this->fail('Expected ACL_Test_JSON_Response');
        } catch (ACL_Test_JSON_Response $e) {
            $this->assertFalse($e->payload['success']);
            $this->assertSame(403, $e->payload['status']);
        }

        $this->assertSame([], get_option(Admin_Conflict_Logger::OPTION_NAME, []));
    }

    public function test_ajax_log_error_enforces_rate_limit(): void
    {
        $plugin = Admin_Conflict_Logger::get_instance();
        $_POST = ['message' => 'boom', 'source' => 'x.js'];

        for ($i = 0; $i < Admin_Conflict_Logger::RATE_LIMIT; $i++) {
            try {
                $plugin->ajax_log_error();
            } catch (ACL_Test_JSON_Response $e) {
                $this->assertTrue($e->payload['success']);
            }
        }

        try {
            $plugin->ajax_log_error();
            $this->fail('Expected rate-limit rejection');
        } catch (ACL_Test_JSON_Response $e) {
            $this->assertFalse($e->payload['success']);
            $this->assertSame(429, $e->payload['status']);
        }
    }

    public function test_constants_match_documented_limits(): void
    {
        $this->assertSame(100, Admin_Conflict_Logger::MAX_ERRORS);
        $this->assertSame(2000, Admin_Conflict_Logger::MAX_MESSAGE_LENGTH);
        $this->assertSame(10000, Admin_Conflict_Logger::MAX_STACK_LENGTH);
        $this->assertSame('1.1.0', ACL_VERSION);
    }
}
