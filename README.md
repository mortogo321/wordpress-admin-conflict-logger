# Admin Conflict Logger

Automatically logs JavaScript errors with plugin context to help identify conflicts quickly.

![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)

## The Problem

33% of WordPress users say troubleshooting plugin conflicts takes too long. When something breaks, you have to deactivate plugins one-by-one to find the culprit.

## The Solution

Admin Conflict Logger automatically captures JavaScript errors and identifies which plugin is likely causing the issue.

## Features

- **Automatic Error Logging** - No configuration needed, just activate
- **Smart Plugin Detection** - Identifies the likely culprit from error sources
- **Admin & Frontend Monitoring** - Catches errors everywhere
- **Clean Dashboard** - View all errors with filtering and statistics
- **Performance Focused** - Minimal footprint, debounced logging
- **Privacy Friendly** - All data stored locally, no external services

## Screenshots

*Screenshots coming soon*

## Installation

### From GitHub
1. Download the latest release
2. Upload to `/wp-content/plugins/`
3. Activate through the 'Plugins' menu
4. Go to 'Conflict Logger' in the admin menu

### From WordPress.org
Coming soon!

## How It Works

1. Plugin injects a lightweight error logger on all pages
2. When a JavaScript error occurs, it's captured with context
3. The plugin analyzes the error source to identify the suspected plugin
4. Errors are stored locally and displayed in a clean dashboard

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

GPL-2.0-or-later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

## Author

**Mor** - [GitHub](https://github.com/mortogo321) | [WordPress](https://profiles.wordpress.org/mortogo321/)
