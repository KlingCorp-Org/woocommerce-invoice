<?php
/**
 * Signature line block.
 *
 * @var array $block
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$label = '' !== $block['label'] ? $block['label'] : __( 'Signature', 'order-documents' );
echo '<table class="odocs-signature" style="width:100%"><tr>';
if ( $block['show_date_line'] ) {
	echo '<td style="width:40%;padding-right:8mm"><div class="odocs-sign-line">' . esc_html__( 'Place and date', 'order-documents' ) . '</div></td>';
}
echo '<td><div class="odocs-sign-line">' . esc_html( $label ) . '</div></td>';
echo '</tr></table>';
