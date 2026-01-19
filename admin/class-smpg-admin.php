<?php
/**
 * Admin Management Class for Sync Manager Prima GF.
 *
 * @package SyncManagerPrimaGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMPG_Admin {

	/**
	 * Constructor: Initialize hooks and AJAX routes.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'wp_ajax_smpg_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_smpg_batch_sync', array( $this, 'handle_batch_sync' ) );
		add_action( 'wp_ajax_smpg_update_entry_rfid', array( $this, 'handle_update_entry_rfid' ) );
		add_action( 'wp_ajax_nopriv_smpg_update_entry_rfid', array( $this, 'handle_update_entry_rfid' ) );
		add_action( 'wp_ajax_smpg_sync_single', array( $this, 'handle_single_sync' ) );
		add_action( 'wp_ajax_nopriv_smpg_sync_single', array( $this, 'handle_single_sync' ) );
	}

	/**
	 * Create Admin Menu.
	 */
	public function add_menu() {
		add_menu_page(
			esc_html__( 'Sync Manager', 'sync-manager-prima-gf' ),
			esc_html__( 'Sync Manager', 'sync-manager-prima-gf' ),
			'manage_options',
			'smpg-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-shield',
			25
		);

		add_submenu_page(
			'smpg-dashboard',
			esc_html__( 'Settings', 'sync-manager-prima-gf' ),
			esc_html__( 'Settings', 'sync-manager-prima-gf' ),
			'manage_options',
			'smpg-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'smpg-dashboard',
			esc_html__( 'Activity Logs', 'sync-manager-prima-gf' ),
			esc_html__( 'Activity Logs', 'sync-manager-prima-gf' ),
			'manage_options',
			'smpg-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Register Settings with Sanitization Callback.
	 */
	public function register_settings() {
		// Added sanitization callback to resolve 'SettingSanitization' error.
		register_setting( 
			'smpg_settings_group', 
			'smpg_settings', 
			array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) 
		);

		add_settings_section( 'smpg_api_section', esc_html__( 'API Configuration', 'sync-manager-prima-gf' ), null, 'smpg-settings' );

		$fields = array(
			'controller_url'   => __( 'Controller Domain/IP', 'sync-manager-prima-gf' ),
			'admin_user'       => __( 'Admin Username', 'sync-manager-prima-gf' ),
			'admin_pass'       => __( 'Admin Password', 'sync-manager-prima-gf' ),
			'target_form_id'   => __( 'Target Gravity Form', 'sync-manager-prima-gf' ),
			'address_field_id' => __( 'GF Address Field ID', 'sync-manager-prima-gf' ),
			'rfid_field_id'    => __( 'GF RFID Field ID', 'sync-manager-prima-gf' ),
			'log_mode'         => __( 'Logging Level', 'sync-manager-prima-gf' ),
		);

		foreach ( $fields as $id => $title ) {
			$callback = ( 'target_form_id' === $id ) ? 'render_form_select' : ( ( 'log_mode' === $id ) ? 'render_select_field' : 'render_text_field' );
			add_settings_field( $id, $title, array( $this, $callback ), 'smpg-settings', 'smpg_api_section', array( 'label_for' => $id ) );
		}
	}

	/**
	 * Settings Sanitization Callback.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		if ( isset( $input['controller_url'] ) ) $sanitized['controller_url'] = esc_url_raw( $input['controller_url'] );
		if ( isset( $input['admin_user'] ) ) $sanitized['admin_user'] = sanitize_text_field( $input['admin_user'] );
		if ( isset( $input['admin_pass'] ) ) $sanitized['admin_pass'] = sanitize_text_field( $input['admin_pass'] );
		if ( isset( $input['target_form_id'] ) ) $sanitized['target_form_id'] = absint( $input['target_form_id'] );
		if ( isset( $input['address_field_id'] ) ) $sanitized['address_field_id'] = sanitize_text_field( $input['address_field_id'] );
		if ( isset( $input['rfid_field_id'] ) ) $sanitized['rfid_field_id'] = sanitize_text_field( $input['rfid_field_id'] );
		if ( isset( $input['log_mode'] ) ) $sanitized['log_mode'] = sanitize_text_field( $input['log_mode'] );
		
		return $sanitized;
	}

	/**
	 * Dashboard UI and Scripts.
	 */
	public function render_dashboard_page() {
		if ( ! class_exists( 'GFAPI' ) ) return;
		$options = get_option( 'smpg_settings' );
		$form_id = isset( $options['target_form_id'] ) ? intval( $options['target_form_id'] ) : 0;
		$entries = GFAPI::get_entries( $form_id, array( 'field_filters' => array( array( 'key' => 'prima_sync_status', 'value' => 'Found' ) ) ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pending Prima Controller Sync', 'sync-manager-prima-gf' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Resident', 'sync-manager-prima-gf' ); ?></th>
						<th><?php esc_html_e( 'Address', 'sync-manager-prima-gf' ); ?></th>
						<th><?php esc_html_e( 'Controller RFIDs', 'sync-manager-prima-gf' ); ?></th>
						<th><?php esc_html_e( 'New RFID Assignment', 'sync-manager-prima-gf' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'sync-manager-prima-gf' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No records found needing sync.', 'sync-manager-prima-gf' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<?php 
							$cards = gform_get_meta( $entry['id'], 'prima_existing_cards' ); 
							$addr_id = isset( $options['address_field_id'] ) ? $options['address_field_id'] : '';
							$rfid_id = isset( $options['rfid_field_id'] ) ? $options['rfid_field_id'] : '';
							?>
							<tr>
								<td><strong><?php echo esc_html( rgar( $entry, '1.3' ) . ' ' . rgar( $entry, '1.6' ) ); ?></strong></td>
								<td><?php echo esc_html( rgar( $entry, $addr_id ) ); ?></td>
								<td><code><?php echo esc_html( $cards ? $cards : __( 'None', 'sync-manager-prima-gf' ) ); ?></code></td>
								<td><input type="text" class="rfid-input" value="<?php echo esc_attr( rgar( $entry, $rfid_id ) ); ?>"></td>
								<td>
									<button class="button action-save" data-id="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Update', 'sync-manager-prima-gf' ); ?></button>
									<button class="button button-primary action-sync" data-id="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Sync to Prima', 'sync-manager-prima-gf' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.action-save').on('click', function() {
				var btn = $(this);
				$.post(ajaxurl, {
					action: 'smpg_update_entry_rfid',
					entry_id: btn.data('id'),
					rfid: btn.closest('tr').find('.rfid-input').val(),
					security: '<?php echo wp_create_nonce( "smpg_rfid_nonce" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
				}, function() { btn.text('<?php esc_html_e( "Updated", "sync-manager-prima-gf" ); ?>'); });
			});
			$('.action-sync').on('click', function() {
				var btn = $(this);
				btn.prop('disabled', true).text('<?php esc_html_e( "Syncing...", "sync-manager-prima-gf" ); ?>');
				$.post(ajaxurl, {
					action: 'smpg_sync_single',
					entry_id: btn.data('id'),
					rfid: btn.closest('tr').find('.rfid-input').val(),
					security: '<?php echo wp_create_nonce( "smpg_sync_nonce" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
				}, function(res) {
					if(res.success) { btn.closest('tr').fadeOut(); } 
					else { alert(res.data); btn.prop('disabled', false).text('<?php esc_html_e( "Sync to Prima", "sync-manager-prima-gf" ); ?>'); }
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Settings Page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sync Manager Settings', 'sync-manager-prima-gf' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'smpg_settings_group' ); do_settings_sections( 'smpg-settings' ); submit_button(); ?>
			</form>
			<hr>
			<button id="smpg-test-conn" class="button button-secondary"><?php esc_html_e( 'Test Connection', 'sync-manager-prima-gf' ); ?></button>
			<span id="conn-res" style="margin-left:10px;"></span>
		</div>
		<script>
		jQuery('#smpg-test-conn').on('click', function() {
			var btn = jQuery(this); btn.text('<?php esc_html_e( "Testing...", "sync-manager-prima-gf" ); ?>');
			jQuery.post(ajaxurl, { 
				action: 'smpg_test_connection', 
				security: '<?php echo wp_create_nonce( "smpg_test_nonce" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>' 
			}, function(res) {
				jQuery('#conn-res').text(res.data).css('color', res.success ? 'green' : 'red');
				btn.text('<?php esc_html_e( "Test Connection", "sync-manager-prima-gf" ); ?>');
			});
		});
		</script>
		<?php
	}

	/**
	 * Field Renderers.
	 */
	public function render_text_field( $args ) {
		$options = get_option( 'smpg_settings' );
		$id      = $args['label_for'];
		$val     = isset( $options[ $id ] ) ? $options[ $id ] : '';
		$type    = ( 'admin_pass' === $id ) ? 'password' : 'text';
		printf( '<input type="%s" name="smpg_settings[%s]" value="%s" class="regular-text">', esc_attr( $type ), esc_attr( $id ), esc_attr( $val ) );
	}

	public function render_select_field( $args ) {
		$options = get_option( 'smpg_settings' );
		$id      = $args['label_for'];
		$current = isset( $options[ $id ] ) ? $options[ $id ] : 'simple';
		?>
		<select name="smpg_settings[<?php echo esc_attr( $id ); ?>]">
			<option value="disabled" <?php selected( $current, 'disabled' ); ?>><?php esc_html_e( 'Disabled (No Logging)', 'sync-manager-prima-gf' ); ?></option>
			<option value="simple" <?php selected( $current, 'simple' ); ?>><?php esc_html_e( 'Simple (Status Only)', 'sync-manager-prima-gf' ); ?></option>
			<option value="debug" <?php selected( $current, 'debug' ); ?>><?php esc_html_e( 'Debug (Full XML)', 'sync-manager-prima-gf' ); ?></option>
		</select>
		<?php
	}

	public function render_form_select() {
		$options = get_option( 'smpg_settings' );
		$current = isset( $options['target_form_id'] ) ? $options['target_form_id'] : '';
		$forms   = GFAPI::get_forms();
		echo '<select name="smpg_settings[target_form_id]"><option value="">' . esc_html__( 'Select Form', 'sync-manager-prima-gf' ) . '</option>';
		foreach ( $forms as $f ) { 
			echo '<option value="' . esc_attr( $f['id'] ) . '" ' . selected( $current, $f['id'], false ) . '>' . esc_html( $f['title'] ) . '</option>'; 
		}
		echo '</select>';
	}

	/**
	 * AJAX Handlers with Validation, Unslashing, and Sanitization.
	 */
	public function handle_test_connection() {
		check_ajax_referer( 'smpg_test_nonce', 'security' );
		$api = new SMPG_API();
		$res = $api->test_login();
		if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
		wp_send_json_success( esc_html__( 'Connected!', 'sync-manager-prima-gf' ) );
	}

	public function handle_update_entry_rfid() {
		check_ajax_referer( 'smpg_rfid_nonce', 'security' );
		
		// Addresses Warnings: Input Validation, Missing Unslash, and Sanitization.
		if ( ! isset( $_POST['entry_id'] ) || ! isset( $_POST['rfid'] ) ) {
			wp_send_json_error( __( 'Missing parameters.', 'sync-manager-prima-gf' ) );
		}

		$options  = get_option( 'smpg_settings' );
		$rfid_id  = isset( $options['rfid_field_id'] ) ? $options['rfid_field_id'] : '';
		$entry_id = absint( $_POST['entry_id'] );
		$rfid     = sanitize_text_field( wp_unslash( $_POST['rfid'] ) );

		GFAPI::update_entry_field( $entry_id, $rfid_id, $rfid );
		wp_send_json_success();
	}

	public function handle_single_sync() {
		check_ajax_referer( 'smpg_sync_nonce', 'security' );
		
		// Addresses Warnings: Input Validation, Missing Unslash, and Sanitization.
		if ( ! isset( $_POST['entry_id'] ) || ! isset( $_POST['rfid'] ) ) {
			wp_send_json_error( __( 'Missing parameters.', 'sync-manager-prima-gf' ) );
		}

		$entry_id = absint( $_POST['entry_id'] );
		$rfid     = sanitize_text_field( wp_unslash( $_POST['rfid'] ) );
		$first    = gform_get_meta( $entry_id, 'prima_ctrl_first_name' );
		$last     = gform_get_meta( $entry_id, 'prima_ctrl_last_name' );

		$api = new SMPG_API();
		$res = $api->add_or_update_rfid( $first, $last, $rfid );
		if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );

		gform_update_meta( $entry_id, 'prima_sync_status', 'Synced' );
		GFAPI::add_note( $entry_id, 0, 'Prima Sync', sprintf( 'RFID %s synced for %s %s.', $rfid, $first, $last ) );
		wp_send_json_success();
	}

	/**
	 * Log Viewer.
	 */
	public function render_logs_page() {
		$log_file = SMPG_PATH . 'logs/prima_activity.log';
		$content  = file_exists( $log_file ) ? file_get_contents( $log_file ) : __( 'No log found.', 'sync-manager-prima-gf' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Activity Logs', 'sync-manager-prima-gf' ); ?></h1>
			<textarea style="width:100%;height:600px;font-family:monospace;" readonly><?php echo esc_textarea( $content ); ?></textarea>
		</div>
		<?php
	}
}