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
$rows = array();
foreach ( $data->data_rows() as $key => $row ) {
	$toggle = $toggles[ $row['group'] ] ?? 'show_other';
	if ( ! empty( $block[ $toggle ] ) && '' !== (string) $row['value'] ) {
		$rows[] = $row;
	}
}
if ( ! $rows ) {
	return;
}
echo '<table class="odocs-data-table' . ( $block['boxed'] ? ' odocs-data-boxed' : '' ) . '">';
foreach ( $rows as $row ) {
	printf( '<tr><td class="odocs-label">%s</td><td class="odocs-value">%s</td></tr>', esc_html( $row['label'] ), esc_html( $row['value'] ) );
}
echo '</table>';
