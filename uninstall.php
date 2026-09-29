<?php
/**
 * Uninstall handler: remove all plugin data.
 *
 * @package Admin_Conflict_Logger
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'acl_error_logs' );
