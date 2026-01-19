<?php
/**
 * Gravity Forms Handler for Sync Manager Prima GF.
 * Manages the logic for resident lookup and metadata mapping during form submission.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMPG_GF_Handler {

	/**
	 * Constructor: Initialize Gravity Forms hooks.
	 */
	public function __construct() {
		$options = get_option( 'smpg_settings' );
		$form_id = isset( $options['target_form_id'] ) ? intval( $options['target_form_id'] ) : 0;

		if ( $form_id > 0 ) {
			// Hook into the specific form submission
			add_action( "gform_after_submission_{$form_id}", array( $this, 'handle_resident_registration' ), 10, 2 );
		}
	}

	/**
	 * Main logic for resident lookup and controller mapping.
	 * * @param array $entry The Gravity Forms entry object.
	 * @param array $form  The Gravity Forms form object.
	 */
	public function handle_resident_registration( $entry, $form ) {
		$options = get_option( 'smpg_settings' );
		$address_field_id = isset( $options['address_field_id'] ) ? $options['address_field_id'] : '';

		if ( empty( $address_field_id ) ) {
			SMPG_Logger::log( "Sync skipped: Address Field ID not configured in settings.", $entry['id'], 'info' );
			return;
		}

		$resident_address = rgar( $entry, $address_field_id );

		if ( empty( $resident_address ) ) {
			SMPG_Logger::log( "Sync skipped: Entry address is empty.", $entry['id'], 'info' );
			return;
		}

		SMPG_Logger::log( "Initiating controller lookup for address: $resident_address" );

		$api = new SMPG_API();
		$result = $api->lookup_user_by_address( $resident_address );

		if ( is_wp_error( $result ) ) {
			gform_update_meta( $entry['id'], 'prima_sync_status', 'API Error' );
			SMPG_Logger::log( "Lookup Error for Entry #{$entry['id']}", $result->get_error_message(), 'info' );
			return;
		}

		/**
		 * Parse the ReadUsers response.
		 * We look for a match in the UsrAddress field.
		 */
		$found = false;
		if ( isset( $result->response->data->Users->User ) ) {
			foreach ( $result->response->data->Users->User as $user ) {
				// Prima returns address as a string; we do a loose case-insensitive match
				if ( strtolower( (string) $user->UsrAddress ) === strtolower( trim( $resident_address ) ) ) {
					
					// Store official Controller names and existing cards in entry meta
					gform_update_meta( $entry['id'], 'prima_ctrl_first_name', (string) $user->UsrName );
					gform_update_meta( $entry['id'], 'prima_ctrl_last_name', (string) $user->UsrLastName );
					gform_update_meta( $entry['id'], 'prima_existing_cards', (string) $user->UsrCards );
					gform_update_meta( $entry['id'], 'prima_sync_status', 'Found' );
					
					SMPG_Logger::log( "Match found in Controller", [
						'entry_id' => $entry['id'],
						'ctrl_name' => (string) $user->UsrName . ' ' . (string) $user->UsrLastName
					]);
					
					$found = true;
					break;
				}
			}
		}

		if ( ! $found ) {
			gform_update_meta( $entry['id'], 'prima_sync_status', 'Not Found' );
			SMPG_Logger::log( "Resident address not found in controller records.", $resident_address, 'info' );
		}
	}
}