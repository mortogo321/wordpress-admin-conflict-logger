=== Admin Conflict Logger ===
Contributors: mortogo321
Tags: debug, conflict, error, troubleshooting, developer
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically logs JavaScript errors with plugin context to help identify conflicts quickly.

== Description ==

**Stop wasting hours deactivating plugins one-by-one to find conflicts!**

Admin Conflict Logger automatically captures JavaScript errors and identifies which plugin is likely causing the issue. When an error occurs, the plugin logs:

* Error message and stack trace
* Source file and line number
* Which plugins were active
* **Suspected plugin** based on error source analysis

= Features =

* **Automatic Error Logging** - No configuration needed, just activate
* **Smart Plugin Detection** - Identifies the likely culprit plugin from error sources
* **Admin & Frontend Monitoring** - Catches errors everywhere (frontend only for admins)
* **Clean Dashboard** - View all errors with filtering and statistics
* **Performance Focused** - Minimal footprint, debounced logging
* **Privacy Friendly** - All data stored locally, no external services

= Why This Plugin? =

33% of WordPress users say troubleshooting plugin conflicts takes too long. This plugin aims to reduce that time from hours to minutes by automatically identifying the source of JavaScript errors.

= Use Cases =

* Debug why your checkout suddenly stopped working
* Find which plugin is causing admin panel errors
* Monitor site health after plugin updates
* Identify conflicts before users report them

== Installation ==

1. Upload the `admin-conflict-logger` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to 'Conflict Logger' in the admin menu to view logs

That's it! The plugin starts logging automatically.

== Frequently Asked Questions ==

= Does this slow down my site? =

No. The error logger is lightweight (< 5KB) and only sends data when an error actually occurs. Logging is debounced to prevent flooding.

= Does this send data externally? =

No. All error logs are stored in your WordPress database. No data is sent to external servers.

= Will this catch all errors? =

It catches JavaScript errors and unhandled promise rejections. It does not catch PHP errors (use Query Monitor for that).

= How many errors are stored? =

The plugin stores the last 100 errors. Older errors are automatically removed.

= Can I use this on production sites? =

Yes! It's designed for production use. Error logging only triggers when actual errors occur and has minimal performance impact.

== Screenshots ==

1. Main dashboard showing error statistics and suspected plugins
2. Error log table with filtering options
3. Stack trace modal for detailed debugging

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic JavaScript error logging
* Smart plugin detection
* Admin dashboard with statistics
* Stack trace viewer
* Bulk and individual log deletion

== Upgrade Notice ==

= 1.0.0 =
Initial release
