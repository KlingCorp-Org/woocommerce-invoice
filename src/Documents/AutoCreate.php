<?php
/**
 * Creates documents automatically when an order reaches a configured status.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class AutoCreate {

	public function register(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 20, 4 );
	}

	/**
	 * @param int       $order_id Order ID.
	 * @param string    $from     Old status.
	 * @param string    $to       New status.
	 * @param \WC_Order $order    Order.
	 */
	public function status_changed( $order_id, $from, $to, $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		foreach ( DocumentRegistry::enabled() as $type => $class ) {
			if ( $class::is_per_refund() || $class::is_multi_order() ) {
				continue;
			}
			$document = DocumentRegistry::make( $type, $order );
			if ( $document && in_array( $to, (array) $document->setting( 'auto_create_statuses', array() ), true ) ) {
				$document->ensure( 'status_' . $to );
			}
		}
	}
}
