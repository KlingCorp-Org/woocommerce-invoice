<?php
/**
 * Signature fields block: a row of labelled lines to sign or fill in by hand.
 *
 * @var array $block
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$labels = array_values( array_filter( array_map( 'trim', explode( ',', (string) $block['labels'] ) ), 'strlen' ) );
if ( ! $labels ) {
	$labels = array( __( 'Signature', 'order-documents' ), __( 'Name in print', 'order-documents' ), __( 'Date', 'order-documents' ) );
}
$labels = array_slice( $labels, 0, 5 );
if ( '' !== trim( (string) $block['heading'] ) ) {
	echo '<div class="odocs-heading">' . esc_html( $block['heading'] ) . '</div>';
}
$width = round( 100 / count( $labels ), 2 );
echo '<table class="odocs-sign-fields"><tr>';
foreach ( $labels as $i => $label ) {
	$label_html = '<div class="odocs-sign-label">' . esc_html( $label ) . '</div>';
	printf(
		'<td style="width:%s%%;%s">%s<div class="odocs-sign-space"></div>%s</td>',
		esc_attr( (string) $width ),
		$i < count( $labels ) - 1 ? 'padding-right:5mm' : '',
		'above' === $block['position'] ? $label_html : '', // phpcs:ignore WordPress.Security.EscapeOutput
		'above' === $block['position'] ? '' : $label_html // phpcs:ignore WordPress.Security.EscapeOutput
	);
}
echo '</tr></table>';
