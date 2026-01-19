<?php
/**
 * Plugin Name: Prima Access Control Controller
 * Description: Real-time XML Integration between Gravity Forms and Prima Access Controllers.
 * Version: 1.2.0
 * Author: Philip L.
 * Text Domain: prima-ctrl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define Plugin Constants
 */
define( 'PRIMA_CTRL_VERSION', '1.2.0' );
define( 'PRIMA_CTRL_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRIMA_CTRL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Initialization Class
 */
class Prima_Controller_Base {

	public function __construct() {
		$this->includes();
		$this->init_components();
		
		// Ensure log directory exists on activation/init
		add_action( 'init', array( $this, 'ensure_log_directory' ) );
	}

	/**
	 * Load all required class files.
	 */
	private function includes() {
		// Core Utilities
		require_once PRIMA_CTRL_PATH . 'admin/class-prima-ctrl-logger.php';
		
		// API & Business Logic
		require_once PRIMA_CTRL_PATH . 'includes/class-prima-ctrl-api.php';
		require_once PRIMA_CTRL_PATH . 'includes/class-prima-ctrl-gf-handler.php';
		
		// Admin UI
		require_once PRIMA_CTRL_PATH . 'admin/class-prima-ctrl-admin.php';
		
		// Front-End UI
		require_once PRIMA_CTRL_PATH . 'includes/class-prima-ctrl-shortcode.php';
	}

	/**
	 * Instantiate classes to trigger hooks and handlers.
	 */
	private function init_components() {
		new Prima_Ctrl_GF_Handler();
		new Prima_Ctrl_Shortcode();

		if ( is_admin() ) {
			new Prima_Ctrl_Admin();
		}
	}

	/**
	 * Verifies that the /logs/ directory exists and contains the activity log file.
	 * This prevents the 'Failed to open stream' error.
	 */
	public function ensure_log_directory() {
		$log_dir = PRIMA_CTRL_PATH . 'logs';
		$log_file = $log_dir . '/prima_activity.log';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		if ( ! file_exists( $log_file ) ) {
			$handle = fopen( $log_file, 'w' );
			if ( $handle ) {
				fwrite( $handle, "[" . date( 'Y-m-d H:i:s' ) . "] Log system initialized." . PHP_EOL );
				fclose( $handle );
			}
		}
	}
}

/**
 * Run the Plugin
 */
new Prima_Controller_Base();