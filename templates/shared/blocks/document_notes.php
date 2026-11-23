<?php
/**
 * Notes block.
 *
 * @var array                          $block
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$content = $data->document_notes();
if ( '' === trim( $content ) ) {
	return;
}
if ( $block['show_heading'] ) {
	echo '<div class="odocs-heading">' . esc_html( '' !== $block['heading'] ? $block['heading'] : __( 'Notes', 'order-documents' ) ) . '</div>';
}
echo '<div class="odocs-text">' . wp_kses_post( $content ) . '</div>';
