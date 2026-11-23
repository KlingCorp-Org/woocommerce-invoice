<?php
/**
 * Paid stamp block.
 *
 * @var array                         $block
 * @var \OrderDocs\Documents\Document $document
 * @var \OrderDocs\Rendering\Renderer $r
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

if ( $block['only_if_paid'] && ! $document->get_order()->is_paid() ) {
	return;
}
if ( 'receipt' === $document->get_type() && ! $document->setting( 'show_paid_stamp', 1 ) ) {
	return;
}
$text  = '' !== $block['text'] ? $block['text'] : (string) $document->setting( 'paid_stamp_text', __( 'PAID', 'order-documents' ) );
$color = $block['color'] ? $r->color( $block['color'] ) : '#15803d';
if ( '' === trim( $text ) ) {
	$text = __( 'PAID', 'order-documents' );
}
printf( '<span class="odocs-stamp" style="%s">%s</span>', esc_attr( 'color:' . $color . ';border-color:' . $color ), esc_html( $text ) );
