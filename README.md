# Admin Conflict Logger

[![CI](https://github.com/mortogo321/wordpress-admin-conflict-logger/actions/workflows/ci.yml/badge.svg)](https://github.com/mortogo321/wordpress-admin-conflict-logger/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759B)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)](LICENSE)

A WordPress plugin that demonstrates hook-based plugin architecture, secure AJAX handling, and lightweight client-side instrumentation — it captures JavaScript errors in wp-admin and on the frontend and identifies which active plugin (or theme) is the likely source.

## What's Inside

- Singleton plugin class wiring into WordPress `init`, `admin_menu`, `admin_enqueue_scripts`, `wp_enqueue_scripts`, and `wp_ajax_*` hooks
- Global `error` / `unhandledrejection` listener (`assets/js/error-logger.js`) with a debounced send queue, in-flight deduplication, and a filter list for non-actionable noise (e.g. `ResizeObserver loop`, `ChunkLoadError`)
- AJAX endpoints for logging, clearing, and deleting entries, each protected with `check_ajax_referer` nonce checks and `current_user_can('manage_options')` capability checks; the log endpoint is additionally rate-limited (20 events/min per user) and enforces per-field input caps
- Heuristic "suspected plugin" detection that matches the error's source/stack trace against active plugin folder names and the active theme's stylesheet (pure, unit-tested helper — no WordPress I/O)
- Admin dashboard (`includes/admin-page.php`) showing total error count, a per-plugin breakdown, a sortable log table, and a stack-trace modal, with defensive defaults and bounded stack-trace attributes
- Storage via a single WordPress option (capped at the most recent 100 entries) — no external services, no schema changes; `uninstall.php` removes all data on uninstall

## Requirements

- PHP 8.2+
- WordPress 6.4+ (tested up to 6.9)
- MySQL 8.4 (Docker stacks) / any WP-supported database otherwise

## Quickstart

1. Copy this folder into `wp-content/plugins/admin-conflict-logger` on a local WordPress install
2. Activate **Admin Conflict Logger** from the Plugins screen
3. Open **Conflict Logger** in the wp-admin menu — JavaScript errors are captured automatically from that point on, in wp-admin for all users and on the frontend for logged-in administrators

Or run the prebuilt Docker stack (production-like, plugin pre-installed):

```bash
MYSQL_ROOT_PASSWORD=secret MYSQL_PASSWORD=secret docker compose up --build
```

For live development with the plugin bind-mounted:

```bash
docker compose -f compose.dev.yml up
```

Then open `http://localhost:8000` and complete the WordPress installer.

## Development

```bash
composer install   # PHP dev deps (PHPUnit, PHPCS)
bun install        # JS dev deps (Biome, Vitest)

composer lint      # php -l + PHPCS (WordPress Coding Standards)
composer test      # PHPUnit: 14 tests (detection heuristic, caps, rate limit, capability gate)
bun run lint       # Biome check
bun run test       # Vitest: 9 tests (ignore rules, dedupe, queue cap, payload truncation)
```

CI (`.github/workflows/ci.yml`) runs lint + test + Docker build on every push/PR; Dependabot tracks GitHub Actions, Composer, npm, and Docker weekly.

## Structure

- `admin-conflict-logger.php` — plugin bootstrap: hooks, AJAX handlers, capability + rate-limit gates, plugin/theme detection logic
- `includes/admin-page.php` — dashboard template
- `uninstall.php` — option cleanup on uninstall
- `assets/js/error-logger.js` — error/rejection listener with debounce and dedup
- `assets/js/admin.js` — dashboard interactions (refresh, clear, delete, stack-trace modal)
- `assets/css/admin.css` — dashboard styling
- `tests/` — PHPUnit suite (WP function stubs, no WP install needed); `tests/js/` — Vitest suite

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
