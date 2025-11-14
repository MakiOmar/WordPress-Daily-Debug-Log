<?php
/**
 * Plugin Name: Daily Error Logger (MU)
 * Description: Creates daily WordPress/PHP error logs with backtrace. Loads before all plugins.
 * Version: 1.0
 * Author: Mohammad Omar
 *
 * @package AnonyWP_Daily_Debug_Log
 */

// For MU plugins, ABSPATH might not be defined yet, so we only check if we're being called directly.
// This allows the plugin to load in MU context before ABSPATH is defined.

/* ============================================================
   CONFIG
   ============================================================ */

// Directory where logs will be stored.
$log_dir = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( dirname( __DIR__ ) ) ) . '/wp-logs/';

// Create directory if missing.
if ( ! file_exists( $log_dir ) ) {
	if ( function_exists( 'wp_mkdir_p' ) ) {
		wp_mkdir_p( $log_dir );
	} else {
		@mkdir( $log_dir, 0755, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

// Ensure directory is writable.
if ( ! is_writable( $log_dir ) ) {
	@chmod( $log_dir, 0755 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

// Today's log file.
$daily_log = $log_dir . 'debug-' . date( 'Y-m-d' ) . '.log';

// Make log file accessible globally for callbacks.
$GLOBALS['daily_log'] = $daily_log;

// Flag to indicate we're in error logging context.
$GLOBALS['mu_logging_error'] = false;

// Force English locale for error logging early in the process.
if ( function_exists( 'add_filter' ) ) {
	add_filter( 'locale', 'mu_force_english_locale_for_errors', 1 );
	add_filter( 'gettext', 'mu_translate_errors_to_english', 999, 3 );
}

/**
 * Force English locale for error contexts.
 *
 * @param string $locale Current locale.
 * @return string Locale to use.
 */
function mu_force_english_locale_for_errors( $locale ) {
	// If we're in error logging context, force English.
	if ( isset( $GLOBALS['mu_logging_error'] ) && $GLOBALS['mu_logging_error'] ) {
		return 'en_US';
	}
	
	// Check if we're in an error context by examining backtrace.
	$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	foreach ( $bt as $frame ) {
		if ( isset( $frame['function'] ) && 
			( 'trigger_error' === $frame['function'] || 
			  'wp_die' === $frame['function'] ||
			  '_doing_it_wrong' === $frame['function'] ||
			  '_deprecated_function' === $frame['function'] ||
			  '_deprecated_argument' === $frame['function'] ||
			  '_deprecated_hook' === $frame['function'] ) ) {
			return 'en_US';
		}
	}
	return $locale;
}

/**
 * Translate error messages back to English when logging.
 *
 * @param string $translated Translated text.
 * @param string $text       Original text.
 * @param string $domain     Text domain.
 * @return string Original English text when in error context.
 */
function mu_translate_errors_to_english( $translated, $text, $domain ) {
	// If we're in error logging context, return original English text.
	if ( isset( $GLOBALS['mu_logging_error'] ) && $GLOBALS['mu_logging_error'] ) {
		return $text;
	}
	return $translated;
}

/**
 * Get English version of error message if available.
 *
 * @param string $translated_msg Translated error message.
 * @return string English error message if available, otherwise original.
 */
function mu_get_english_error_message( $translated_msg ) {
	// If WordPress is loaded, try to find the original English text.
	if ( function_exists( 'load_textdomain' ) && isset( $GLOBALS['l10n'] ) ) {
		// Switch locale temporarily to get English translations.
		$original_locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		
		if ( function_exists( 'switch_to_locale' ) ) {
			switch_to_locale( 'en_US' );
		}
		
		// The translated message might have the original English in WordPress translations.
		// We can't easily reverse it, but we can ensure future errors are in English.
		// For now, return the message as-is but note that locale switching should help.
		
		if ( function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}
	}
	
	// Return original message (it's already translated when it reaches here).
	// The locale switching in the handler should prevent future translations.
	return $translated_msg;
}


/* ============================================================
   LOCALE HANDLING
   ============================================================ */

/**
 * Switch to English locale temporarily for error logging.
 *
 * @return array Original locales before switching.
 */
function mu_switch_to_english_locale() {
	$original_locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
	
	// Store currently loaded text domains to reload later.
	global $l10n, $l10n_unloaded;
	$original_textdomains = array();
	if ( isset( $GLOBALS['l10n'] ) && is_array( $GLOBALS['l10n'] ) ) {
		$original_textdomains = array_keys( $GLOBALS['l10n'] );
	}
	
	// Unload all text domains to force English.
	foreach ( $original_textdomains as $domain ) {
		if ( function_exists( 'unload_textdomain' ) ) {
			unload_textdomain( $domain );
		}
	}
	
	// Switch WordPress locale to English.
	if ( function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( 'en_US' );
	}
	
	// Reload text domains in English.
	foreach ( $original_textdomains as $domain ) {
		if ( function_exists( 'load_textdomain' ) ) {
			load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $domain . '-en_US.mo' );
			if ( ! is_textdomain_loaded( $domain ) ) {
				load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '-en_US.mo' );
			}
			if ( ! is_textdomain_loaded( $domain ) ) {
				load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			}
		}
	}
	
	// Switch PHP locale to English.
	$original_php_locale = setlocale( LC_ALL, 0 );
	@setlocale( LC_ALL, 'en_US', 'en_US.UTF-8', 'English_United States.1252' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	
	return array(
		'wp_locale'       => $original_locale,
		'php_locale'      => $original_php_locale,
		'textdomains'     => $original_textdomains,
	);
}

/**
 * Restore original locale after error logging.
 *
 * @param array $original_locales Original locales before switching.
 */
function mu_restore_locale( $original_locales ) {
	// Restore WordPress locale.
	if ( function_exists( 'restore_previous_locale' ) ) {
		restore_previous_locale();
	} elseif ( isset( $original_locales['wp_locale'] ) && function_exists( 'switch_to_locale' ) ) {
		switch_to_locale( $original_locales['wp_locale'] );
	}
	
	// Restore text domains.
	if ( isset( $original_locales['textdomains'] ) && is_array( $original_locales['textdomains'] ) ) {
		foreach ( $original_locales['textdomains'] as $domain ) {
			if ( function_exists( 'unload_textdomain' ) ) {
				unload_textdomain( $domain );
			}
		}
		// Text domains will be reloaded automatically by WordPress with the restored locale.
	}
	
	// Restore PHP locale.
	if ( isset( $original_locales['php_locale'] ) ) {
		@setlocale( LC_ALL, $original_locales['php_locale'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}


/* ============================================================
   BACKTRACE FORMATTER
   ============================================================ */

/**
 * Convert backtrace array to formatted string.
 *
 * @param array $bt Backtrace array.
 * @return string Formatted backtrace string.
 */
function mu_log_bt_to_string( $bt ) {
	if ( empty( $bt ) || ! is_array( $bt ) ) {
		return '';
	}

	$out = '';
	foreach ( $bt as $i => $t ) {
		$file = isset( $t['file'] ) ? $t['file'] : '[internal]';
		$line = isset( $t['line'] ) ? $t['line'] : '-';
		$func = isset( $t['function'] ) ? $t['function'] : '';
		$class = isset( $t['class'] ) ? $t['class'] : '';
		$type = isset( $t['type'] ) ? $t['type'] : '';

		$out .= "#$i $file:$line -> $class$type$func()\n";
	}
	return $out;
}


/* ============================================================
   LOGGER FUNCTION
   ============================================================ */

/**
 * Log message to daily log file with optional backtrace.
 *
 * @param string $msg        Message to log.
 * @param bool   $include_bt Whether to include backtrace. Default true.
 * @return bool True on success, false on failure.
 */
function mu_daily_logger( $msg, $include_bt = true ) {
	if ( ! isset( $GLOBALS['daily_log'] ) || empty( $GLOBALS['daily_log'] ) ) {
		return false;
	}

	$log_file = $GLOBALS['daily_log'];
	$log      = date( '[Y-m-d H:i:s] ' ) . $msg . "\n";

	if ( $include_bt ) {
		$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		if ( ! empty( $bt ) ) {
			$log .= "-- Backtrace --\n"
				. mu_log_bt_to_string( $bt )
				. "\n";
		}
	}

	// Try error_log first.
	$result = @error_log( $log, 3, $log_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	// Fallback to file_put_contents if error_log fails.
	if ( false === $result ) {
		$result = @file_put_contents( $log_file, $log, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	}

	return false !== $result;
}


/* ============================================================
   ERROR HANDLER (NOTICES, WARNINGS, ERRORS)
   ============================================================ */

// Ensure we capture all error types.
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
	// Force error reporting to E_ALL if WP_DEBUG is not enabled.
	// This allows us to log errors even if WP_DEBUG is false.
	@ini_set( 'error_reporting', E_ALL ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.display_errors_Disallowed
	@error_reporting( E_ALL ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

set_error_handler(
	function ( $errno, $errstr, $errfile, $errline, $errcontext = null ) {
		// Set flag that we're logging an error.
		$GLOBALS['mu_logging_error'] = true;

		// Switch to English locale to ensure error messages are in English.
		$original_locales = mu_switch_to_english_locale();

		// Map error types to readable names.
		$error_types = array(
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_NOTICE            => 'E_NOTICE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_STRICT            => 'E_STRICT',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		);

		$error_type = isset( $error_types[ $errno ] ) ? $error_types[ $errno ] : "UNKNOWN[$errno]";

		// Sanitize file path for logging.
		$errfile = str_replace( '\\', '/', $errfile );

		// Try to get English version of error message if it's translated.
		$english_msg = mu_get_english_error_message( $errstr );

		mu_daily_logger( "$error_type\n$english_msg in $errfile:$errline" );

		// Restore original locale.
		mu_restore_locale( $original_locales );

		// Clear error logging flag.
		$GLOBALS['mu_logging_error'] = false;

		// Return false to allow other error handlers to process the error.
		return false;
	},
	E_ALL
);


/* ============================================================
   SHUTDOWN HANDLER (FATAL ERRORS)
   ============================================================ */

register_shutdown_function(
	function () {
		$error = error_get_last();

		if ( $error && ( $error['type'] & ( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR ) ) ) {
			// Set flag that we're logging an error.
			$GLOBALS['mu_logging_error'] = true;

			// Switch to English locale to ensure error messages are in English.
			$original_locales = mu_switch_to_english_locale();

			// Map error types to readable names.
			$error_types = array(
				E_ERROR             => 'E_ERROR',
				E_PARSE             => 'E_PARSE',
				E_CORE_ERROR        => 'E_CORE_ERROR',
				E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
				E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			);

			$error_type = isset( $error_types[ $error['type'] ] ) ? $error_types[ $error['type'] ] : "FATAL_ERROR[{$error['type']}]";
			$errfile    = str_replace( '\\', '/', $error['file'] );

			// Try to get English version of error message if it's translated.
			$english_msg = mu_get_english_error_message( $error['message'] );

			$msg = "$error_type\n$english_msg in $errfile:{$error['line']}";
			mu_daily_logger( $msg, false );

			// Attempt to show backtrace.
			$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
			if ( ! empty( $bt ) ) {
				$trace_log = "-- Recovered Backtrace --\n"
					. mu_log_bt_to_string( $bt ) . "\n";
				@error_log( $trace_log, 3, $GLOBALS['daily_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				@file_put_contents( $GLOBALS['daily_log'], $trace_log, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			}

			// Restore original locale.
			mu_restore_locale( $original_locales );

			// Clear error logging flag.
			$GLOBALS['mu_logging_error'] = false;
		}
	}
);


/* ============================================================
   EXCEPTION HANDLER
   ============================================================ */

set_exception_handler(
	function ( $ex ) {
		// Set flag that we're logging an error.
		$GLOBALS['mu_logging_error'] = true;

		// Switch to English locale to ensure error messages are in English.
		$original_locales = mu_switch_to_english_locale();

		$errfile = str_replace( '\\', '/', $ex->getFile() );
		
		// Try to get English version of error message if it's translated.
		$english_msg = mu_get_english_error_message( $ex->getMessage() );
		
		$msg = "UNCAUGHT EXCEPTION\n$english_msg in $errfile:{$ex->getLine()}";
		mu_daily_logger( $msg, false );

		$trace = $ex->getTrace();
		if ( ! empty( $trace ) ) {
			$trace_log = "-- Exception Backtrace --\n" .
				mu_log_bt_to_string( $trace ) . "\n";
			@error_log( $trace_log, 3, $GLOBALS['daily_log'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents( $GLOBALS['daily_log'], $trace_log, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		}

		// Restore original locale.
		mu_restore_locale( $original_locales );

		// Clear error logging flag.
		$GLOBALS['mu_logging_error'] = false;

		// Let the default exception handler run.
		restore_exception_handler();
		throw $ex;
	}
);
