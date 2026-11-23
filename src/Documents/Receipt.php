<?php
/**
 * Receipt for paid orders.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class Receipt extends Document {

	public static function type(): string {
		return 'receipt';
	}

	public static function label(): string {
		return __( 'Receipt', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Receipts', 'order-documents' );
	}

	public static function icon(): string {
		return 'yes-alt';
	}

	public static function default_numbered(): bool {
		return true;
	}

	public static function legacy_slug(): ?string {
		return 'receipt';
	}

	protected static function field_defaults(): array {
		return array(
			'prices' => 'incl',
		);
	}

	protected static function extra_settings_fields(): array {
		return array(
			array( 'type' => 'heading', 'label' => __( 'Receipt details', 'order-documents' ) ),
			array( 'id' => 'reuse_invoice_number', 'type' => 'checkbox', 'label' => __( 'Use the invoice number', 'order-documents' ), 'description' => __( 'The receipt shows the invoice number instead of its own (the invoice is created if needed).', 'order-documents' ), 'default' => 0 ),
			array( 'id' => 'show_paid_stamp', 'type' => 'checkbox', 'label' => __( 'Show a "paid" stamp', 'order-documents' ), 'default' => 1 ),
			array( 'id' => 'paid_stamp_text', 'type' => 'text', 'label' => __( 'Stamp text', 'order-documents' ), 'default' => __( 'PAID', 'order-documents' ) ),
			array( 'id' => 'show_transaction_id', 'type' => 'checkbox', 'label' => __( 'Show the transaction ID', 'order-documents' ), 'default' => 1 ),
		);
	}

	protected function type_allows( ?string &$reason = null ): bool {
		if ( ! $this->order->is_paid() ) {
			$reason = __( 'Receipts are only available for paid orders.', 'order-documents' );
			return false;
		}
		return true;
	}

	protected function assign_number( int $timestamp ): void {
		if ( $this->setting( 'reuse_invoice_number' ) ) {
			$invoice = DocumentRegistry::make( 'invoice', $this->order );
			if ( $invoice && $invoice->ensure( 'receipt' ) && $invoice->get_plain_number() ) {
				$this->data['number']           = $invoice->get_plain_number();
				$this->data['formatted_number'] = $invoice->get_number();
				return;
			}
		}
		parent::assign_number( $timestamp );
	}

	public function data_rows(): array {
		$rows = parent::data_rows();
		if ( $this->order->get_date_paid() ) {
			$rows['paid_date'] = array( 'label' => __( 'Paid', 'order-documents' ), 'value' => odocs_format_date( $this->order->get_date_paid() ) );
		}
		if ( $this->setting( 'show_transaction_id', 1 ) && $this->order->get_transaction_id() ) {
			$rows['transaction_id'] = array( 'label' => __( 'Transaction ID', 'order-documents' ), 'value' => $this->order->get_transaction_id() );
		}
		return $rows;
	}
}
