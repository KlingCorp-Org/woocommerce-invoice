<?php
/**
 * Shipping details block: method, weight, quantity, tracking number and extra text.
 *
 * @var array                          $block
 * @var \OrderDocs\Documents\Document  $document
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$order = $document->get_order();
$lines = array();
if ( $block['show_method'] && $order->get_shipping_method() ) {
	$lines[] = esc_html__( 'Shipping method', 'order-documents' ) . ': <strong>' . esc_html( $order->get_shipping_method() ) . '</strong>';
}
$tracking_key = trim( (string) $block['tracking_meta'] );
if ( '' !== $tracking_key ) {
	$tracking = $order->get_meta( $tracking_key );
	if ( is_scalar( $tracking ) && '' !== (string) $tracking ) {
		$lines[] = esc_html__( 'Tracking number', 'order-documents' ) . ': <strong>' . esc_html( (string) $tracking ) . '</strong>';
	}
}
if ( $block['show_weight'] ) {
	$weight = $data->total_weight();
	if ( $weight > 0 ) {
		$lines[] = esc_html__( 'Weight', 'order-documents' ) . ': ' . esc_html( wc_format_weight( $weight ) );
	}
}
if ( $block['show_quantity'] ) {
	$lines[] = esc_html__( 'Total quantity', 'order-documents' ) . ': ' . esc_html( $data->quantity( $data->total_quantity() ) );
}
$text = trim( (string) $block['text'] );
if ( '' !== $text ) {
	$lines[] = wp_kses_post( odocs_autop( \OrderDocs\Rendering\Placeholders::replace( $text, $document ) ) );
}
if ( ! $lines ) {
	return;
}
if ( $block['show_heading'] ) {
	echo '<div class="odocs-heading">' . esc_html( '' !== $block['heading'] ? $block['heading'] : __( 'Delivery', 'order-documents' ) ) . '</div>';
}
echo '<div class="odocs-shipping-info">' . implode( '<br>', $lines ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
