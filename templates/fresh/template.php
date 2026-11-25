<?php
/**
 * Fresh template: rounded boxes, product images, tinted table header, big total and a payment band.
 * Based on the Goggo Grossist invoice, packing slip and delivery note designs.
 *
 * "style" values are used for every design setting still at its global default, so choices made under
 * Order Documents → Design always win. "palette" pins the exact shades for the default accent colour;
 * with another accent colour the shades are derived from it.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'         => __( 'Fresh', 'order-documents' ),
	'description'  => __( 'Rounded boxes, product images, a tinted table header, a large total and a payment band.', 'order-documents' ),
	'table_style'  => 'plain',
	'box_padding'  => array( 2.8, 5.3 ),
	'heading_font' => 'fredoka',
	'style'        => array(
		'accent_color'  => '#1F7A36',
		'text_color'    => '#3A4A3E',
		'muted_color'   => '#6B7A6E',
		'font_family'   => 'nunito',
		'font_size'     => 10,
		'logo_height'   => 16,
		'margin_top'    => 12,
		'margin_right'  => 14,
		'margin_bottom' => 27,
		'margin_left'   => 14,
	),
	'palette'      => array(
		'accent_light' => '#3AB854',
		'tint'         => '#EAFBEE',
		'line'         => '#D6E0D7',
	),
);
