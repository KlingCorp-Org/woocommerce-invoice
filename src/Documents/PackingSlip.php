<?php
/**
 * Packing slip (no prices).
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class PackingSlip extends Document {

	public static function type(): string {
		return 'packing_slip';
	}

	public static function label(): string {
		return __( 'Packing slip', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Packing slips', 'order-documents' );
	}

	public static function icon(): string {
		return 'clipboard';
	}

	public static function default_enabled(): bool {
		return true;
	}

	public static function shows_prices(): bool {
		return false;
	}

	public static function legacy_slug(): ?string {
		return 'packing_slip';
	}

	public function data_rows(): array {
		$rows = parent::data_rows();
		if ( $this->order->get_customer_id() ) {
			$rows['customer_number'] = array( 'label' => __( 'Customer number', 'order-documents' ), 'value' => (string) $this->order->get_customer_id() );
		}
		$invoice = DocumentRegistry::make( 'invoice', $this->order );
		if ( $invoice && $invoice->exists() && $invoice->get_number() ) {
			$rows['invoice_number'] = array( 'label' => __( 'Invoice number', 'order-documents' ), 'value' => $invoice->get_number() );
		}
		return $rows;
	}

	protected static function field_defaults(): array {
		return array(
			'billing_address'  => 'never',
			'shipping_address' => 'always',
			'show_weight'      => 1,
		);
	}

}
