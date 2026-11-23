<?php
/**
 * Shop details block.
 *
 * @var array                          $block
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$shop  = $data->shop();
$lines = array();
if ( $block['show_name'] && $shop['shop_name'] ) {
	$lines[] = '<div class="odocs-shop-name">' . esc_html( $shop['shop_name'] ) . '</div>';
}
if ( $block['show_address'] && $shop['shop_address'] ) {
	$lines[] = '<div class="odocs-shop-address">' . wp_kses_post( $shop['shop_address'] ) . '</div>';
}
$contact = array();
if ( $block['show_phone'] && $shop['shop_phone'] ) {
	$contact[] = esc_html( $shop['shop_phone'] );
}
if ( $block['show_email'] && $shop['shop_email'] ) {
	$contact[] = esc_html( $shop['shop_email'] );
}
if ( $block['show_website'] && $shop['shop_website'] ) {
	$contact[] = esc_html( $shop['shop_website'] );
}
if ( $contact ) {
	$lines[] = '<div class="odocs-shop-contact">' . implode( '<br>', $contact ) . '</div>';
}
$legal = array();
if ( $block['show_org_number'] && $shop['org_number'] ) {
	$legal[] = esc_html( $shop['org_number'] );
}
if ( $block['show_vat_number'] && $shop['vat_number'] ) {
	/* translators: %s: VAT number */
	$legal[] = esc_html( sprintf( __( 'VAT: %s', 'order-documents' ), $shop['vat_number'] ) );
}
if ( $block['show_register_text'] && $shop['register_text'] ) {
	$legal[] = esc_html( $shop['register_text'] );
}
if ( $legal ) {
	$lines[] = '<div class="odocs-shop-legal">' . implode( '<br>', $legal ) . '</div>';
}
foreach ( array( 1, 2, 3 ) as $n ) {
	if ( $block[ 'show_extra_' . $n ] && $shop[ 'extra_' . $n ] ) {
		$lines[] = '<div class="odocs-shop-extra">' . wp_kses_post( $shop[ 'extra_' . $n ] ) . '</div>';
	}
}
echo implode( '', $lines ); // phpcs:ignore
