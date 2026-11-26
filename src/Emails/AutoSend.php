<?php
/**
 * E-mails documents automatically when an order reaches a status chosen in the document settings:
 * to the customer and/or to a fixed address (e.g. the company that handles invoice payments).
 *
 * Sending is queued with Action Scheduler so checkout and bulk status changes stay fast, and each
 * document is sent at most once per target.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Emails;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class AutoSend {

	const HOOK = 'odocs_send_document';

	/** E-mail class key per target. */
	const EMAILS = array(
		'customer' => 'OrderDocs_Document_Email',
		'address'  => 'OrderDocs_Document_Address_Email',
	);

	public function register(): void {
		// After AutoCreate (priority 20), so documents created on the same status exist already.
		add_action( 'woocommerce_order_status_changed', array( $this, 'status_changed' ), 30, 4 );
		add_action( self::HOOK, array( $this, 'send' ), 10, 3 );
	}

	/**
	 * Targets a document type is set to be e-mailed to for an order status.
	 *
	 * @return string[]
	 */
	public static function targets_for( string $type, string $status ): array {
		$class = DocumentRegistry::get_class( $type );
		if ( ! $class || ! $class::supports_auto_send() || ! $class::is_type_enabled() ) {
			return array();
		}
		$section = 'doc_' . $type;
		$targets = array();
		if ( in_array( $status, (array) Settings::get( $section, 'email_customer_statuses', array() ), true ) ) {
			$targets[] = 'customer';
		}
		if ( in_array( $status, (array) Settings::get( $section, 'email_address_statuses', array() ), true ) ) {
			$targets[] = 'address';
		}
		return $targets;
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
		foreach ( array_keys( DocumentRegistry::enabled() ) as $type ) {
			foreach ( self::targets_for( $type, (string) $to ) as $target ) {
				$document = DocumentRegistry::make( $type, $order );
				if ( ! $document || $document->was_emailed( $target ) ) {
					continue;
				}
				$args = array( (int) $order->get_id(), (string) $type, $target );
				if ( function_exists( 'as_enqueue_async_action' ) && apply_filters( 'odocs_queue_emails', true, $document, $target ) ) {
					if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( self::HOOK, $args, 'order-documents' ) ) {
						as_enqueue_async_action( self::HOOK, $args, 'order-documents' );
					}
				} else {
					$this->send( ...$args );
				}
			}
		}
	}

	/**
	 * Sends one document to one target, unless it was sent before. Returns true when sent.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $type     Document type.
	 * @param string $target   'customer' or 'address'.
	 */
	public function send( $order_id, $type, $target ): bool {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof \WC_Order || ! isset( self::EMAILS[ $target ] ) ) {
			return false;
		}
		$class    = DocumentRegistry::get_class( (string) $type );
		$document = $class && $class::supports_auto_send() ? DocumentRegistry::make( (string) $type, $order ) : null;
		if ( ! $document || $document->was_emailed( $target ) ) {
			return false;
		}
		$reason = null;
		if ( ! $document->is_allowed( $reason ) ) {
			/* translators: 1: document title, 2: reason */
			$order->add_order_note( sprintf( __( '%1$s was not e-mailed automatically: %2$s', 'order-documents' ), $document->get_title(), (string) $reason ) );
			return false;
		}
		$emails = WC()->mailer()->get_emails();
		$email  = $emails[ self::EMAILS[ $target ] ] ?? null;
		if ( ! $email instanceof DocumentEmail || ! $document->ensure( 'email' ) ) {
			return false;
		}
		$sent = (bool) $email->trigger( $order, $document );
		if ( $sent ) {
			$document->mark_emailed( $target );
		}
		return $sent;
	}
}
