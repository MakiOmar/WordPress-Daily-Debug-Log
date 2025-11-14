# WordPress Daily Debug Log

A Must-Use (MU) WordPress plugin that creates daily error logs with detailed backtraces. This plugin loads before all other plugins to capture all PHP errors, warnings, notices, fatal errors, and exceptions.

## Features

- **Daily Log Files**: Automatically creates separate log files for each day (format: `debug-YYYY-MM-DD.log`)
- **Comprehensive Error Logging**: Captures all PHP error types:
  - Errors (E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, etc.)
  - Warnings (E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, etc.)
  - Notices (E_NOTICE, E_USER_NOTICE, etc.)
  - Deprecated functions (E_DEPRECATED, E_USER_DEPRECATED)
  - Fatal errors
  - Uncaught exceptions
- **Detailed Backtraces**: Includes full stack traces for all errors to help identify the source
- **English Error Messages**: Forces all error messages to be logged in English, regardless of WordPress language settings
- **Early Loading**: As a Must-Use plugin, it loads before all other plugins to ensure no errors are missed
- **Works with WP_DEBUG off**: Logs errors even when `WP_DEBUG` is disabled
- **Clean Error Format**: Error types are displayed on separate lines for easy scanning

## Installation

### Manual Installation

1. Download or clone this repository
2. Copy the `anony-wp-daily-debug-log.php` file to your WordPress `wp-content/mu-plugins/` directory
3. If the `mu-plugins` directory doesn't exist, create it
4. The plugin will be automatically loaded by WordPress

**Important**: This plugin must be placed in `wp-content/mu-plugins/` directory (not in the regular `plugins` directory) for it to work as a Must-Use plugin.

### Using Git

```bash
cd wp-content/mu-plugins/
git clone https://github.com/MakiOmar/WordPress-Daily-Debug-Log.git
```

## Configuration

### WordPress Debug Constants

Add the following constants to your `wp-config.php` file for optimal error logging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', false);
```

**Explanation:**
- `WP_DEBUG`: Enables debug mode in WordPress
- `WP_DEBUG_DISPLAY`: Disables displaying errors on the frontend (errors won't be shown to visitors)
- `WP_DEBUG_LOG`: Disables WordPress default error logging to `wp-content/debug.log` (this plugin creates its own daily logs)

**Note**: The plugin will still log errors even if `WP_DEBUG` is set to `false`, but it's recommended to enable it for comprehensive error capture.

### Log File Location

Log files are automatically created in:
```
wp-content/wp-logs/debug-YYYY-MM-DD.log
```

Example:
```
wp-content/wp-logs/debug-2025-11-14.log
```

The `wp-logs` directory is automatically created if it doesn't exist.

## Usage

Once installed, the plugin automatically starts logging all errors. Simply check the log files in `wp-content/wp-logs/` to review errors.

### Log File Format

Each error entry includes:
- Timestamp
- Error type (on separate line)
- Error message with file and line number
- Full backtrace showing the call stack

Example log entry:
```
[2025-11-14 12:49:10] E_USER_NOTICE
Please check the prettyPhoto code. This notice was sent because of the handler.
in /path/to/file.php:123
-- Backtrace --
#0 /path/to/file.php:123 -> SomeClass::someMethod()
#1 /path/to/another-file.php:456 -> someFunction()
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Write permissions to `wp-content/` directory

## How It Works

1. **Error Handler**: Registers a custom error handler that captures all PHP errors
2. **Shutdown Handler**: Catches fatal errors that occur during script execution
3. **Exception Handler**: Logs all uncaught exceptions
4. **Locale Switching**: Temporarily switches to English locale when logging errors to ensure consistent language
5. **Daily Log Rotation**: Creates a new log file each day automatically

## Compatibility

- Works with `WP_DEBUG` enabled or disabled
- Compatible with `WP_DEBUG_LOG` (won't conflict)
- Works regardless of WordPress language settings
- Compatible with all WordPress themes and plugins

## Author

**Mohammad Omar**

- GitHub: [@MakiOmar](https://github.com/MakiOmar)
- Repository: [WordPress-Daily-Debug-Log](https://github.com/MakiOmar/WordPress-Daily-Debug-Log)

## License

This plugin is released under the GPL v2 or later license.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Changelog

### Version 1.0
- Initial release
- Daily log file creation
- Comprehensive error logging with backtraces
- English locale enforcement
- Support for all PHP error types
- Fatal error and exception handling

## Support

If you encounter any issues or have questions, please open an issue on the [GitHub repository](https://github.com/MakiOmar/WordPress-Daily-Debug-Log/issues).

## Notes

- This plugin is designed for development and debugging purposes
- Log files can grow large over time - consider setting up log rotation or periodic cleanup
- The plugin uses WordPress Coding Standards (WPCS) for code quality
- All error messages are forced to English for easier debugging and consistency

