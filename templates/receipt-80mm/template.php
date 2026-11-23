<?php
/**
 * Receipt 80 mm template (thermal printers).
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'        => __( 'Receipt 80 mm', 'order-documents' ),
	'description' => __( 'Narrow single-column layout for 80 mm receipt printers.', 'order-documents' ),
	'table_style' => 'plain',
	'paper'       => '80mm',
	'margins'     => array( 5, 4, 5, 4 ),
);
