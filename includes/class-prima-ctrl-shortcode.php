<?php
/**
 * Shortcode Handler for Front-End RFID Management.
 * Renders the pending sync table with search, cache-busting, and AJAX updates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prima_Ctrl_Shortcode {

	public function __construct() {
		add_shortcode( 'prima_pending_sync', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Renders the RFID Management Table via [prima_pending_sync]
	 */
	public function render_shortcode( $atts ) {
		// 1. Force Browser/Server No-Cache for this request
		if ( ! is_admin() ) {
			header( "Cache-Control: no-cache, must-revalidate, max-age=0" );
			header( "Pragma: no-cache" );
			header( "Expires: Wed, 11 Jan 1984 05:00:00 GMT" );
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			return '<p>' . __( 'Gravity Forms is required.', 'prima-ctrl' ) . '</p>';
		}

		$options = get_option( 'prima_ctrl_settings' );
		$form_id = isset( $options['target_form_id'] ) ? intval( $options['target_form_id'] ) : 0;
		
		// 2. Fetch all entries that are "Found" (Matched in Controller) but not yet "Synced"
		$entries = GFAPI::get_entries( $form_id, array(
			'field_filters' => array(
				array( 'key' => 'prima_sync_status', 'value' => 'Found' )
			),
			'page_size' => 500 // Ensures all 300+ residents load
		) );

		ob_start();
		?>
		<div id="prima-shortcode-container" class="prima-ctrl-wrap">
			<style>
				.prima-search-box { width: 100%; max-width: 400px; padding: 12px; margin-bottom: 20px; border: 2px solid #0073aa; border-radius: 5px; font-size: 16px; }
				.prima-ctrl-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-family: sans-serif; font-size: 14px; }
				.prima-ctrl-table th, .prima-ctrl-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
				.prima-ctrl-table th { background: #f8f9fa; position: sticky; top: 0; z-index: 10; }
				.prima-ctrl-table tr:hover { background: #f9f9f9; }
				.fe-rfid-input { width: 100px; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
				.btn-prima-save { background: #6c757d; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-right: 4px; }
				.btn-prima-sync { background: #0073aa; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
				.btn-prima-sync:disabled { background: #ccc; cursor: not-allowed; }
				.prima-status-synced { background: #d4edda !important; color: #155724; opacity: 0.6; transition: 0.5s ease; }
			</style>

			<input type="text" id="prima-search-input" class="prima-search-box" placeholder="Search by name, address, or ID...">

			<table class="prima-ctrl-table" id="prima-mgmt-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Resident', 'prima-ctrl' ); ?></th>
						<th><?php esc_html_e( 'Address', 'prima-ctrl' ); ?></th>
						<th><?php esc_html_e( 'RFID', 'prima-ctrl' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'prima-ctrl' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No records found needing sync.', 'prima-ctrl' ); ?></td></tr>
					<?php else : 
						foreach ( $entries as $entry ) :
							$address = rgar( $entry, $options['address_field_id'] );
							$rfid    = rgar( $entry, $options['rfid_field_id'] );
							?>
							<tr id="row-<?php echo esc_attr( $entry['id'] ); ?>">
								<td><strong><?php echo esc_html( rgar( $entry, '1.3' ) . ' ' . rgar( $entry, '1.6' ) ); ?></strong></td>
								<td><?php echo esc_html( $address ); ?></td>
								<td>
									<input type="text" class="fe-rfid-input" value="<?php echo esc_attr( $rfid ); ?>">
								</td>
								<td>
									<button class="btn-prima-save" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
										<?php esc_html_e( 'Update', 'prima-ctrl' ); ?>
									</button>
									<button class="btn-prima-sync" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
										<?php esc_html_e( 'Sync', 'prima-ctrl' ); ?>
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
			
			// 3. Search Logic
			$("#prima-search-input").on("keyup", function() {
				var value = $(this).val().toLowerCase();
				$("#prima-mgmt-table tbody tr").filter(function() {
					$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
				});
			});

			// 4. Local RFID Update (Saves to Gravity Forms Entry)
			$('.btn-prima-save').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				var rfidValue = btn.closest('tr').find('.fe-rfid-input').val();
				btn.prop('disabled', true).text('...');

				$.post('<?php echo admin_url("admin-ajax.php"); ?>?v=' + Date.now(), {
					action: 'prima_ctrl_update_entry_rfid',
					entry_id: btn.data('id'),
					rfid: rfidValue,
					security: '<?php echo wp_create_nonce("prima_ctrl_rfid_nonce"); ?>'
				}, function(res) {
					btn.prop('disabled', false).text('Saved');
					setTimeout(function(){ btn.text('Update'); }, 2000);
				});
			});

			// 5. Controller Sync Logic (AddOrUpdateUser)
			$('.btn-prima-sync').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				var entryId = btn.data('id');
				var rfidValue = btn.closest('tr').find('.fe-rfid-input').val();

				if(!rfidValue) { alert('Please enter an RFID.'); return; }
				
				btn.prop('disabled', true).text('Syncing...');

				$.post('<?php echo admin_url("admin-ajax.php"); ?>?v=' + Date.now(), {
					action: 'prima_ctrl_sync_single',
					entry_id: entryId,
					rfid: rfidValue,
					security: '<?php echo wp_create_nonce("prima_ctrl_sync_nonce"); ?>'
				}, function(res) {
					if(res.success) {
						btn.text('Synced!');
						btn.closest('tr').addClass('prima-status-synced').fadeOut(800, function() {
							$(this).remove();
							// Check if table is empty to show message
							if($('#prima-mgmt-table tbody tr').length === 0) {
								$('#prima-mgmt-table tbody').append('<tr><td colspan="4">All records synced.</td></tr>');
							}
						});
					} else {
						alert('Sync Error: ' + res.data);
						btn.prop('disabled', false).text('Sync');
					}
				}).fail(function() {
					alert('Connection Error. Refresh and try again.');
					btn.prop('disabled', false).text('Sync');
				});
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}
}