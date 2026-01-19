<?php
/**
 * Logger Class for Prima Controller.
 * Handles activity logging with respect to the user-defined Logging Level.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prima_Ctrl_Logger {

	/**
	 * Write a message to the custom log file based on setting levels.
	 *
	 * @param string $message The message to log.
	 * @param mixed  $data    Optional data (XML strings, arrays, etc) to append.
	 * @param string $level   The severity level of this specific log entry ('info' or 'debug').
	 */
	public static function log( $message, $data = null, $level = 'info' ) {
		$options = get_option( 'prima_ctrl_settings' );
		$mode    = isset( $options['log_mode'] ) ? $options['log_mode'] : 'simple';

		// 1. Check if logging is completely disabled
		if ( 'disabled' === $mode ) {
			return;
		}

		/**
		 * 2. Log Level Filtering
		 * If the message is 'debug' (like full XML payloads), but the user
		 * only wants 'simple' logging, we skip it.
		 */
		if ( 'debug' === $level && 'debug' !== $mode ) {
			return;
		}

		$log_dir  = PRIMA_CTRL_PATH . 'logs';
		$log_file = $log_dir . '/prima_activity.log';

		// Ensure directory exists
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$timestamp = date( 'Y-m-d H:i:s' );
		
		// Format data for readability
		$data_str = '';
		if ( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				$data_str = ' | DATA: ' . json_encode( $data );
			} else {
				$data_str = ' | DATA: ' . $data;
			}
		}

		$entry = "[$timestamp] " . strtoupper( $level ) . ": $message" . $data_str . PHP_EOL;

		/**
		 * Write to file. 
		 * We use the '3' message type for error_log to append to a specific file.
		 */
		error_log( $entry, 3, $log_file );
	}

	/**
	 * Utility to clear the log file if needed via code or future button.
	 */
	public static function clear_logs() {
		$log_file = PRIMA_CTRL_PATH . 'logs/prima_activity.log';
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, "[" . date( 'Y-m-d H:i:s' ) . "] Log cleared." . PHP_EOL );
		}
	}
}