<?php
/**
 * Customer note block.
 *
 * @var array                          $block
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$content = $data->customer_note();
if ( '' === trim( $content ) ) {
	return;
}
if ( $block['show_heading'] ) {
	echo '<div class="odocs-heading">' . esc_html( '' !== $block['heading'] ? $block['heading'] : __( 'Customer note', 'order-documents' ) ) . '</div>';
}
echo '<div class="odocs-text">' . wp_kses_post( $content ) . '</div>';
