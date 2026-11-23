<?php
/**
 * Footer block.
 *
 * @var array                          $block
 * @var \OrderDocs\Documents\Document  $document
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$content = trim( (string) $block['content'] );
$content = '' !== $content ? odocs_autop( \OrderDocs\Rendering\Placeholders::replace( $content, $document ) ) : $data->shop()['footer'];
if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
	return;
}
echo '<div class="odocs-footer' . ( $block['show_border'] ? ' odocs-footer-border' : '' ) . '">' . wp_kses_post( $content ) . '</div>';
