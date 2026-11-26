<?php
/**
 * "Order document to a fixed address" e-mail: sends a document to the address set in the document
 * settings, e.g. the company that handles invoice payments.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Emails;

use OrderDocs\Documents\Document;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email', false ) || ! class_exists( DocumentEmail::class ) ) {
	return;
}

class DocumentAddressEmail extends DocumentEmail {

	public function __construct() {
		$this->id             = 'odocs_document_address';
		$this->customer_email = false;
		$this->title          = __( 'Order document to a fixed address', 'order-documents' );
		$this->description    = __( 'Sent to the fixed address in the document settings (for example the company that handles your invoice payments) when the order reaches a chosen status, or from the order screen. The PDF is attached.', 'order-documents' );
		$this->template_html  = 'emails/odocs-document-address.php';
		$this->template_plain = 'emails/plain/odocs-document-address.php';
		$this->template_base  = ODOCS_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_date}'      => '',
			'{order_number}'    => '',
			'{document_title}'  => '',
			'{document_number}' => '',
		);
		\WC_Email::__construct();
	}

	public function get_default_subject() {
		return __( '{document_title} {document_number} – order #{order_number}', 'order-documents' );
	}

	public function get_default_heading() {
		return __( '{document_title} {document_number}', 'order-documents' );
	}

	public function get_default_additional_content() {
		return '';
	}

	/**
	 * @param \WC_Order $order    Order.
	 * @param Document  $document Document.
	 */
	protected function recipient_for( $order, $document ): string {
		return (string) $document->setting( 'email_address', '' );
	}

	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $this->object,
				'document'           => $this->document,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'document'           => $this->document,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
