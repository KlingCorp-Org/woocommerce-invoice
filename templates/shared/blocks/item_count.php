<?php
/**
 * Quantity summary block: number of lines and total quantity.
 *
 * @var array                          $block
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$items = $data->items();
if ( ! $items ) {
	return;
}
$unit = trim( (string) $block['unit'] );
echo '<table class="odocs-totals odocs-item-count">';
if ( $block['show_lines'] ) {
	printf( '<tr class="odocs-total-subtotal"><td class="odocs-label">%s</td><td class="odocs-value">%s</td></tr>', esc_html__( 'Number of lines', 'order-documents' ), esc_html( (string) count( $items ) ) );
}
printf(
	'<tr class="odocs-total-total"><td class="odocs-label">%s</td><td class="odocs-value">%s</td></tr>',
	esc_html__( 'Total quantity', 'order-documents' ),
	esc_html( trim( $data->quantity( $data->total_quantity() ) . ' ' . $unit ) )
);
echo '</table>';
