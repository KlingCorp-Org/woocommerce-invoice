<?php
/**
 * Document notice block (e.g. the proforma disclaimer).
 *
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$content = $data->notice();
if ( '' !== trim( $content ) ) {
	echo '<div class="odocs-notice">' . wp_kses_post( $content ) . '</div>';
}
