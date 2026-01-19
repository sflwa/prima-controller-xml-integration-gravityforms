<?php
/**
 * Admin Management Class for Prima Controller.
 * Handles Settings, Connection Testing, Batch Lookup, and RFID Syncing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prima_Ctrl_Admin {

	/**
	 * Constructor: Initialize hooks and AJAX routes.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// AJAX Handlers for Admin-only tasks
		add_action( 'wp_ajax_prima_ctrl_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_prima_ctrl_batch_sync', array( $this, 'handle_batch_sync' ) );
		
		// AJAX Handlers for BOTH Admin and Front-End (Shortcode)
		add_action( 'wp_ajax_prima_ctrl_update_entry_rfid', array( $this, 'handle_update_entry_rfid' ) );
		add_action( 'wp_ajax_nopriv_prima_ctrl_update_entry_rfid', array( $this, 'handle_update_entry_rfid' ) );
		
		add_action( 'wp_ajax_prima_ctrl_sync_single', array( $this, 'handle_single_sync' ) );
		add_action( 'wp_ajax_nopriv_prima_ctrl_sync_single', array( $this, 'handle_single_sync' ) );
	}

	/**
	 * Create the WP Admin Menu structure.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Prima Controller', 'prima-ctrl' ),
			__( 'Prima Controller', 'prima-ctrl' ),
			'manage_options',
			'prima-ctrl',
			array( $this, 'render_pending_page' ),
			'dashicons-shield',
			25
		);

		add_submenu_page(
			'prima-ctrl',
			__( 'Settings', 'prima-ctrl' ),
			__( 'Settings', 'prima-ctrl' ),
			'manage_options',
			'prima-ctrl-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'prima-ctrl',
			__( 'Logs', 'prima-ctrl' ),
			__( 'Logs', 'prima-ctrl' ),
			'manage_options',
			'prima-ctrl-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Register Settings and Fields.
	 */
	public function register_settings() {
		register_setting( 'prima_ctrl_settings_group', 'prima_ctrl_settings' );

		add_settings_section(
			'prima_ctrl_api_section',
			__( 'API & Integration Configuration', 'prima-ctrl' ),
			null,
			'prima-ctrl-settings'
		);

		$fields = array(
			'controller_url'   => __( 'Controller Domain/IP', 'prima-ctrl' ),
			'admin_user'       => __( 'Admin Username', 'prima-ctrl' ),
			'admin_pass'       => __( 'Admin Password', 'prima-ctrl' ),
			'target_form_id'   => __( 'Target Gravity Form', 'prima-ctrl' ),
			'address_field_id' => __( 'GF Address Field ID', 'prima-ctrl' ),
			'rfid_field_id'    => __( 'GF RFID Field ID', 'prima-ctrl' ),
			'log_mode'         => __( 'Logging Level', 'prima-ctrl' ),
		);

		foreach ( $fields as $id => $title ) {
			$callback = 'render_text_field';
			if ( 'target_form_id' === $id ) $callback = 'render_form_select';
			if ( 'log_mode' === $id ) $callback = 'render_select_field';

			add_settings_field(
				$id,
				$title,
				array( $this, $callback ),
				'prima-ctrl-settings',
				'prima_ctrl_api_section',
				array( 'label_for' => $id )
			);
		}
	}

	/**
	 * Page 1: Main Admin UI for Pending Syncs.
	 */
	public function render_pending_page() {
		if ( ! class_exists( 'GFAPI' ) ) {
			echo '<div class="notice notice-error"><p>Gravity Forms is not active.</p></div>';
			return;
		}

		$options = get_option( 'prima_ctrl_settings' );
		$form_id = isset( $options['target_form_id'] ) ? intval( $options['target_form_id'] ) : 0;

		$entries = GFAPI::get_entries( $form_id, array(
			'field_filters' => array(
				array( 'key' => 'prima_sync_status', 'value' => 'Found' )
			)
		) );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pending Prima RFID Sync', 'prima-ctrl' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Resident', 'prima-ctrl' ); ?></th>
						<th><?php esc_html_e( 'Address', 'prima-ctrl' ); ?></th>
						<th><?php esc_html_e( 'Current RFIDs', 'prima-ctrl' ); ?></th>
						<th><?php esc_html_e( 'New RFID Assignment', 'prima-ctrl' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'prima-ctrl' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No records found needing sync.', 'prima-ctrl' ); ?></td></tr>
					<?php else : 
						foreach ( $entries as $entry ) :
							$address = rgar( $entry, $options['address_field_id'] );
							$cards   = gform_get_meta( $entry['id'], 'prima_existing_cards' );
							$new_rfid = rgar( $entry, $options['rfid_field_id'] );
							?>
							<tr id="entry-<?php echo esc_attr( $entry['id'] ); ?>">
								<td><?php echo esc_html( rgar( $entry, '1.3' ) . ' ' . rgar( $entry, '1.6' ) ); ?></td>
								<td><?php echo esc_html( $address ); ?></td>
								<td><code><?php echo esc_html( $cards ?: 'None' ); ?></code></td>
								<td>
									<input type="text" class="rfid-input" value="<?php echo esc_attr( $new_rfid ); ?>">
								</td>
								<td>
									<button class="button action-save-rfid" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
										<?php esc_html_e( 'Update Entry', 'prima-ctrl' ); ?>
									</button>
									<button class="button button-primary action-sync-controller" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
										<?php esc_html_e( 'Sync to Controller', 'prima-ctrl' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; 
					endif; ?>
				</tbody>
			</table>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('.action-save-rfid').on('click', function() {
				var btn = $(this);
				var rfid = btn.closest('tr').find('.rfid-input').val();
				btn.prop('disabled', true).text('...');
				$.post(ajaxurl, {
					action: 'prima_ctrl_update_entry_rfid',
					entry_id: btn.data('id'),
					rfid: rfid,
					security: '<?php echo wp_create_nonce("prima_ctrl_rfid_nonce"); ?>'
				}, function(res) { 
					btn.prop('disabled', false).text('Updated!');
					setTimeout(function(){ btn.text('Update Entry'); }, 2000);
				});
			});

			$('.action-sync-controller').on('click', function() {
				var btn = $(this);
				var entryId = btn.data('id');
				var rfid = btn.closest('tr').find('.rfid-input').val();
				if(!rfid) { alert('RFID field is empty.'); return; }
				
				btn.prop('disabled', true).text('Syncing...');
				$.post(ajaxurl, {
					action: 'prima_ctrl_sync_single',
					entry_id: entryId,
					rfid: rfid,
					security: '<?php echo wp_create_nonce("prima_ctrl_sync_nonce"); ?>'
				}, function(res) {
					if(res.success) { btn.closest('tr').fadeOut(); } 
					else { alert(res.data); btn.prop('disabled', false).text('Sync to Controller'); }
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Page 2: Settings & Batch Utilities.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Prima Controller Settings', 'prima-ctrl' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'prima_ctrl_settings_group' ); do_settings_sections( 'prima-ctrl-settings' ); submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Connectivity Test', 'prima-ctrl' ); ?></h2>
			<button id="prima-test-conn" class="button button-secondary"><?php esc_html_e( 'Test API Connection', 'prima-ctrl' ); ?></button>
			<span id="test-res" style="margin-left:10px; font-weight:bold;"></span>

			<hr>
			<h2><?php esc_html_e( 'Initial Setup Sync', 'prima-ctrl' ); ?></h2>
			<p><?php esc_html_e( 'Maps existing records and retrieves official Controller names via address lookup.', 'prima-ctrl' ); ?></p>
			<button id="prima-batch-sync-btn" class="button button-primary"><?php esc_html_e( 'Start Initial Lookup Sync', 'prima-ctrl' ); ?></button>
			<div id="batch-sync-progress" style="margin-top:15px; display:none;">
				<div style="width:300px; background:#ddd; border:1px solid #ccc;"><div id="sync-bar" style="width:0%; background:#0073aa; height:20px;"></div></div>
				<p id="sync-stats"></p>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#prima-test-conn').on('click', function(e) {
				e.preventDefault();
				var btn = $(this); btn.prop('disabled', true).text('Testing...');
				$.post(ajaxurl, { action: 'prima_ctrl_test_connection', security: '<?php echo wp_create_nonce("prima_ctrl_test_nonce"); ?>' }, function(res) {
					$('#test-res').text(res.data).css('color', res.success ? 'green' : 'red');
					btn.prop('disabled', false).text('Test API Connection');
				});
			});

			$('#prima-batch-sync-btn').on('click', function(e) {
				e.preventDefault(); if(!confirm('Process records for lookup?')) return;
				var btn = $(this); btn.prop('disabled', true); $('#batch-sync-progress').show();
				runBatchSync(0);
			});

			function runBatchSync(offset) {
				$.post(ajaxurl, { action: 'prima_ctrl_batch_sync', offset: offset, security: '<?php echo wp_create_nonce("prima_ctrl_batch_nonce"); ?>' }, function(res) {
					if(res.success) {
						var progress = (res.data.current / res.data.total) * 100;
						$('#sync-bar').css('width', progress + '%');
						$('#sync-stats').text('Processed ' + res.data.current + ' of ' + res.data.total);
						if(res.data.current < res.data.total) { runBatchSync(res.data.current); } 
						else { alert('Sync Complete!'); btn.prop('disabled', false); }
					} else { alert('Batch Sync Error: ' + res.data); }
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Rendering Helpers.
	 */
	public function render_text_field( $args ) {
		$options = get_option( 'prima_ctrl_settings' );
		$id = $args['label_for'];
		$type = ( 'admin_pass' === $id ) ? 'password' : 'text';
		$val = isset( $options[$id] ) ? $options[$id] : '';
		printf( '<input type="%s" name="prima_ctrl_settings[%s]" value="%s" class="regular-text">', esc_attr( $type ), esc_attr( $id ), esc_attr( $val ) );
	}

	public function render_select_field( $args ) {
		$options = get_option( 'prima_ctrl_settings' );
		$id = $args['label_for'];
		$current = isset( $options[$id] ) ? $options[$id] : 'simple';
		?>
		<select name="prima_ctrl_settings[<?php echo esc_attr( $id ); ?>]">
			<option value="disabled" <?php selected( $current, 'disabled' ); ?>><?php esc_html_e( 'Disabled (No Logging)', 'prima-ctrl' ); ?></option>
			<option value="simple" <?php selected( $current, 'simple' ); ?>><?php esc_html_e( 'Simple (Status Only)', 'prima-ctrl' ); ?></option>
			<option value="debug" <?php selected( $current, 'debug' ); ?>><?php esc_html_e( 'Debug (Full XML Logging)', 'prima-ctrl' ); ?></option>
		</select>
		<?php
	}

	public function render_form_select() {
		if ( ! class_exists( 'GFAPI' ) ) return;
		$options = get_option( 'prima_ctrl_settings' );
		$current = isset( $options['target_form_id'] ) ? $options['target_form_id'] : '';
		$forms = GFAPI::get_forms();
		echo '<select name="prima_ctrl_settings[target_form_id]"><option value="">Select a Gravity Form</option>';
		foreach ( $forms as $f ) { echo '<option value="'.$f['id'].'" '.selected($current, $f['id'], false).'>'.$f['title'].'</option>'; }
		echo '</select>';
	}

	/**
	 * AJAX logic.
	 */
	public function handle_test_connection() {
		check_ajax_referer( 'prima_ctrl_test_nonce', 'security' );
		$api = new Prima_Ctrl_API();
		$res = $api->test_login();
		if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
		wp_send_json_success( __( 'Connection Successful!', 'prima-ctrl' ) );
	}

	public function handle_batch_sync() {
		check_ajax_referer( 'prima_ctrl_batch_nonce', 'security' );
		$options = get_option( 'prima_ctrl_settings' );
		$form_id = intval( $options['target_form_id'] );
		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$entries = GFAPI::get_entries( $form_id, array( 'page_size' => 10, 'offset' => $offset ) );
		
		$handler = new Prima_Ctrl_GF_Handler();
		foreach ( $entries as $entry ) { $handler->handle_resident_registration( $entry, null ); }
		wp_send_json_success( array( 'current' => $offset + count( $entries ), 'total' => GFAPI::count_entries( $form_id ) ) );
	}

	public function handle_update_entry_rfid() {
		check_ajax_referer( 'prima_ctrl_rfid_nonce', 'security' );
		$options = get_option( 'prima_ctrl_settings' );
		$entry_id = intval( $_POST['entry_id'] );
		$rfid     = sanitize_text_field( $_POST['rfid'] );

		GFAPI::update_entry_field( $entry_id, $options['rfid_field_id'], $rfid );
		
		wp_send_json_success( __( 'Local entry updated.', 'prima-ctrl' ) );
	}

	public function handle_single_sync() {
		check_ajax_referer( 'prima_ctrl_sync_nonce', 'security' );
		
		$entry_id = intval( $_POST['entry_id'] );
		$rfid     = sanitize_text_field( $_POST['rfid'] );
		
		$first = gform_get_meta( $entry_id, 'prima_ctrl_first_name' );
		$last  = gform_get_meta( $entry_id, 'prima_ctrl_last_name' );

		if ( empty( $first ) || empty( $last ) ) {
			wp_send_json_error( __( 'Missing Name mapping. Re-run Initial Setup Sync.', 'prima-ctrl' ) );
		}

		$api = new Prima_Ctrl_API();
		$res = $api->add_or_update_rfid( $first, $last, $rfid ); 
		
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}

		// Update Sync Status
		gform_update_meta( $entry_id, 'prima_sync_status', 'Synced' );

		// Record in Gravity Forms History/Notes
		GFAPI::add_note( 
			$entry_id, 
			0, 
			'Prima Controller Sync', 
			sprintf( 'RFID %s appended to resident %s %s.', $rfid, $first, $last )
		);

		wp_send_json_success( __( 'Synced Successfully!', 'prima-ctrl' ) );
	}

	/**
	 * Log Viewer.
	 */
	public function render_logs_page() {
		$log_file = PRIMA_CTRL_PATH . 'logs/prima_activity.log';
		$log_content = file_exists( $log_file ) ? file_get_contents( $log_file ) : __( 'No log file found.', 'prima-ctrl' );
		echo '<div class="wrap"><h1>Logs</h1><textarea style="width:100%;height:500px;font-family:monospace;" readonly>'.esc_textarea($log_content).'</textarea></div>';
	}
}