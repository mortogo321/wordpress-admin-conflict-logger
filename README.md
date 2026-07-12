# Admin Conflict Logger

A WordPress plugin that demonstrates hook-based plugin architecture, secure AJAX handling, and lightweight client-side instrumentation — it captures JavaScript errors in wp-admin and on the frontend and identifies which active plugin (or theme) is the likely source.

## What's Inside

- Singleton plugin class wiring into WordPress `init`, `admin_menu`, `admin_enqueue_scripts`, `wp_enqueue_scripts`, and `wp_ajax_*` hooks
- Global `error` / `unhandledrejection` listener (`assets/js/error-logger.js`) with a debounced send queue, in-flight deduplication, and a filter list for non-actionable noise (e.g. `ResizeObserver loop`, `ChunkLoadError`)
- AJAX endpoints for logging, clearing, and deleting entries, each protected with `check_ajax_referer` nonce checks and `current_user_can('manage_options')` capability checks
- Heuristic "suspected plugin" detection that matches the error's source/stack trace against active plugin folder names and the active theme's stylesheet
- Admin dashboard (`includes/admin-page.php`) showing total error count, a per-plugin breakdown, a sortable log table, and a stack-trace modal
- Storage via a single WordPress option (capped at the most recent 100 entries) — no external services, no schema changes

## Tech Stack

- PHP 7.4+ targeting the WordPress Plugin API (WordPress 5.8+)
- Vanilla JavaScript for error capture; jQuery (bundled with WordPress core) for the admin UI
- WordPress Options API for persistence
- No build step or package manager — plain PHP/JS/CSS enqueued via `wp_enqueue_script` / `wp_enqueue_style`

## Quickstart

1. Copy this folder into `wp-content/plugins/admin-conflict-logger` on a local WordPress install
2. Activate **Admin Conflict Logger** from the Plugins screen
3. Open **Conflict Logger** in the wp-admin menu — JavaScript errors are captured automatically from that point on, in wp-admin for all users and on the frontend for logged-in administrators

## Structure

- `admin-conflict-logger.php` — plugin bootstrap: hooks, AJAX handlers, plugin/theme detection logic
- `includes/admin-page.php` — dashboard template
- `assets/js/error-logger.js` — error/rejection listener with debounce and dedup
- `assets/js/admin.js` — dashboard interactions (refresh, clear, delete, stack-trace modal)
- `assets/css/admin.css` — dashboard styling

## License

GPL-2.0-or-later
