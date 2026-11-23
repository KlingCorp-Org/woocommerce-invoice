<?php
/**
 * Credit note, one per WooCommerce refund.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class CreditNote extends Document {

	public static function type(): string {
		return 'credit_note';
	}

	public static function label(): string {
		return __( 'Credit note', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Credit notes', 'order-documents' );
	}

	public static function icon(): string {
		return 'undo';
	}

	public static function numbering_forced(): bool {
		return true;
	}

	public static function default_enabled(): bool {
		return true;
	}

	public static function default_archive(): bool {
		return true;
	}

	public static function is_per_refund(): bool {
		return true;
	}

	public static function legacy_slug(): ?string {
		return 'credit_note';
	}

	protected static function sequence_options(): array {
		return array(
			'own'     => __( 'Own sequence', 'order-documents' ),
			'invoice' => __( 'Shared with invoices', 'order-documents' ),
		);
	}

	protected static function field_defaults(): array {
		return array(
			'attach_to_emails' => array( 'customer_refunded_order' ),
			'my_account'       => 'created',
		);
	}

	protected static function extra_settings_fields(): array {
		return array(
			array( 'type' => 'heading', 'label' => __( 'Credit note details', 'order-documents' ) ),
			array( 'id' => 'require_invoice', 'type' => 'checkbox', 'label' => __( 'Only for orders with an invoice', 'order-documents' ), 'default' => 1 ),
			array( 'id' => 'negative_amounts', 'type' => 'checkbox', 'label' => __( 'Show amounts as negative numbers', 'order-documents' ), 'default' => 1 ),
			array( 'id' => 'show_reason', 'type' => 'checkbox', 'label' => __( 'Show the refund reason', 'order-documents' ), 'default' => 1 ),
		);
	}

	public function get_invoice(): ?Invoice {
		$invoice = DocumentRegistry::make( 'invoice', $this->order );
		return $invoice instanceof Invoice ? $invoice : null;
	}

	protected function type_allows( ?string &$reason = null ): bool {
		if ( ! $this->refund ) {
			$reason = __( 'A credit note belongs to a refund. Refund the order first.', 'order-documents' );
			return false;
		}
		if ( $this->setting( 'require_invoice', 1 ) ) {
			$invoice = $this->get_invoice();
			if ( ! $invoice || ! $invoice->exists() ) {
				$reason = __( 'Create the invoice before the credit note.', 'order-documents' );
				return false;
			}
		}
		return true;
	}

	public function get_key(): string {
		return 'credit_note-' . ( $this->refund ? $this->refund->get_id() : $this->order->get_id() );
	}

	public function data_rows(): array {
		$rows    = parent::data_rows();
		$invoice = $this->get_invoice();
		if ( $invoice && $invoice->exists() ) {
			$rows['invoice_number'] = array( 'label' => __( 'Original invoice', 'order-documents' ), 'value' => $invoice->get_number() );
			$rows['invoice_date']   = array( 'label' => __( 'Invoice date', 'order-documents' ), 'value' => odocs_format_date( $invoice->get_display_date() ) );
		}
		if ( $this->refund && $this->setting( 'show_reason', 1 ) && $this->refund->get_reason() ) {
			$rows['reason'] = array( 'label' => __( 'Reason', 'order-documents' ), 'value' => $this->refund->get_reason() );
		}
		return $rows;
	}
}
