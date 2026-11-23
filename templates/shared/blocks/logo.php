<?php
/**
 * Logo block.
 *
 * @var array                             $block
 * @var \OrderDocs\Documents\Document     $document
 * @var \OrderDocs\Rendering\OrderData    $data
 * @var \OrderDocs\Rendering\Renderer     $r
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$logo   = $data->logo();
$height = (float) $block['max_height'] > 0 ? (float) $block['max_height'] : (float) $r->style()['logo_height'];
$shop   = $data->shop();
if ( $logo && $logo['width'] && $logo['height'] ) {
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
