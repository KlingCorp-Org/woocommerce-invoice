<?php
/**
 * VAT summary block: basis and VAT per rate.
 *
 * @var array                          $block
 * @var \OrderDocs\Documents\Document  $document
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

if ( ! $document::shows_prices() ) {
	return;
}
$rows = $data->vat_summary();
if ( ! $rows ) {
	return;
}
if ( $block['show_heading'] ) {
	echo '<div class="odocs-heading">' . esc_html( '' !== $block['heading'] ? $block['heading'] : __( 'VAT summary', 'order-documents' ) ) . '</div>';
}
echo '<table class="odocs-vat"><thead><tr>';
echo '<th>' . esc_html__( 'Rate', 'order-documents' ) . '</th><th>' . esc_html__( 'Basis', 'order-documents' ) . '</th><th>' . esc_html__( 'VAT', 'order-documents' ) . '</th><th>' . esc_html__( 'Total', 'order-documents' ) . '</th>';
echo '</tr></thead><tbody>';
foreach ( $rows as $row ) {
	printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( $row['rate'] ), wp_kses_post( $row['basis'] ), wp_kses_post( $row['tax'] ), wp_kses_post( $row['total'] ) );
}
echo '</tbody></table>';
