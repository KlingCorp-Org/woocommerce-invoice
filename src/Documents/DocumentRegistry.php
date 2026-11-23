<?php
/**
 * Registry of document types.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class DocumentRegistry {

	/** @var array<string,string>|null */
	private static $types = null;

	/**
	 * @return array<string,class-string<Document>> type => class
	 */
	public static function types(): array {
		if ( null === self::$types ) {
			self::$types = (array) apply_filters(
				'odocs_document_types',
				array(
					'invoice'       => Invoice::class,
					'packing_slip'  => PackingSlip::class,
					'delivery_note' => DeliveryNote::class,
					'credit_note'   => CreditNote::class,
					'proforma'      => ProformaInvoice::class,
					'receipt'       => Receipt::class,
					'picking_list'  => PickingList::class,
				)
			);
		}
		return self::$types;
	}

	/**
	 * @return class-string<Document>|null
	 */
	public static function get_class( string $type ): ?string {
		$types = self::types();
		return isset( $types[ $type ] ) && class_exists( $types[ $type ] ) ? $types[ $type ] : null;
	}

	public static function exists( string $type ): bool {
		return null !== self::get_class( $type );
	}

	/**
	 * Enabled types, in registry order.
	 *
	 * @return array<string,class-string<Document>>
	 */
	public static function enabled(): array {
		$enabled = array();
		foreach ( self::types() as $type => $class ) {
			if ( class_exists( $class ) && $class::is_type_enabled() ) {
				$enabled[ $type ] = $class;
			}
		}
		return $enabled;
	}

	/**
	 * @return array<string,string> type => label
	 */
	public static function labels(): array {
		$labels = array();
		foreach ( self::types() as $type => $class ) {
			if ( class_exists( $class ) ) {
				$labels[ $type ] = $class::label();
			}
		}
		return $labels;
	}

	/**
	 * Creates a document object for an order (and refund, for credit notes).
	 *
	 * @param \WC_Order|int             $order  Order.
	 * @param \WC_Order_Refund|int|null $refund Refund.
	 */
	public static function make( string $type, $order, $refund = null ): ?Document {
		$class = self::get_class( $type );
		if ( ! $class ) {
			return null;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order );
		if ( $order instanceof \WC_Order_Refund ) {
			$refund = $order;
			$order  = wc_get_order( $order->get_parent_id() );
		}
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		if ( null !== $refund && ! $refund instanceof \WC_Order_Refund ) {
			$refund = wc_get_order( $refund );
			$refund = $refund instanceof \WC_Order_Refund ? $refund : null;
		}
		return new $class( $order, $refund );
	}
}
