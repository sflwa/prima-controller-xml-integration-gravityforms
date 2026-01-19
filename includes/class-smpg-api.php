<?php
/**
 * API Client for Sync Manager Prima GF.
 * Handles authentication and XML requests to the Prima Controller.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMPG_API {

	private $endpoint;
	private $username;
	private $password;
	private $session_id = null;

	/**
	 * Constructor: Initializes settings and binary path.
	 */
	public function __construct() {
		$options = get_option( 'smpg_settings' );
		$url     = isset( $options['controller_url'] ) ? esc_url_raw( $options['controller_url'] ) : '';
		
		$url = rtrim( $url, '/' );
		if ( strpos( $url, 'sysfcgi.fx' ) === false ) {
			$this->endpoint = $url . '/bin/sysfcgi.fx';
		} else {
			$this->endpoint = $url;
		}
		
		$this->username = isset( $options['admin_user'] ) ? trim( $options['admin_user'] ) : '';
		$this->password = isset( $options['admin_pass'] ) ? trim( $options['admin_pass'] ) : '';
	}

	/**
	 * Connection test logic.
	 */
	public function test_login() {
		return $this->login();
	}

	/**
	 * Obtains a SessionID via LoginUser request.
	 */
	private function login() {
		$xml = '<?xml version="1.0" encoding="UTF-8" ?>
		<requests>
			<request name="LoginUser">
				<param name="UsrName" value="' . esc_attr( $this->username ) . '"/>
				<param name="UsrPassword" value="' . esc_attr( $this->password ) . '"/>
			</request>
		</requests>';

		$response = wp_remote_post( $this->endpoint, array( 
			'body'    => $xml, 
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8' )
		) );

		if ( is_wp_error( $response ) ) return $response;

		$res_body = wp_remote_retrieve_body( $response );
		$xml_data = simplexml_load_string( $res_body );
		
		if ( isset( $xml_data->response ) && '0' === (string) $xml_data->response['status'] ) {
			$this->session_id = (string) $xml_data->response->data->SessionID;
			return true;
		}

		return new WP_Error( 'login_failed', __( 'API Login failed. Check credentials.', 'sync-manager-prima-gf' ) );
	}

	/**
	 * Shared remote request handler using Session-Id headers.
	 */
	private function remote_request( $xml_payload ) {
		if ( empty( $this->endpoint ) ) return new WP_Error( 'missing_config', 'URL not configured.' );

		if ( ! $this->session_id ) {
			$auth = $this->login();
			if ( is_wp_error( $auth ) ) return $auth;
		}

		$headers = array( 
			'Content-Type' => 'text/xml; charset=utf-8',
			'Session-Id'   => $this->session_id
		);

		SMPG_Logger::log( "Sending XML", $xml_payload, 'debug' );

		$args = array(
			'body'    => $xml_payload,
			'headers' => $headers,
			'timeout' => 30,
		);

		$response = wp_remote_post( $this->endpoint, $args );

		if ( is_wp_error( $response ) ) return $response;

		$res_body = wp_remote_retrieve_body( $response );
		SMPG_Logger::log( "Received Response", $res_body, 'debug' );

		return $this->parse_response( $res_body );
	}

	/**
	 * Search for resident by address.
	 */
	public function lookup_user_by_address( $address ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" ?>
		<requests>
			<request name="ReadUsers">
				<param name="Range" value="All-preview"/>
				<param name="Filter" value="' . esc_attr( $address ) . '"/>
				<param name="FilterFields" value="UsrAddress"/>
			</request>
		</requests>';
		return $this->remote_request( $xml );
	}

	/**
	 * Update user record. Uses single KeyColumn to resolve controller "Wrong parameters" errors.
	 */
	public function add_or_update_rfid( $first_name, $last_name, $rfid ) {
		$xml = '<?xml version="1.0" encoding="UTF-8" ?>
		<requests>
			<request name="AddOrUpdateUser">
				<param name="KeyColumns" value="UsrName" />
				<param name="UsrName" value="' . esc_attr( $first_name ) . '" />
				<param name="UsrLastName" value="' . esc_attr( $last_name ) . '" />
				<param name="UsrCards" value="' . esc_attr( $rfid ) . '" />
			</request>
		</requests>';

		return $this->remote_request( $xml );
	}

	/**
	 * Parse status and map error codes.
	 */
	private function parse_response( $response_body ) {
		$xml = simplexml_load_string( $response_body );
		if ( ! $xml ) return new WP_Error( 'xml_parse_error', 'Invalid XML format.' );

		$status = isset( $xml->response['status'] ) ? (int) $xml->response['status'] : 1;
		
		if ( 0 !== $status ) {
			$message = isset( $xml->response['message'] ) ? (string) $xml->response['message'] : 'Error ' . $status;
			
			switch ( $status ) {
				case 5:  $message = __( 'Error 5: Authentication failed.', 'sync-manager-prima-gf' ); break;
				case 8:  $message = __( 'Error 8: Match failed. Check name mapping.', 'sync-manager-prima-gf' ); break;
				case 15: $message = __( 'Error 15: RFID already in use.', 'sync-manager-prima-gf' ); break;
				case 22: $message = __( 'Error 22: XML License required on controller.', 'sync-manager-prima-gf' ); break;
			}
			return new WP_Error( 'api_status_error', $message );
		}
		return $xml;
	}
}