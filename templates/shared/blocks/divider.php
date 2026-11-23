<?php
/**
 * Divider block.
 *
 * @var array                         $block
 * @var \OrderDocs\Rendering\Renderer $r
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$color = $block['color'] ? $r->color( $block['color'] ) : '#d1d5db';
printf( '<div style="%s"></div>', esc_attr( sprintf( 'border-top:%spt %s %s;height:0;line-height:0;font-size:0;', (float) $block['thickness'], $block['style'], $color ) ) );
