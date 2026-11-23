<?php
/**
 * Spacer block.
 *
 * @var array $block
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

printf( '<div style="height:%smm;line-height:0;font-size:0;">&nbsp;</div>', esc_attr( (string) (float) $block['height'] ) );
