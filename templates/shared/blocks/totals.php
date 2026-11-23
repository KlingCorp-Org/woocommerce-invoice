<?php
/**
 * Totals block.
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
$totals = $data->totals(
	array(
		'subtotal' => $block['show_subtotal'],
		'discount' => $block['show_discount'],
		'shipping' => $block['show_shipping'],
		'fees'     => $block['show_fees'],
		'taxes'    => $block['show_taxes'],
	)
);
if ( ! $totals ) {
	return;
}
echo '<table class="odocs-totals">';
foreach ( $totals as $key => $row ) {
	$label = 'total' === $key && '' !== $block['total_label'] ? $block['total_label'] : $row['label'];
	printf(
		'<tr class="odocs-total-%1$s"><td class="odocs-label">%2$s</td><td class="odocs-value">%3$s</td></tr>',
		esc_attr( $row['class'] ),
		esc_html( $label ),
		wp_kses_post( $row['value'] )
	);
}
echo '</table>';
