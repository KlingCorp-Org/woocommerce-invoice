<?php
/**
 * Invoice.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class Invoice extends Document {

	public static function type(): string {
		return 'invoice';
	}

	public static function label(): string {
		return __( 'Invoice', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Invoices', 'order-documents' );
	}

	public static function icon(): string {
		return 'media-text';
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

	public static function legacy_slug(): ?string {
		return 'invoice';
	}

	protected static function field_defaults(): array {
		return array(
			'attach_to_emails' => array( 'customer_completed_order', 'customer_invoice' ),
			'my_account'       => 'created',
		);
	}

	protected static function extra_settings_fields(): array {
		return array_merge(
			array(
				array( 'type' => 'heading', 'label' => __( 'Invoice details', 'order-documents' ) ),
				array( 'id' => 'due_days', 'type' => 'number', 'label' => __( 'Due date (days after the invoice date)', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 365, 'description' => __( '0 hides the due date.', 'order-documents' ) ),
				array( 'id' => 'show_delivery_date', 'type' => 'checkbox', 'label' => __( 'Show delivery date', 'order-documents' ), 'description' => __( 'The date the order was completed.', 'order-documents' ), 'default' => 0 ),
				array( 'id' => 'show_customer_number', 'type' => 'checkbox', 'label' => __( 'Show customer number', 'order-documents' ), 'default' => 0 ),
			),
			self::payment_fields()
		);
	}

	/**
	 * Payment information and KID fields, shared with the proforma invoice.
	 */
	public static function payment_fields(): array {
		return array(
			array( 'id' => 'payment_info', 'type' => 'textarea', 'label' => __( 'Payment information', 'order-documents' ), 'default' => '', 'description' => __( 'Shown in the "Payment information" block, e.g. "Pay to account {{bank_account}}, KID {{kid}}, due {{due_date}}".', 'order-documents' ) ),
			array(
				'id'      => 'kid',
				'type'    => 'select',
				'label'   => __( 'KID payment reference', 'order-documents' ),
				'default' => 'none',
				'options' => array(
					'none'  => __( 'None', 'order-documents' ),
					'mod10' => __( 'MOD10 (Luhn)', 'order-documents' ),
					'mod11' => __( 'MOD11', 'order-documents' ),
				),
				'description' => __( 'Norwegian payment reference available as {{kid}}, built from the document number (or the order number when the document has none).', 'order-documents' ),
			),
			array( 'id' => 'kid_length', 'type' => 'number', 'label' => __( 'KID length', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 25, 'description' => __( 'Total length including the check digit, padded with zeros. 0 means no padding.', 'order-documents' ) ),
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
		if ( $this->setting( 'show_delivery_date' ) && $this->order->get_date_completed() ) {
			$rows['delivery_date'] = array( 'label' => __( 'Delivery date', 'order-documents' ), 'value' => odocs_format_date( $this->order->get_date_completed() ) );
		}
		if ( $this->setting( 'show_customer_number' ) && $this->order->get_customer_id() ) {
			$rows['customer_number'] = array( 'label' => __( 'Customer number', 'order-documents' ), 'value' => (string) $this->order->get_customer_id() );
		}
		$kid = $this->get_kid();
		if ( '' !== $kid ) {
			$rows['kid'] = array( 'label' => __( 'KID', 'order-documents' ), 'value' => $kid );
		}
		return $rows;
	}
}
