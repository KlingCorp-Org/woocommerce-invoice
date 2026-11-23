<?php
/**
 * Billing address block.
 *
 * @var array                          $block
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$address = $data->billing_address();
if ( '' === $address ) {
	return;
}
if ( $block['show_heading'] ) {
	echo '<div class="odocs-heading">' . esc_html( '' !== $block['heading'] ? $block['heading'] : __( 'Billing address', 'order-documents' ) ) . '</div>';
}
echo '<div class="odocs-address">' . wp_kses_post( $address ) . '</div>';
$contact = array_filter( array( $data->email(), $data->phone() ) );
if ( $contact ) {
	echo '<div class="odocs-address-contact">' . implode( '<br>', array_map( 'esc_html', $contact ) ) . '</div>'; // phpcs:ignore
}
