<?php
/**
 * "Order document" e-mail: sends a document to the customer, from the order actions or automatically
 * when the order reaches a status chosen in the document settings.
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
		$this->description    = __( 'Sent to the customer when you e-mail a document from the order screen, or automatically when the order reaches a status chosen in the document settings. The PDF is attached.', 'order-documents' );
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
	 * Recipient(s) of the document.
	 *
	 * @param \WC_Order $order    Order.
	 * @param Document  $document Document.
	 */
	protected function recipient_for( $order, $document ): string {
		return (string) $order->get_billing_email();
	}

	/**
	 * Sends the document. Returns true when the e-mail was sent.
	 *
	 * @param \WC_Order $order    Order.
	 * @param Document  $document Document.
	 */
	public function trigger( $order, $document = null ) {
		if ( ! $order instanceof \WC_Order || ! $document instanceof Document ) {
			return false;
		}
		$this->setup_locale();
		$this->object                            = $order;
		$this->document                          = $document;
		$this->recipient                         = $this->recipient_for( $order, $document );
		$this->placeholders['{order_date}']      = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{order_number}']    = $order->get_order_number();
		$this->placeholders['{document_title}']  = $document->get_title();
		$this->placeholders['{document_number}'] = $document->get_number();

		$sent  = false;
		$title = $document->get_title() . ( $document->get_number() ? ' ' . $document->get_number() : '' );
		if ( ! $this->is_enabled() ) {
			/* translators: 1: document title, 2: e-mail name */
			$order->add_order_note( sprintf( __( '%1$s was not e-mailed: the "%2$s" e-mail is turned off in WooCommerce → Settings → E-mails.', 'order-documents' ), $title, $this->get_title() ) );
		} elseif ( ! $this->get_recipient() ) {
			/* translators: %s: document title */
			$order->add_order_note( sprintf( __( '%s was not e-mailed: there is no e-mail address to send it to.', 'order-documents' ), $title ) );
		} else {
			$attachments = $this->get_attachments();
			if ( ! $attachments ) {
				/* translators: 1: document title, 2: e-mail address */
				$order->add_order_note( sprintf( __( '%1$s could not be e-mailed to %2$s: the PDF could not be created.', 'order-documents' ), $title, $this->get_recipient() ) );
			} else {
				$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $attachments );
				$order->add_order_note(
					sprintf(
						/* translators: 1: document title, 2: e-mail address */
						$sent ? __( '%1$s e-mailed to %2$s.', 'order-documents' ) : __( '%1$s could not be e-mailed to %2$s.', 'order-documents' ),
						$title,
						$this->get_recipient()
					),
					false,
					true
				);
			}
		}
		$this->restore_locale();
		$this->document = null;
		return $sent;
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
