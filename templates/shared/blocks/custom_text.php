<?php
/**
 * Custom text block.
 *
 * @var array                         $block
 * @var \OrderDocs\Documents\Document $document
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$content = trim( (string) $block['content'] );
if ( '' !== $content ) {
	echo '<div class="odocs-text">' . wp_kses_post( odocs_autop( \OrderDocs\Rendering\Placeholders::replace( $content, $document ) ) ) . '</div>';
}
