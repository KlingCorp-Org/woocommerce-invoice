<?php
namespace OrderDocs\Tests;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Rendering\PdfGenerator;
use OrderDocs\Rendering\Placeholders;
use OrderDocs\Migration\WpoImporter;
use OrderDocs\Migration\LegacyAdapter;

class DocumentTest extends TestCase {

	public function test_invoice_gets_a_number_once(): void {
		$order   = $this->order();
		$invoice = DocumentRegistry::make( 'invoice', $order );
		$this->assertFalse( $invoice->exists() );
		$this->assertTrue( $invoice->ensure() );
		$number = $invoice->get_plain_number();
		$this->assertGreaterThan( 0, $number );
		$again = DocumentRegistry::make( 'invoice', wc_get_order( $order->get_id() ) );
		$again->ensure();
		$this->assertSame( $number, $again->get_plain_number(), 'Opening twice keeps the number' );
		$this->assertSame( $invoice->get_number(), wc_get_order( $order->get_id() )->get_meta( '_odocs_invoice_number' ) );
	}

	public function test_delete_removes_data(): void {
		$order   = $this->order();
		$invoice = DocumentRegistry::make( 'invoice', $order );
		$invoice->ensure();
		$invoice->delete();
		$this->assertFalse( DocumentRegistry::make( 'invoice', wc_get_order( $order->get_id() ) )->exists() );
	}

	public function test_credit_note_requires_refund_and_invoice(): void {
		$order = $this->order( array( 'status' => 'processing' ) );
		$cn    = DocumentRegistry::make( 'credit_note', $order );
		$this->assertFalse( $cn->is_allowed() );
		$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => 10 ) );
		$cn     = DocumentRegistry::make( 'credit_note', $order, $refund );
		$reason = null;
		$this->assertFalse( $cn->is_allowed( $reason ), 'No invoice yet' );
		DocumentRegistry::make( 'invoice', $order )->ensure();
		$cn = DocumentRegistry::make( 'credit_note', wc_get_order( $order->get_id() ), $refund );
		$this->assertTrue( $cn->ensure() );
		$this->assertNotEmpty( wc_get_order( $refund->get_id() )->get_meta( '_odocs_credit_note' ), 'Stored on the refund' );
	}

	public function test_receipt_only_for_paid_orders(): void {
		$this->assertFalse( DocumentRegistry::make( 'receipt', $this->order( array( 'status' => 'pending' ) ) )->is_allowed() );
		$this->assertTrue( DocumentRegistry::make( 'receipt', $this->order( array( 'status' => 'processing' ) ) )->is_allowed() );
	}

	public function test_placeholders(): void {
		$order = $this->order();
		$order->set_billing_company( 'Ærlig & Co' );
		$doc  = DocumentRegistry::make( 'invoice', $order );
		$text = Placeholders::replace( 'Order {{order_number}} for {{billing_company}} {{unknown_thing}}', $doc );
		$this->assertSame( 'Order ' . $order->get_order_number() . ' for Ærlig &amp; Co {{unknown_thing}}', $text );
	}

	public function test_pdf_renders_for_every_type_and_template(): void {
		$order = $this->order( array( 'status' => 'completed' ) );
		foreach ( array_keys( \OrderDocs\Rendering\TemplateRegistry::all() ) as $template ) {
			foreach ( array( 'invoice', 'packing_slip', 'picking_list' ) as $type ) {
				$doc = DocumentRegistry::make( $type, $order );
				$pdf = PdfGenerator::documents( array( $doc ), array( 'template' => $template ) );
				$this->assertStringStartsWith( '%PDF', $pdf, "$template/$type" );
			}
		}
	}

	public function test_legacy_wpo_data_is_read(): void {
		$order = $this->order();
		$order->update_meta_data( '_wpo_marker', 1 );
		$order->update_meta_data( '_wcpdf_invoice_number', 'F-2025-0099' );
		$order->update_meta_data( '_wcpdf_invoice_number_data', array( 'number' => 99, 'formatted_number' => 'F-2025-0099' ) );
		$order->update_meta_data( '_wcpdf_invoice_date', 1735732800 );
		$order->save();
		$data = LegacyAdapter::read( wc_get_order( $order->get_id() ), 'invoice' );
		$this->assertSame( 99, $data['number'] );
		$this->assertSame( 'F-2025-0099', $data['formatted_number'] );
		$invoice = DocumentRegistry::make( 'invoice', wc_get_order( $order->get_id() ) );
		$this->assertTrue( $invoice->is_legacy() );
		$this->assertSame( 'F-2025-0099', $invoice->get_number() );
		$invoice->ensure();
		$this->assertFalse( DocumentRegistry::make( 'invoice', wc_get_order( $order->get_id() ) )->is_legacy(), 'Adopted on first use' );
	}

	public function test_wpo_placeholder_conversion(): void {
		$this->assertSame( '{{year}}/{{order_number}}-', WpoImporter::convert_placeholders( '[invoice_year]/[order_number]-', 'invoice' ) );
		$this->assertSame( 'CN{{month}}', WpoImporter::convert_placeholders( 'CN[credit_note_month]', 'credit_note' ) );
	}
}
