<?php
/**
 * Document download buttons for customers: My Account orders list, order details page
 * (also shown on the thank-you page for guests, using the order key).
 *
 * @package OrderDocs
 */

namespace OrderDocs\Frontend;

use OrderDocs\Documents\Document;
use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class MyAccount {

	public function register(): void {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'orders_list_actions' ), 20, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_details_buttons' ), 20 );
	}

	/**
	 * Documents of an order the customer may download.
	 *
	 * @return Document[]
	 */
	public static function customer_documents( \WC_Order $order ): array {
		$documents = array();
		foreach ( DocumentRegistry::enabled() as $type => $class ) {
			if ( ! $class::supports_emails() ) {
				continue;
			}
			if ( $class::is_per_refund() ) {
				foreach ( array_reverse( $order->get_refunds() ) as $refund ) {
					$document = DocumentRegistry::make( $type, $order, $refund );
					if ( $document && $document->is_visible_to_customer() ) {
						$documents[] = $document;
					}
				}
				continue;
			}
			$document = DocumentRegistry::make( $type, $order );
			if ( $document && $document->is_visible_to_customer() ) {
				$documents[] = $document;
			}
		}
		return apply_filters( 'odocs_customer_documents', $documents, $order );
	}

	public static function url( Document $document ): string {
		$args = array( 'context' => is_user_logged_in() ? 'customer' : 'guest' );
		if ( $document->get_refund() ) {
			$args['refund_id'] = $document->get_refund()->get_id();
		}
		if ( ! is_user_logged_in() ) {
			$args['order_key'] = $document->get_order()->get_order_key();
		}
		return odocs_document_url( $document->get_type(), array( $document->get_order()->get_id() ), $args );
	}

	private static function button_label( Document $document ): string {
		$label = $document->get_title();
		if ( $document->get_refund() && $document->get_number() ) {
			$label .= ' ' . $document->get_number();
		}
		return $label;
	}

	/**
	 * @param array     $actions Actions.
	 * @param \WC_Order $order   Order.
	 */
	public function orders_list_actions( $actions, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return $actions;
		}
		foreach ( self::customer_documents( $order ) as $document ) {
			$actions[ 'odocs_' . $document->get_key() ] = array(
				'url'  => self::url( $document ),
				'name' => self::button_label( $document ),
			);
		}
		return $actions;
	}

	/**
	 * @param \WC_Order $order Order.
	 */
	public function order_details_buttons( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		if ( ! is_user_logged_in() && ! Settings::get( 'advanced', 'guest_access', 1 ) ) {
			return;
		}
		$documents = self::customer_documents( $order );
		if ( ! $documents ) {
			return;
		}
		echo '<section class="odocs-customer-documents"><h2 class="woocommerce-column__title">' . esc_html__( 'Documents', 'order-documents' ) . '</h2><p>';
		foreach ( $documents as $document ) {
			printf(
				'<a class="woocommerce-button button odocs-download" href="%s" target="_blank" rel="noopener nofollow">%s</a> ',
				esc_url( self::url( $document ) ),
				esc_html( self::button_label( $document ) )
			);
		}
		echo '</p></section>';
	}
}
