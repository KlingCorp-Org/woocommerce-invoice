<?php
/**
 * Tools → import from WooCommerce PDF Invoices & Packing Slips.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Admin;

use OrderDocs\Migration\WpoImporter;

defined( 'ABSPATH' ) || exit;

class ImportPage {

	public static function register_handlers(): void {
		add_action( 'admin_post_odocs_wpo_import', array( __CLASS__, 'handle' ) );
	}

	public function render(): void {
		echo '<h2>' . esc_html__( 'Import from WooCommerce PDF Invoices & Packing Slips', 'order-documents' ) . '</h2>';
		if ( ! WpoImporter::has_wpo_data() ) {
			echo '<p class="description">' . esc_html__( 'No data from that plugin was found on this site.', 'order-documents' ) . '</p>';
			return;
		}
		$state = WpoImporter::state();
		$scan  = WpoImporter::scan();
		$map   = WpoImporter::settings_map();
		$url   = admin_url( 'admin-post.php' );

		echo '<p>' . esc_html__( 'Orders that already have an invoice or other document from that plugin keep their number and date, even before importing anything. The steps below take over the settings, continue the number sequences, and copy the document data permanently. Nothing in the old plugin is changed, so both can stay active while you switch.', 'order-documents' ) . '</p>';

		// Step 1: numbers.
		echo '<h3>1. ' . esc_html__( 'Continue the number sequences', 'order-documents' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( $url ) . '">';
		wp_nonce_field( 'odocs_wpo_import' );
		echo '<input type="hidden" name="action" value="odocs_wpo_import"><input type="hidden" name="step" value="sequences">';
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr>';
		echo '<th>' . esc_html__( 'Document', 'order-documents' ) . '</th><th>' . esc_html__( 'Documents found', 'order-documents' ) . '</th><th>' . esc_html__( 'Highest number', 'order-documents' ) . '</th><th>' . esc_html__( 'Next number in old plugin', 'order-documents' ) . '</th><th>' . esc_html__( 'Next number here', 'order-documents' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $scan as $type => $row ) {
			if ( ! $row['count'] && ! $row['wpo_next'] ) {
				continue;
			}
			echo '<tr><td>' . esc_html( $row['label'] ) . ( $row['yearly'] ? ' <span class="odocs-muted">(' . esc_html__( 'yearly reset, this year only', 'order-documents' ) . ')</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) $row['count'] ) . '</td><td>' . esc_html( $row['highest'] ? (string) $row['highest'] : '–' ) . '</td><td>' . esc_html( $row['wpo_next'] ? (string) $row['wpo_next'] : '–' ) . '</td><td>';
			if ( $row['has_number'] ) {
				printf( '<input type="number" min="1" class="small-text" name="next[%s]" value="%s"> <span class="odocs-muted">%s %s</span>', esc_attr( $type ), esc_attr( (string) $row['proposed'] ), esc_html__( 'now:', 'order-documents' ), esc_html( (string) $row['our_next'] ) );
			} else {
				echo '–';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		if ( ! empty( $state['sequences_set'] ) ) {
			/* translators: %s: date */
			echo '<p class="odocs-done">' . esc_html( sprintf( __( 'Number sequences were set on %s.', 'order-documents' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['sequences_set'] ) ) ) . '</p>';
		}
		submit_button( __( 'Set next numbers', 'order-documents' ), 'secondary', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Set these next numbers? New documents will continue from them.', 'order-documents' ) ) . "')" ) );
		echo '</form>';

		// Step 2: settings.
		echo '<h3>2. ' . esc_html__( 'Import settings', 'order-documents' ) . '</h3>';
		if ( ! $map ) {
			echo '<p class="description">' . esc_html__( 'No settings found.', 'order-documents' ) . '</p>';
		} else {
			echo '<form method="post" action="' . esc_url( $url ) . '">';
			wp_nonce_field( 'odocs_wpo_import' );
			echo '<input type="hidden" name="action" value="odocs_wpo_import"><input type="hidden" name="step" value="settings">';
			echo '<table class="widefat striped" style="max-width:900px"><thead><tr><td class="check-column"><input type="checkbox" checked onclick="jQuery(this).closest(\'table\').find(\'tbody input\').prop(\'checked\', this.checked)"></td><th>' . esc_html__( 'Setting', 'order-documents' ) . '</th><th>' . esc_html__( 'Value', 'order-documents' ) . '</th></tr></thead><tbody>';
			foreach ( $map as $entry ) {
				$id = $entry['section'] . '.' . $entry['key'];
				printf(
					'<tr><th class="check-column"><input type="checkbox" name="keys[]" value="%s" checked></th><td>%s</td><td>%s</td></tr>',
					esc_attr( $id ),
					esc_html( $entry['label'] ),
					nl2br( esc_html( wp_trim_words( (string) $entry['display'], 40 ) ) ) // phpcs:ignore
				);
			}
			echo '</tbody></table>';
			if ( ! empty( $state['settings_imported'] ) ) {
				/* translators: %s: date */
				echo '<p class="odocs-done">' . esc_html( sprintf( __( 'Settings were imported on %s.', 'order-documents' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $state['settings_imported'] ) ) ) . '</p>';
			}
			submit_button( __( 'Import selected settings', 'order-documents' ), 'secondary', 'submit', false );
			echo '</form>';
		}

		// Step 3: copy.
		echo '<h3>3. ' . esc_html__( 'Copy document data', 'order-documents' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Copies numbers, dates and notes of all existing documents into this plugin, in the background. Needed before you remove the old plugin\'s data.', 'order-documents' ) . '</p>';
		$status = $state['copy_status'] ?? '';
		if ( 'running' === $status ) {
			/* translators: 1: processed orders, 2: copied documents */
			echo '<p><span class="spinner is-active" style="float:none;margin:0 4px 0 0"></span>' . esc_html( sprintf( __( 'Running: %1$d orders processed, %2$d documents copied. Reload the page to see progress.', 'order-documents' ), (int) ( $state['copy_processed'] ?? 0 ), (int) ( $state['copy_copied'] ?? 0 ) ) ) . '</p>';
		} elseif ( 'done' === $status ) {
			/* translators: 1: processed orders, 2: copied documents */
			echo '<p class="odocs-done">' . esc_html( sprintf( __( 'Finished: %1$d orders processed, %2$d documents copied.', 'order-documents' ), (int) ( $state['copy_processed'] ?? 0 ), (int) ( $state['copy_copied'] ?? 0 ) ) ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( $url ) . '">';
		wp_nonce_field( 'odocs_wpo_import' );
		echo '<input type="hidden" name="action" value="odocs_wpo_import"><input type="hidden" name="step" value="copy">';
		submit_button( 'done' === $status ? __( 'Run again', 'order-documents' ) : __( 'Start copying', 'order-documents' ), 'secondary', 'submit', false, 'running' === $status ? array( 'disabled' => 'disabled' ) : array() );
		echo '</form><hr>';
	}

	public static function handle(): void {
		check_admin_referer( 'odocs_wpo_import' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'order-documents' ) );
		}
		$step = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );
		switch ( $step ) {
			case 'sequences':
				$next = isset( $_POST['next'] ) && is_array( $_POST['next'] ) ? array_map( 'absint', wp_unslash( $_POST['next'] ) ) : array();
				WpoImporter::apply_sequences( $next );
				break;
			case 'settings':
				$keys = isset( $_POST['keys'] ) && is_array( $_POST['keys'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['keys'] ) ) : array();
				if ( $keys ) {
					WpoImporter::import_settings( $keys );
				}
				break;
			case 'copy':
				WpoImporter::start_copy();
				break;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . SettingsPage::SLUG . '&tab=tools&imported=' . $step ) );
		exit;
	}
}
