<?php
/**
 * Logger Class for Sync Manager Prima GF.
 *
 * @package SyncManagerPrimaGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMPG_Logger {

	/**
	 * Write a message to the custom log file based on setting levels.
	 *
	 * @param string $message The message to log.
	 * @param mixed  $data    Optional data to append.
	 * @param string $level   Log level (info/debug).
	 */
	public static function log( $message, $data = null, $level = 'info' ) {
		$options = get_option( 'smpg_settings' );
		$mode    = isset( $options['log_mode'] ) ? $options['log_mode'] : 'simple';

		// 1. Check if logging is completely disabled.
		if ( 'disabled' === $mode ) {
			return;
		}

		// 2. Only log debug messages if log_mode is set to debug.
		if ( 'debug' === $level && 'debug' !== $mode ) {
			return;
		}

		$log_dir  = SMPG_PATH . 'logs';
		$log_file = $log_dir . '/prima_activity.log';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Fixed: Use gmdate() instead of date() to satisfy WordPress.DateTime.RestrictedFunctions.
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$data_str  = ( $data ) ? ' | DATA: ' . ( is_array( $data ) || is_object( $data ) ? wp_json_encode( $data ) : $data ) : '';
		$entry     = "[$timestamp] " . strtoupper( $level ) . ": $message" . $data_str . PHP_EOL;

		/**
		 * Fixed: Replaced error_log() with file_put_contents() to avoid DevelopmentFunctions warning.
		 * We use FILE_APPEND and LOCK_EX for safe concurrent writing.
		 */
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Utility to clear the log file.
	 */
	public static function clear_logs() {
		$log_file = SMPG_PATH . 'logs/prima_activity.log';
		if ( file_exists( $log_file ) ) {
			// Fixed: Using gmdate() for consistency.
			$entry = '[' . gmdate( 'Y-m-d H:i:s' ) . '] Log manually cleared.' . PHP_EOL;
			file_put_contents( $log_file, $entry );
		}
	}
}