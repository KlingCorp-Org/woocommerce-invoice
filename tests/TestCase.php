<?php
namespace OrderDocs\Tests;

use OrderDocs\Settings\Settings;

abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/** @var int[] */
	protected $created = array();

	protected function order( array $args = array() ): \WC_Order {
		$order = wc_create_order();
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test product ' . wp_generate_password( 4, false ) );
		$product->set_regular_price( $args['price'] ?? 100 );
		$product->save();
		$this->created[] = $product->get_id();
		$order->add_product( $product, $args['qty'] ?? 1 );
		$order->set_billing_email( 'test@example.test' );
		$order->calculate_totals();
		$order->set_status( $args['status'] ?? 'pending' );
		$order->save();
		return $order;
	}

	protected function tearDown(): void {
		Settings::flush();
		parent::tearDown();
	}
}
