<?php
namespace OrderDocs\Tests;

use OrderDocs\Settings\Settings;

abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/** @var int[] */
	protected $created = array();

	/** @var \WC_Order[] */
	protected $orders = array();

	protected function order( array $args = array() ): \WC_Order {
		$order = wc_create_order();
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test product ' . wp_generate_password( 4, false ) );
		$product->set_regular_price( $args['price'] ?? 100 );
		$product->save();
		$this->created[] = $product->get_id();
		$order->add_product( $product, $args['qty'] ?? 1 );
		$this->orders[] = $order;
		$order->set_billing_email( 'test@example.test' );
		$order->calculate_totals();
		$order->set_status( $args['status'] ?? 'pending' );
		$order->save();
		return $order;
	}

	protected function tearDown(): void {
		foreach ( $this->orders as $order ) {
			foreach ( $order->get_refunds() as $refund ) {
				$refund->delete( true );
			}
			$order->delete( true );
		}
		foreach ( $this->created as $product_id ) {
			wp_delete_post( $product_id, true );
		}
		$this->orders  = array();
		$this->created = array();
		Settings::flush();
		parent::tearDown();
	}
}
