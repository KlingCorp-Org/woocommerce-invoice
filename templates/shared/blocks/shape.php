<?php
/**
 * Shape block: a filled and/or outlined rectangle, usually placed behind other components.
 *
 * @var array                         $block
 * @var array                         $frame context, w and h (mm).
 * @var \OrderDocs\Rendering\Renderer $r
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$border = (float) $block['border_width'];
$fill   = $block['fill'] ? $r->color( (string) $block['fill'] ) : '';
if ( ! $fill && $border <= 0 ) {
	return;
}
// Dompdf has no box-sizing, so the border is taken off the height.
$height = max( 0.5, (float) ( $frame['h'] ?? 10 ) - 2 * $border * 25.4 / 72 );
$style  = array( 'height:' . round( $height, 2 ) . 'mm', 'line-height:0', 'font-size:0' );
if ( $fill ) {
	$style[] = 'background-color:' . $fill;
}
if ( $border > 0 ) {
	$style[] = sprintf( 'border:%spt solid %s', $border, $block['border_color'] ? $r->color( (string) $block['border_color'] ) : '#9ca3af' );
}
if ( (float) $block['radius'] > 0 ) {
	$style[] = 'border-radius:' . (float) $block['radius'] . 'mm';
}
printf( '<div class="odocs-shape" style="%s"></div>', esc_attr( implode( ';', $style ) ) );
