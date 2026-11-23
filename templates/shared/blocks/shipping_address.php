<?php
/**
 * Shipping address block.
 *
 * @var array                          $block
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$address = $data->shipping_address();
if ( '' === $address ) {
	return;
}
if ( $block['show_heading'] ) {
	echo '<div class="odocs-heading">' . esc_html( '' !== $block['heading'] ? $block['heading'] : __( 'Ship to', 'order-documents' ) ) . '</div>';
}
echo '<div class="odocs-address">' . wp_kses_post( $address ) . '</div>';
if ( $data->shipping_phone() ) {
	echo '<div class="odocs-address-contact">' . esc_html( $data->shipping_phone() ) . '</div>';
}
