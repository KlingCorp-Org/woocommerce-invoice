<?php
/**
 * "Order document" e-mail: sends any document to the customer from the order actions.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Emails;

use OrderDocs\Documents\Document;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Email', false ) ) {
	return;
}

class DocumentEmail extends \WC_Email {

	/** @var Document|null */
	public $document = null;

	public function __construct() {
		$this->id             = 'odocs_document';
		$this->customer_email = true;
		$this->title          = __( 'Order document', 'order-documents' );
		$this->description    = __( 'Sent when you e-mail an invoice, packing slip or other document to the customer from the order screen. The PDF is attached.', 'order-documents' );
		$this->template_html  = 'emails/odocs-document.php';
		$this->template_plain = 'emails/plain/odocs-document.php';
		$this->template_base  = ODOCS_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_date}'      => '',
			'{order_number}'    => '',
			'{document_title}'  => '',
			'{document_number}' => '',
		);
		parent::__construct();
		$this->manual = true;
	}

	/**
	 * Collapses double spaces left by empty placeholders (e.g. a document without a number).
	 *
	 * @param string $string Text.
	 */
	public function format_string( $string ) {
		return trim( preg_replace( '/[ ]{2,}/', ' ', (string) parent::format_string( $string ) ) );
	}

	public function get_default_subject() {
		return __( '{document_title} {document_number} for order #{order_number}', 'order-documents' );
	}

	public function get_default_heading() {
		return __( 'Your {document_title}', 'order-documents' );
	}

	public function get_default_additional_content() {
		return __( 'Thank you for your order.', 'order-documents' );
	}

	/**
	 * @param \WC_Order $order    Order.
	 * @param Document  $document Document.
	 */
	public function trigger( $order, $document = null ) {
		if ( ! $order instanceof \WC_Order || ! $document instanceof Document ) {
			return;
		}
		$this->setup_locale();
		$this->object                            = $order;
		$this->document                          = $document;
		$this->recipient                         = $order->get_billing_email();
		$this->placeholders['{order_date}']      = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{order_number}']    = $order->get_order_number();
		$this->placeholders['{document_title}']  = $document->get_title();
		$this->placeholders['{document_number}'] = $document->get_number();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$sent = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			$order->add_order_note(
				sprintf(
					/* translators: 1: document title, 2: e-mail address */
					$sent ? __( '%1$s e-mailed to %2$s.', 'order-documents' ) : __( '%1$s could not be e-mailed to %2$s.', 'order-documents' ),
					$document->get_title() . ( $document->get_number() ? ' ' . $document->get_number() : '' ),
					$this->get_recipient()
				),
				false,
				true
			);
		}
		$this->restore_locale();
		$this->document = null;
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
