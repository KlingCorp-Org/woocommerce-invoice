<?php
/**
 * Document title block.
 *
 * @var array                         $block
 * @var \OrderDocs\Documents\Document $document
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$title = '' !== trim( (string) $block['text'] ) ? (string) $block['text'] : $document->get_title();
if ( $block['show_number'] && $document->get_number() ) {
	$title .= ' ' . $document->get_number();
}
if ( $block['uppercase'] ) {
	$title = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $title, 'UTF-8' ) : strtoupper( $title );
}
$style = (float) $block['font_size'] > 0 ? 'font-size:' . (float) $block['font_size'] . 'pt' : '';
echo '<div class="odocs-title" style="' . esc_attr( $style ) . '">' . esc_html( $title ) . '</div>';
