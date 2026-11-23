<?php
/**
 * In-memory sample order for previews when the shop has no orders yet. Never saved.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

use OrderDocs\Documents\Document;
use OrderDocs\Documents\Number\Sequence;

defined( 'ABSPATH' ) || exit;

class DemoOrder {

	public static function create(): \WC_Order {
		static $filtered = false;
		if ( ! $filtered ) {
			$filtered = true;
			add_filter(
				'woocommerce_order_number',
				static function ( $number, $order ) {
					return $order instanceof \WC_Order && ! $order->get_id() ? '1042' : $number;
				},
				10,
				2
			);
		}
		$order = new \WC_Order();
		$order->set_currency( get_woocommerce_currency() );
		$order->set_status( 'processing' );
		$order->set_date_created( time() - DAY_IN_SECONDS );
		$order->set_date_paid( time() - DAY_IN_SECONDS );
		$order->set_payment_method_title( __( 'Card payment', 'order-documents' ) );
		$order->set_customer_note( __( 'Please leave the parcel at the back door.', 'order-documents' ) );
		$order->set_address(
			array(
				'first_name' => 'Kari',
				'last_name'  => 'Nordmann',
				'company'    => __( 'Example Company Ltd', 'order-documents' ),
				'address_1'  => 'Storgata 1',
				'postcode'   => '0155',
				'city'       => 'Oslo',
				'country'    => 'NO',
				'email'      => 'kari@example.com',
				'phone'      => '+47 400 00 000',
			),
			'billing'
		);
		$order->set_address(
			array(
				'first_name' => 'Ola',
				'last_name'  => 'Nordmann',
				'address_1'  => 'Fjordveien 12',
				'postcode'   => '5003',
				'city'       => 'Bergen',
				'country'    => 'NO',
			),
			'shipping'
		);

		$lines = array(
			array( __( 'Wool sweater, blue', 'order-documents' ), 1, 799.20, 25, array( __( 'Size', 'order-documents' ) => 'M' ) ),
			array( __( 'Hiking socks (2-pack)', 'order-documents' ), 2, 119.20, 25, array() ),
			array( __( 'Cookbook: Nordic kitchen', 'order-documents' ), 1, 349.00, 0, array() ),
		);
		$tax_total = 0.0;
		$subtotal  = 0.0;
		foreach ( $lines as $line ) {
			list( $name, $qty, $unit, $rate, $meta ) = $line;
			$net  = round( $unit * $qty, 2 );
			$tax  = round( $net * $rate / 100, 2 );
			$item = new \WC_Order_Item_Product();
			$item->set_name( $name );
			$item->set_quantity( $qty );
			$item->set_subtotal( $net );
			$item->set_total( $net );
			$item->set_subtotal_tax( $tax );
			$item->set_total_tax( $tax );
			$rate_id = $rate ? 9001 : 9002;
			$item->set_taxes( array( 'total' => array( $rate_id => $tax ), 'subtotal' => array( $rate_id => $tax ) ) );
			foreach ( $meta as $key => $value ) {
				$item->add_meta_data( $key, $value, true );
			}
			$order->add_item( $item );
			$tax_total += $tax;
			$subtotal  += $net;
		}

		$shipping = new \WC_Order_Item_Shipping();
		$shipping->set_method_title( __( 'Home delivery', 'order-documents' ) );
		$shipping->set_total( 79.20 );
		$shipping->set_taxes( array( 'total' => array( 9001 => 19.80 ) ) );
		$order->add_item( $shipping );

		foreach ( array( array( 9001, 25, 'MVA', round( 799.20 * 0.25 + 238.40 * 0.25, 2 ), 19.80 ), array( 9002, 0, 'MVA', 0, 0 ) ) as $tax_line ) {
			$tax_item = new \WC_Order_Item_Tax();
			$tax_item->set_rate_id( $tax_line[0] );
			$tax_item->set_label( $tax_line[2] );
			$tax_item->set_rate_code( 'NO-MVA-' . $tax_line[1] );
			if ( is_callable( array( $tax_item, 'set_rate_percent' ) ) ) {
				$tax_item->set_rate_percent( $tax_line[1] );
			}
			$tax_item->set_tax_total( $tax_line[3] );
			$tax_item->set_shipping_tax_total( $tax_line[4] );
			$order->add_item( $tax_item );
		}
		$order->set_shipping_total( 79.20 );
		$order->set_shipping_tax( 19.80 );
		$order->set_cart_tax( $tax_total );
		$order->set_total( round( $subtotal + $tax_total + 79.20 + 19.80, 2 ) );
		return apply_filters( 'odocs_demo_order', $order );
	}

	public static function refund( \WC_Order $order ): \WC_Order_Refund {
		$refund = new \WC_Order_Refund();
		$refund->set_currency( $order->get_currency() );
		$refund->set_reason( __( 'Returned item', 'order-documents' ) );
		$refund->set_date_created( time() );
		$item = new \WC_Order_Item_Product();
		$item->set_name( __( 'Hiking socks (2-pack)', 'order-documents' ) );
		$item->set_quantity( -1 );
		$item->set_subtotal( -119.20 );
		$item->set_total( -119.20 );
		$item->set_subtotal_tax( -29.80 );
		$item->set_total_tax( -29.80 );
		$item->set_taxes( array( 'total' => array( 9001 => -29.80 ), 'subtotal' => array( 9001 => -29.80 ) ) );
		$refund->add_item( $item );
		$tax_item = new \WC_Order_Item_Tax();
		$tax_item->set_rate_id( 9001 );
		$tax_item->set_label( 'MVA' );
		if ( is_callable( array( $tax_item, 'set_rate_percent' ) ) ) {
			$tax_item->set_rate_percent( 25 );
		}
		$tax_item->set_tax_total( -29.80 );
		$refund->add_item( $tax_item );
		$refund->set_amount( 149.00 );
		$refund->set_total( -149.00 );
		return $refund;
	}

	/**
	 * Gives a document that doesn't exist yet a date and the next number, in memory only.
	 */
	public static function fake_document_data( Document $document ): void {
		$document->set_date( time() );
		if ( $document->is_numbered() ) {
			$document->set_number( Sequence::peek( $document->sequence_name() ) );
		}
	}
}
