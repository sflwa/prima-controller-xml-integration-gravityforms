<?php
/**
 * Shortcode Handler for Sync Manager Prima GF.
 *
 * @package SyncManagerPrimaGF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMPG_Shortcode {

	/**
	 * Constructor: Initialize the shortcode.
	 */
	public function __construct() {
		add_shortcode( 'prima_pending_sync', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Renders the RFID Management Table via [prima_pending_sync].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		// 1. Force Browser/Server No-Cache for this request.
		if ( ! is_admin() ) {
			header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			return '<p>' . esc_html__( 'Gravity Forms is required.', 'sync-manager-prima-gf' ) . '</p>';
		}

		$options = get_option( 'smpg_settings' );
		$form_id = isset( $options['target_form_id'] ) ? intval( $options['target_form_id'] ) : 0;
		
		// 2. Fetch all entries that are "Found" but not yet "Synced".
		$entries = GFAPI::get_entries( $form_id, array(
			'field_filters' => array(
				array( 'key' => 'prima_sync_status', 'value' => 'Found' ),
			),
			'page_size' => 500,
		) );

		ob_start();
		?>
		<div id="smpg-shortcode-container" class="smpg-wrap">
			<style>
				.smpg-search-box { width: 100%; max-width: 400px; padding: 12px; margin-bottom: 20px; border: 2px solid #0073aa; border-radius: 5px; font-size: 16px; }
				.smpg-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-family: sans-serif; font-size: 14px; }
				.smpg-table th, .smpg-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
				.smpg-table th { background: #f8f9fa; position: sticky; top: 0; z-index: 10; }
				.smpg-table tr:hover { background: #f9f9f9; }
				.smpg-rfid-input { width: 110px; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
				.btn-smpg-update { background: #6c757d; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-right: 4px; }
				.btn-smpg-sync { background: #0073aa; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
				.btn-smpg-sync:disabled { background: #ccc; cursor: not-allowed; }
				.smpg-row-success { background: #d4edda !important; opacity: 0.5; transition: 0.6s ease; }
			</style>

			<input type="text" id="smpg-search" class="smpg-search-box" placeholder="<?php esc_attr_e( 'Search residents or addresses...', 'sync-manager-prima-gf' ); ?>">

			<table class="smpg-table" id="smpg-mgmt-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Resident', 'sync-manager-prima-gf' ); ?></th>
						<th><?php esc_html_e( 'Address', 'sync-manager-prima-gf' ); ?></th>
						<th><?php esc_html_e( 'RFID', 'sync-manager-prima-gf' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'sync-manager-prima-gf' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No pending assignments found.', 'sync-manager-prima-gf' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<?php 
							$addr_id = isset( $options['address_field_id'] ) ? $options['address_field_id'] : '';
							$rfid_id = isset( $options['rfid_field_id'] ) ? $options['rfid_field_id'] : '';
							?>
							<tr id="smpg-row-<?php echo esc_attr( $entry['id'] ); ?>">
								<td><strong><?php echo esc_html( rgar( $entry, '1.3' ) . ' ' . rgar( $entry, '1.6' ) ); ?></strong></td>
								<td><?php echo esc_html( rgar( $entry, $addr_id ) ); ?></td>
								<td>
									<input type="text" class="smpg-rfid-input" value="<?php echo esc_attr( rgar( $entry, $rfid_id ) ); ?>">
								</td>
								<td>
									<button class="btn-smpg-update" data-id="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Update', 'sync-manager-prima-gf' ); ?></button>
									<button class="btn-smpg-sync" data-id="<?php echo esc_attr( $entry['id'] ); ?>"><?php esc_html_e( 'Sync', 'sync-manager-prima-gf' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<script>
		jQuery(document).ready(function($) {
			
			// Search Logic
			$("#smpg-search").on("keyup", function() {
				var value = $(this).val().toLowerCase();
				$("#smpg-mgmt-table tbody tr").filter(function() {
					$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
				});
			});

			// Update Entry Locally
			$('.btn-smpg-update').on('click', function(e) {
				var btn = $(this);
				var rfid = btn.closest('tr').find('.smpg-rfid-input').val();
				btn.prop('disabled', true).text('...');

				$.post('<?php echo admin_url( "admin-ajax.php" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>?v=' + Date.now(), {
					action: 'smpg_update_entry_rfid',
					entry_id: btn.data('id'),
					rfid: rfid,
					security: '<?php echo wp_create_nonce( "smpg_rfid_nonce" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
				}, function() {
					btn.prop('disabled', false).text('<?php esc_html_e( "Saved", "sync-manager-prima-gf" ); ?>');
					setTimeout(function(){ btn.text('<?php esc_html_e( "Update", "sync-manager-prima-gf" ); ?>'); }, 2000);
				});
			});

			// Sync to Prima Controller
			$('.btn-smpg-sync').on('click', function(e) {
				var btn = $(this);
				var entryId = btn.data('id');
				var rfid = btn.closest('tr').find('.smpg-rfid-input').val();

				if(!rfid) { 
					alert('<?php esc_html_e( "Please enter an RFID.", "sync-manager-prima-gf" ); ?>'); 
					return; 
				}
				
				btn.prop('disabled', true).text('<?php esc_html_e( "Syncing...", "sync-manager-prima-gf" ); ?>');

				$.post('<?php echo admin_url( "admin-ajax.php" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>?v=' + Date.now(), {
					action: 'smpg_sync_single',
					entry_id: entryId,
					rfid: rfid,
					security: '<?php echo wp_create_nonce( "smpg_sync_nonce" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'
				}, function(res) {
					if(res.success) {
						btn.text('<?php esc_html_e( "Synced!", "sync-manager-prima-gf" ); ?>');
						btn.closest('tr').addClass('smpg-row-success').fadeOut(800, function() {
							$(this).remove();
							if($('#smpg-mgmt-table tbody tr').length === 0) {
								$('#smpg-mgmt-table tbody').append('<tr><td colspan="4"><?php esc_html_e( "All records synced.", "sync-manager-prima-gf" ); ?></td></tr>');
							}
						});
					} else {
						alert('<?php esc_html_e( "Sync Error: ", "sync-manager-prima-gf" ); ?>' + res.data);
						btn.prop('disabled', false).text('<?php esc_html_e( "Sync", "sync-manager-prima-gf" ); ?>');
					}
				});
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}
}