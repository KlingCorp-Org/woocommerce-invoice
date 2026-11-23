<?php
/**
 * Attaches documents to WooCommerce e-mails.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Emails;

use OrderDocs\Documents\Document;
use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Endpoints\DocumentEndpoint;
use OrderDocs\Storage;

defined( 'ABSPATH' ) || exit;

class Attachments {

	public function register(): void {
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach' ), 20, 4 );
	}

	/**
	 * @param array             $attachments Attachments.
	 * @param string            $email_id    E-mail ID.
	 * @param mixed             $object      Order (or other object).
	 * @param \WC_Email|null    $email       E-mail.
	 */
	public function attach( $attachments, $email_id, $object, $email = null ) {
		$attachments = is_array( $attachments ) ? $attachments : array();
		if ( $object instanceof \WC_Order_Refund ) {
			$object = wc_get_order( $object->get_parent_id() );
		}
		if ( ! $object instanceof \WC_Order ) {
			return $attachments;
		}

		// Our own "send document" e-mail carries the exact document.
		if ( 'odocs_document' === $email_id && $email instanceof DocumentEmail && $email->document instanceof Document ) {
			$path = $this->file_for( $email->document );
			if ( $path ) {
				$attachments[] = $path;
			}
			return $attachments;
		}

		foreach ( DocumentRegistry::enabled() as $type => $class ) {
			if ( ! $class::supports_emails() ) {
				continue;
			}
			$probe = DocumentRegistry::make( $type, $object );
			if ( ! $probe ) {
				continue;
			}
			$wanted = (array) $probe->setting( 'attach_to_emails', array() );
			// WooCommerce uses a separate id for partial refunds; treat it like the refunded-order e-mail.
			$matches = in_array( $email_id, $wanted, true ) || ( 'customer_partially_refunded_order' === $email_id && in_array( 'customer_refunded_order', $wanted, true ) );
			if ( ! $matches ) {
				continue;
			}
			$documents = array();
			if ( $class::is_per_refund() ) {
				$refund = $email && isset( $email->refund ) && $email->refund instanceof \WC_Order_Refund ? $email->refund : null;
				if ( ! $refund ) {
					$refunds = $object->get_refunds();
					$refund  = $refunds ? $refunds[0] : null;
				}
				if ( $refund ) {
					$documents[] = DocumentRegistry::make( $type, $object, $refund );
				}
			} else {
				$documents[] = $probe;
			}
			foreach ( array_filter( $documents ) as $document ) {
				if ( ! apply_filters( 'odocs_attach_document', true, $document, $email_id, $email ) ) {
					continue;
				}
				if ( ! $document->ensure( 'email' ) ) {
					continue;
				}
				$path = $this->file_for( $document );
				if ( $path ) {
					$attachments[] = $path;
				}
			}
		}
		return $attachments;
	}

	/**
	 * Generates (or reuses the archived) PDF and writes it to a temp file for attaching.
	 */
	public function file_for( Document $document ): ?string {
		try {
			$pdf = $document->get_type() === 'picking_list' ? '' : DocumentEndpoint::pdf_for( $document );
		} catch ( \Throwable $e ) {
			wc_get_logger()->error( 'Could not attach ' . $document->get_type() . ' to e-mail: ' . $e->getMessage(), array( 'source' => 'order-documents' ) );
			return null;
		}
		if ( '' === $pdf ) {
			return null;
		}
		return Storage::write_temp( $document->get_filename(), $pdf );
	}
}
