<?php
/**
 * Gravity Forms Handler for Prima Controller XML Integration.
 *
 * Intercepts form submissions and batch sync requests to perform lookups 
 * in the controller and map resident metadata for future RFID syncing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prima_Ctrl_GF_Handler {

	/**
	 * Constructor: Hooks into the Gravity Forms submission process.
	 */
	public function __construct() {
		add_action( 'gform_after_submission', array( $this, 'handle_resident_registration' ), 10, 2 );
	}

	/**
	 * Processes a resident entry to find a matching record in the Controller.
	 *
	 * @param array $entry The Gravity Forms entry object.
	 * @param array $form  The Gravity Forms form object.
	 */
	public function handle_resident_registration( $entry, $form ) {
		$options = get_option( 'prima_ctrl_settings' );
		
		// 1. Ensure this is the correct target form
		$target_form_id = isset( $options['target_form_id'] ) ? $options['target_form_id'] : '';
		if ( (string) $entry['form_id'] !== (string) $target_form_id ) {
			return;
		}

		// 2. Extract the address from the form entry
		$address_field_id = isset( $options['address_field_id'] ) ? $options['address_field_id'] : '';
		$address_value    = rgar( $entry, $address_field_id );

		if ( empty( $address_value ) ) {
			Prima_Ctrl_Logger::log( 'Sync Skip: Address value is empty for Entry ID ' . $entry['id'] );
			return;
		}

		// 3. Query the Controller API
		$api      = new Prima_Ctrl_API();
		$response = $api->lookup_user_by_address( $address_value );

		if ( is_wp_error( $response ) ) {
			Prima_Ctrl_Logger::log( 'Lookup Error for Entry ' . $entry['id'] . ': ' . $response->get_error_message() );
			gform_update_meta( $entry['id'], 'prima_sync_status', 'Error' );
			return;
		}

		/**
		 * Technical Requirement: The ReadUsers request with Range="All-preview" 
		 * returns user data within the <data> block[cite: 572, 611].
		 * We must capture UsrName and UsrLastName to use as identification 
		 * keys for the AddOrUpdateUser request.
		 */
		if ( isset( $response->response->data->user ) ) {
			$user_xml = $response->response->data->user;
			
			// Map XML attributes to Gravity Forms Entry Meta [cite: 78, 271]
			$usr_id    = (string) $user_xml['UsrID'];
			$first_name = (string) $user_xml['UsrName'];
			$last_name  = (string) $user_xml['UsrLastName'];
			$existing_cards = (string) $user_xml['UsrCards'];

			gform_update_meta( $entry['id'], 'prima_usr_id', $usr_id );
			gform_update_meta( $entry['id'], 'prima_ctrl_first_name', $first_name );
			gform_update_meta( $entry['id'], 'prima_ctrl_last_name', $last_name );
			gform_update_meta( $entry['id'], 'prima_existing_cards', $existing_cards );
			gform_update_meta( $entry['id'], 'prima_sync_status', 'Found' );
			
			Prima_Ctrl_Logger::log( 
				sprintf( 'Resident Found: Address %s mapped to UsrID %s (%s %s)', 
					$address_value, 
					$usr_id, 
					$first_name, 
					$last_name 
				) 
			);
		} else {
			// No user found with the provided address
			gform_update_meta( $entry['id'], 'prima_sync_status', 'Not Found' );
			Prima_Ctrl_Logger::log( "Resident Not Found: Address {$address_value} does not exist in controller." );
		}
	}
}