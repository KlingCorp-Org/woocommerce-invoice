<?php
/**
 * Entries in the "Order actions" dropdown: e-mail a document to the customer or to the fixed address,
 * regenerate archived PDFs.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Admin;

use OrderDocs\Documents\Archive;
use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class OrderActions {

	public function register(): void {
		add_filter( 'woocommerce_order_actions', array( $this, 'actions' ), 20, 2 );
		foreach ( array_keys( DocumentRegistry::types() ) as $type ) {
			add_action( 'woocommerce_order_action_odocs_email_' . $type, array( $this, 'email' ) );
			add_action( 'woocommerce_order_action_odocs_email_address_' . $type, array( $this, 'email' ) );
		}
		add_action( 'woocommerce_order_action_odocs_regenerate', array( $this, 'regenerate' ) );
	}

	/**
	 * @param array          $actions Actions.
	 * @param \WC_Order|null $order   Order.
	 */
	public function actions( $actions, $order = null ) {
		if ( ! odocs_user_can_manage() ) {
			return $actions;
		}
		foreach ( DocumentRegistry::enabled() as $type => $class ) {
			if ( ! $class::supports_emails() ) {
				continue;
			}
			/* translators: %s: document title, e.g. "Invoice" */
			$actions[ 'odocs_email_' . $type ] = sprintf( __( 'E-mail %s to customer', 'order-documents' ), strtolower( $class::label() ) );
			$address = $class::supports_auto_send() ? (string) Settings::doc( $type, 'email_address', '' ) : '';
			if ( '' !== $address ) {
				/* translators: 1: document title, e.g. "invoice", 2: e-mail address */
				$actions[ 'odocs_email_address_' . $type ] = sprintf( __( 'E-mail %1$s to %2$s', 'order-documents' ), strtolower( $class::label() ), $address );
			}
		}
		$actions['odocs_regenerate'] = __( 'Regenerate archived document PDFs', 'order-documents' );
		return $actions;
	}

	/**
	 * @param \WC_Order $order Order.
	 */
	public function email( $order ): void {
		$type      = str_replace( 'woocommerce_order_action_odocs_email_', '', current_action() );
		$email_key = 'OrderDocs_Document_Email';
		if ( 0 === strpos( $type, 'address_' ) ) {
			$type      = substr( $type, strlen( 'address_' ) );
			$email_key = 'OrderDocs_Document_Address_Email';
		}
		$emails = WC()->mailer()->get_emails();
		if ( empty( $emails[ $email_key ] ) ) {
			return;
		}
		$refund = null;
		$class  = DocumentRegistry::get_class( $type );
		if ( $class && $class::is_per_refund() ) {
			$refunds = $order->get_refunds();
			$refund  = $refunds ? $refunds[0] : null;
		}
		$document = DocumentRegistry::make( $type, $order, $refund );
		$reason   = null;
		if ( ! $document || ! $document->is_allowed( $reason ) ) {
			/* translators: 1: document title, 2: reason */
			$order->add_order_note( sprintf( __( '%1$s was not sent: %2$s', 'order-documents' ), $class ? $class::label() : $type, (string) $reason ) );
			return;
		}
		$document->ensure( 'email' );
		$emails[ $email_key ]->trigger( $order, $document );
	}

	/**
	 * @param \WC_Order $order Order.
	 */
	public function regenerate( $order ): void {
		$count = 0;
		foreach ( DocumentRegistry::types() as $type => $class ) {
			$targets = $class::is_per_refund() ? $order->get_refunds() : array( null );
			foreach ( $targets as $refund ) {
				$document = DocumentRegistry::make( $type, $order, $refund );
				if ( $document && Archive::get( $document ) ) {
					Archive::delete( $document );
					++$count;
				}
			}
		}
		/* translators: %d: number of documents */
		$order->add_order_note( sprintf( _n( '%d archived document PDF will be regenerated.', '%d archived document PDFs will be regenerated.', $count, 'order-documents' ), $count ) );
	}
}
