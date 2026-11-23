<?php
/**
 * Document data block: number, dates, order number, payment method...
 *
 * @var array                          $block
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$toggles = array(
	'number'          => 'show_number',
	'date'            => 'show_date',
	'order_number'    => 'show_order_number',
	'order_date'      => 'show_order_date',
	'payment_method'  => 'show_payment_method',
	'shipping_method' => 'show_shipping_method',
	'other'           => 'show_other',
);
$exclude = array_filter( array_map( 'trim', explode( ',', (string) ( $block['exclude'] ?? '' ) ) ) );
$rows    = array();
foreach ( $data->data_rows() as $key => $row ) {
	$toggle = $toggles[ $row['group'] ] ?? 'show_other';
	if ( ! empty( $block[ $toggle ] ) && '' !== (string) $row['value'] && ! in_array( $key, $exclude, true ) ) {
		$rows[] = $row;
	}
}
if ( ! $rows ) {
	return;
}
if ( 'grid' === ( $block['layout'] ?? 'table' ) ) {
	$per_row = max( 1, min( 6, (int) $block['grid_columns'] ) );
	$width   = round( 100 / $per_row, 2 );
	echo '<table class="odocs-data-grid' . ( $block['boxed'] ? ' odocs-data-boxed' : '' ) . '">';
	foreach ( array_chunk( $rows, $per_row ) as $chunk ) {
		echo '<tr>';
		foreach ( $chunk as $row ) {
			printf( '<td style="width:%s%%"><div class="odocs-grid-label">%s</div><div class="odocs-grid-value">%s</div></td>', esc_attr( (string) $width ), esc_html( $row['label'] ), esc_html( $row['value'] ) );
		}
		for ( $i = count( $chunk ); $i < $per_row; $i++ ) {
			printf( '<td style="width:%s%%"></td>', esc_attr( (string) $width ) );
		}
		echo '</tr>';
	}
	echo '</table>';
	return;
}
echo '<table class="odocs-data-table' . ( $block['boxed'] ? ' odocs-data-boxed' : '' ) . '">';
foreach ( $rows as $row ) {
	printf( '<tr><td class="odocs-label">%s</td><td class="odocs-value">%s</td></tr>', esc_html( $row['label'] ), esc_html( $row['value'] ) );
}
echo '</table>';
