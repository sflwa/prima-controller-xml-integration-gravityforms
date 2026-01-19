<?php
/**
 * Plugin Name: Sync Manager for Prima Controller and Gravity Forms
 * Description: Real-time resident synchronization between Gravity Forms and Prima Access Controllers.
 * Version: 1.5.0
 * Author: Philip L.
 * License: GPLv2 or later
 * Text Domain: sync-manager-prima-gf
 * Slug: sync-manager-prima-gf
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define Standardized Constants
 */
define( 'SMPG_VERSION', '1.5.0' );
define( 'SMPG_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMPG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Initialization Class
 */
class Sync_Manager_Prima_GF {

	/**
	 * Constructor: Initialize components.
	 */
	public function __construct() {
		$this->includes();
		$this->init_components();
		
		// Set up the log system on initialization
		add_action( 'init', array( $this, 'ensure_log_system' ) );
	}

	/**
	 * Include refactored class files.
	 */
	private function includes() {
		require_once SMPG_PATH . 'admin/class-smpg-logger.php';
		require_once SMPG_PATH . 'includes/class-smpg-api.php';
		require_once SMPG_PATH . 'includes/class-smpg-gf-handler.php';
		require_once SMPG_PATH . 'admin/class-smpg-admin.php';
		require_once SMPG_PATH . 'includes/class-smpg-shortcode.php';
	}

	/**
	 * Instantiate classes using the new SMPG prefix.
	 */
	private function init_components() {
		new SMPG_GF_Handler();
		new SMPG_Shortcode();

		// Admin only functionality
		if ( is_admin() ) {
			new SMPG_Admin();
		}
	}

	/**
	 * Initialization of the log directory.
	 * Checks and creates the logs folder if missing.
	 */
	public function ensure_log_system() {
		$log_dir = SMPG_PATH . 'logs';
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
	}
}

/**
 * Run the Sync Manager
 */
new Sync_Manager_Prima_GF();