<?php
/**
 * Proforma invoice.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class ProformaInvoice extends Document {

	public static function type(): string {
		return 'proforma';
	}

	public static function label(): string {
		return __( 'Proforma invoice', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Proforma invoices', 'order-documents' );
	}

	public static function icon(): string {
		return 'media-default';
	}

	public static function default_numbered(): bool {
		return true;
	}

	public static function legacy_slug(): ?string {
		return 'proforma';
	}

	protected static function field_defaults(): array {
		return array(
			'prefix' => 'PF-',
		);
	}

	protected static function extra_settings_fields(): array {
		return array_merge(
			array(
				array( 'type' => 'heading', 'label' => __( 'Proforma details', 'order-documents' ) ),
				array( 'id' => 'disclaimer', 'type' => 'textarea', 'label' => __( 'Disclaimer', 'order-documents' ), 'default' => __( 'This is a proforma invoice and not a VAT invoice.', 'order-documents' ) ),
				array( 'id' => 'due_days', 'type' => 'number', 'label' => __( 'Due date (days after the document date)', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 365 ),
			),
			Invoice::payment_fields()
		);
	}

	public function get_due_date(): ?\WC_DateTime {
		$days = (int) $this->setting( 'due_days', 0 );
		if ( $days <= 0 ) {
			return null;
		}
		$date = clone ( $this->get_display_date() ?: new \WC_DateTime() );
		$date->modify( '+' . $days . ' days' );
		return $date;
	}

	public function get_kid(): string {
		return Kid::generate( $this, (string) $this->setting( 'kid', 'none' ), (int) $this->setting( 'kid_length', 0 ) );
	}

	public function data_rows(): array {
		$rows = parent::data_rows();
		$due  = $this->get_due_date();
		if ( $due ) {
			$rows['due_date'] = array( 'label' => __( 'Due date', 'order-documents' ), 'value' => odocs_format_date( $due ) );
		}
		$kid = $this->get_kid();
		if ( '' !== $kid ) {
			$rows['kid'] = array( 'label' => __( 'KID', 'order-documents' ), 'value' => $kid );
		}
		return $rows;
	}
}
