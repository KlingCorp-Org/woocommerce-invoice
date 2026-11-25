<?php
/**
 * Logo block.
 *
 * @var array                             $block
 * @var \OrderDocs\Documents\Document     $document
 * @var \OrderDocs\Rendering\OrderData    $data
 * @var \OrderDocs\Rendering\Renderer     $r
 * @var array                             $frame context, w and h (mm).
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$logo   = $data->logo();
$height = (float) $block['max_height'] > 0 ? (float) $block['max_height'] : (float) $r->style()['logo_height'];
$shop   = $data->shop();
$fit    = 'canvas' === ( $frame['context'] ?? '' ) && (float) $block['max_height'] <= 0 && ! empty( $frame['h'] );
if ( $logo && $logo['width'] && $logo['height'] && $fit ) {
	// Placed freely: the logo fills its box, keeping its proportions.
	$ratio  = $logo['width'] / $logo['height'];
	$height = min( (float) $frame['h'], (float) $frame['w'] / $ratio );
	$width  = $height * $ratio;
	echo $r->img( $logo, sprintf( 'width:%smm;height:%smm;', round( $width, 2 ), round( $height, 2 ) ), $shop['shop_name'] ); // phpcs:ignore
} elseif ( $logo && $logo['width'] && $logo['height'] ) {
	$ratio = $logo['width'] / $logo['height'];
	$width = $height * $ratio;
	if ( $width > 85 ) {
		$width  = 85;
		$height = $width / $ratio;
	}
	echo $r->img( $logo, sprintf( 'width:%smm;height:%smm;', round( $width, 2 ), round( $height, 2 ) ), $shop['shop_name'] ); // phpcs:ignore
} elseif ( $block['name_fallback'] && '' !== $shop['shop_name'] ) {
	echo '<div class="odocs-logo-text">' . esc_html( $shop['shop_name'] ) . '</div>';
}
