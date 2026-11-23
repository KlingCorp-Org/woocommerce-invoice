<?php
/**
 * Outputs documents as PDF (or HTML for debugging), with access control:
 *   - admins/shop managers: capability + nonce, any order, bulk allowed;
 *   - logged-in customers: their own order (view_order) + nonce, document visible in My Account;
 *   - guests: order key (when guest access is enabled), document visible in My Account.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Endpoints;

use OrderDocs\Documents\Archive;
use OrderDocs\Documents\Document;
use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Documents\PickingList;
use OrderDocs\Rendering\PdfGenerator;
use OrderDocs\Rendering\Renderer;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class DocumentEndpoint {

	public function register(): void {
		add_action( 'wp_ajax_odocs_document', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_odocs_document', array( $this, 'handle' ) );
	}

	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below per access path.
		$type      = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) );
		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_GET['order_ids'] ?? '' ) ) ) ) ) );
		$refund_id = absint( $_GET['refund_id'] ?? 0 );
		$nonce     = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		$order_key = sanitize_text_field( wp_unslash( $_GET['order_key'] ?? '' ) );
		$want_html = 'html' === ( $_GET['output'] ?? '' );
		// phpcs:enable

		$class = DocumentRegistry::get_class( $type );
		if ( ! $class || ! $order_ids ) {
			$this->fail( __( 'Unknown document or order.', 'order-documents' ), 400 );
		}

		$is_admin = odocs_user_can_manage() && wp_verify_nonce( $nonce, 'odocs_document' );
		if ( ! $is_admin && ! $this->customer_can_access( $order_ids, $nonce, $order_key ) ) {
			$this->fail( __( 'You are not allowed to view this document.', 'order-documents' ), 403 );
		}

		$limit = (int) Settings::get( 'advanced', 'bulk_limit', 100 );
		if ( count( $order_ids ) > $limit ) {
			/* translators: %d: maximum number of orders */
			$this->fail( sprintf( __( 'Please select at most %d orders at a time.', 'order-documents' ), $limit ), 400 );
		}

		$documents = array();
		$reasons   = array();
		foreach ( $this->build_documents( $class, $type, $order_ids, $refund_id ) as $document ) {
			if ( ! $is_admin && ! $document->is_visible_to_customer() ) {
				continue;
			}
			$reason = null;
			if ( ! $document->is_allowed( $reason ) ) {
				$reasons[] = sprintf( '#%s: %s', $document->get_order()->get_order_number(), $reason );
				continue;
			}
			$document->ensure( $is_admin ? 'manual' : 'customer' );
			$documents[] = $document;
		}

		if ( ! $documents ) {
			$this->fail( $reasons ? implode( '<br>', array_map( 'esc_html', $reasons ) ) : __( 'There is nothing to print for the selected orders.', 'order-documents' ), 404, ! empty( $reasons ) );
		}

		if ( $want_html && ( $is_admin || Settings::get( 'advanced', 'debug_html', 0 ) ) ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			echo ( new Renderer( 'html' ) )->render( $documents ); // phpcs:ignore WordPress.Security.EscapeOutput -- full document built by the renderer.
			exit;
		}
		if ( Settings::get( 'advanced', 'debug_html', 0 ) && $is_admin ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			echo ( new Renderer( 'pdf' ) )->render( $documents ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}

		try {
			$pdf = 1 === count( $documents ) ? self::pdf_for( $documents[0] ) : PdfGenerator::documents( $documents );
		} catch ( \Throwable $e ) {
			wc_get_logger()->error( 'PDF generation failed: ' . $e->getMessage(), array( 'source' => 'order-documents' ) );
			$this->fail( __( 'The PDF could not be generated. Details are in WooCommerce → Status → Logs (order-documents).', 'order-documents' ), 500 );
		}

		$filename = 1 === count( $documents ) ? $documents[0]->get_filename() : sanitize_file_name( strtolower( remove_accents( $class::plural_label() ) ) . '-' . wp_date( 'Y-m-d-His' ) . '.pdf' );
		$this->send_pdf( $pdf, $filename );
	}

	/**
	 * PDF for a single document: archived copy when there is one, otherwise rendered (and archived if enabled).
	 */
	public static function pdf_for( Document $document ): string {
		$archived = Archive::get( $document );
		if ( $archived ) {
			return (string) file_get_contents( $archived ); // phpcs:ignore
		}
		$pdf = PdfGenerator::documents( array( $document ) );
		Archive::store( $document, $pdf );
		return $pdf;
	}

	/**
	 * @param class-string<Document> $class Document class.
	 * @return Document[]
	 */
	protected function build_documents( string $class, string $type, array $order_ids, int $refund_id ): array {
		$orders = array();
		foreach ( $order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order instanceof \WC_Order_Refund ) {
				$refund_id = $refund_id ?: $order->get_id();
				$order     = wc_get_order( $order->get_parent_id() );
			}
			if ( $order instanceof \WC_Order ) {
				$orders[] = $order;
			}
		}
		if ( ! $orders ) {
			return array();
		}

		if ( $class::is_multi_order() ) {
			$document = DocumentRegistry::make( $type, $orders[0] );
			if ( $document instanceof PickingList ) {
				$document->set_orders( $orders );
			}
			return $document ? array( $document ) : array();
		}

		$documents = array();
		foreach ( $orders as $order ) {
			if ( $class::is_per_refund() ) {
				$refunds = $order->get_refunds();
				if ( $refund_id && 1 === count( $orders ) ) {
					$refunds = array_filter(
						$refunds,
						static function ( $refund ) use ( $refund_id ) {
							return $refund->get_id() === $refund_id;
						}
					);
				}
				if ( ! $refunds ) {
					$documents[] = DocumentRegistry::make( $type, $order ); // Reports "refund first".
				}
				foreach ( array_reverse( $refunds ) as $refund ) {
					$documents[] = DocumentRegistry::make( $type, $order, $refund );
				}
			} else {
				$documents[] = DocumentRegistry::make( $type, $order );
			}
		}
		return array_values( array_filter( $documents ) );
	}

	protected function customer_can_access( array $order_ids, string $nonce, string $order_key ): bool {
		if ( 1 !== count( $order_ids ) ) {
			return false;
		}
		$order = wc_get_order( $order_ids[0] );
		if ( $order instanceof \WC_Order_Refund ) {
			$order = wc_get_order( $order->get_parent_id() );
		}
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		if ( is_user_logged_in() && current_user_can( 'view_order', $order->get_id() ) && wp_verify_nonce( $nonce, 'odocs_document' ) ) {
			return true;
		}
		if ( Settings::get( 'advanced', 'guest_access', 1 ) && '' !== $order_key && hash_equals( (string) $order->get_order_key(), $order_key ) ) {
			return true;
		}
		return false;
	}

	protected function send_pdf( string $pdf, string $filename ): void {
		$disposition = 'download' === Settings::get( 'general', 'output', 'inline' ) || ! empty( $_GET['download'] ) ? 'attachment' : 'inline'; // phpcs:ignore
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . str_replace( '"', '', $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	protected function fail( string $message, int $status, bool $is_html = false ): void {
		wp_die(
			$is_html ? wp_kses_post( $message ) : esc_html( $message ),
			esc_html__( 'Document', 'order-documents' ),
			array(
				'response'  => $status,
				'back_link' => true,
			)
		);
	}
}
