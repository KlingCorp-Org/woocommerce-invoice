<?php
namespace OrderDocs\Tests;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Emails\AutoSend;
use OrderDocs\Settings\Schema;
use OrderDocs\Settings\Settings;

class AutoSendTest extends TestCase {

	/** @var array[] Captured wp_mail() calls that carry one of this plugin's PDFs. */
	private $mails = array();

	/** @var array<string,array> Setting overrides per section. */
	private $overrides = array();

	protected function setUp(): void {
		parent::setUp();
		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
		add_filter( 'odocs_queue_emails', '__return_false' );
		foreach ( array( 'invoice', 'delivery_note' ) as $type ) {
			add_filter( 'odocs_settings_doc_' . $type, array( $this, 'override' ) );
		}
		// Make sure both e-mails are on, whatever the local site's WooCommerce settings say.
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email instanceof \OrderDocs\Emails\DocumentEmail ) {
				$email->enabled = 'yes';
			}
		}
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		remove_filter( 'odocs_queue_emails', '__return_false' );
		foreach ( array( 'invoice', 'delivery_note' ) as $type ) {
			remove_filter( 'odocs_settings_doc_' . $type, array( $this, 'override' ) );
		}
		$this->overrides = array();
		parent::tearDown();
	}

	/** @param mixed $return */
	public function capture( $return, array $atts ) {
		$ours = array_filter( (array) $atts['attachments'], static function ( $path ) {
			return false !== strpos( (string) $path, 'order-documents/tmp/' );
		} );
		if ( ! $ours ) {
			return true; // WooCommerce's own e-mails without our documents.
		}
		$atts['pdf'] = array();
		foreach ( (array) $atts['attachments'] as $path ) {
			$atts['pdf'][] = is_readable( $path ) && 0 === strpos( (string) file_get_contents( $path, false, null, 0, 5 ), '%PDF-' );
		}
		$this->mails[] = $atts;
		return true;
	}

	public function override( array $values ): array {
		$section = substr( current_filter(), strlen( 'odocs_settings_' ) );
		// Ignore the test site's saved attachment choices; only this plugin's own e-mails are tested here.
		return array_merge( $values, array( 'attach_to_emails' => array() ), $this->overrides[ $section ] ?? array() );
	}

	private function set( string $type, array $values ): void {
		$this->overrides[ 'doc_' . $type ] = $values;
		Settings::flush();
	}

	public function test_no_automatic_emails_by_default(): void {
		foreach ( DocumentRegistry::types() as $type => $class ) {
			$defaults = Schema::defaults( 'doc_' . $type );
			$this->assertSame( array(), $defaults['attach_to_emails'] ?? array(), $type );
			$this->assertSame( array(), $defaults['email_customer_statuses'] ?? array(), $type );
			$this->assertSame( array(), $defaults['email_address_statuses'] ?? array(), $type );
			$this->assertSame( '', $defaults['email_address'] ?? '', $type );
		}
		$order = $this->order( array( 'status' => 'processing' ) );
		$order->update_status( 'completed' );
		$this->assertSame( array(), $this->mails );
	}

	public function test_invoice_is_sent_once_to_the_fixed_address(): void {
		$this->set( 'invoice', array( 'email_address_statuses' => array( 'completed' ), 'email_address' => 'payments@factor.test' ) );
		$order = $this->order( array( 'status' => 'processing' ) );
		$order->update_status( 'completed' );

		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'payments@factor.test', $this->mails[0]['to'] );
		$this->assertSame( array( true ), $this->mails[0]['pdf'] );
		$invoice = DocumentRegistry::make( 'invoice', wc_get_order( $order->get_id() ) );
		$this->assertTrue( $invoice->exists() );
		$this->assertStringContainsString( $invoice->get_number(), $this->mails[0]['subject'] );
		$this->assertTrue( $invoice->was_emailed( 'address' ) );

		// Reaching the status again does not send a second copy.
		$order = wc_get_order( $order->get_id() );
		$order->update_status( 'processing' );
		$order->update_status( 'completed' );
		$this->assertCount( 1, $this->mails );
	}

	public function test_delivery_note_is_sent_to_the_customer(): void {
		$this->set( 'delivery_note', array( 'enabled' => 1, 'email_customer_statuses' => array( 'completed' ) ) );
		$order = $this->order( array( 'status' => 'processing' ) );
		$order->update_status( 'completed' );

		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'test@example.test', $this->mails[0]['to'] );
		$this->assertSame( array( true ), $this->mails[0]['pdf'] );
	}

	public function test_nothing_is_sent_without_an_address(): void {
		$this->set( 'invoice', array( 'email_address_statuses' => array( 'completed' ), 'email_address' => '' ) );
		$order = $this->order( array( 'status' => 'processing' ) );
		$order->update_status( 'completed' );
		$this->assertSame( array(), $this->mails );
		$notes = wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' );
		$this->assertNotEmpty( preg_grep( '/no e-mail address/', $notes ) );
	}

	public function test_sending_is_queued_with_action_scheduler(): void {
		remove_filter( 'odocs_queue_emails', '__return_false' );
		$this->set( 'invoice', array( 'email_address_statuses' => array( 'completed' ), 'email_address' => 'payments@factor.test' ) );
		$order = $this->order( array( 'status' => 'processing' ) );
		$order->update_status( 'completed' );
		$args = array( $order->get_id(), 'invoice', 'address' );
		$this->assertSame( array(), $this->mails );
		$this->assertTrue( as_has_scheduled_action( AutoSend::HOOK, $args, 'order-documents' ) );
		as_unschedule_all_actions( AutoSend::HOOK, $args, 'order-documents' );
	}

	public function test_email_list_is_sanitized(): void {
		$this->assertSame( 'a@b.test, c@d.test', Settings::sanitize_email_list( " a@b.test; not-an-email ,c@d.test\na@b.test" ) );
	}
}
