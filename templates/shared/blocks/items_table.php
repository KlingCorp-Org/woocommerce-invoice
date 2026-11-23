<?php
/**
 * Products table block.
 *
 * @var array                          $block
 * @var \OrderDocs\Documents\Document  $document
 * @var \OrderDocs\Rendering\OrderData $data
 * @var \OrderDocs\Rendering\Renderer  $r
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$types        = \OrderDocs\Rendering\Layout::column_types();
$price_keys   = array( 'unit_price', 'unit_price_excl', 'unit_price_incl', 'discount', 'tax_rate', 'tax', 'total', 'total_excl', 'total_incl' );
$picking_keys = array( 'checkbox', 'orders', 'location' );
$columns      = array();
foreach ( (array) $block['columns'] as $column ) {
	if ( empty( $column['enabled'] ) || ! isset( $types[ $column['key'] ] ) ) {
		continue;
	}
	if ( ! $document::shows_prices() && in_array( $column['key'], $price_keys, true ) ) {
		continue;
	}
	if ( 'orders' === $column['key'] && ! $document instanceof \OrderDocs\Documents\PickingList ) {
		continue;
	}
	$def       = $types[ $column['key'] ];
	$columns[] = array(
		'key'   => $column['key'],
		'label' => '' !== $column['label'] ? $column['label'] : $def['label'],
		'width' => (float) $column['width'] > 0 ? (float) $column['width'] : (float) $def['width'],
		'align' => '' !== $column['align'] ? $column['align'] : $def['align'],
	);
}
if ( ! $columns ) {
	return;
}
$items = $data->items();
$size  = (float) $block['image_size'];
echo '<table class="odocs-items">';
if ( $block['show_header'] ) {
	echo '<thead><tr>';
	foreach ( $columns as $column ) {
		printf(
			'<th class="odocs-c-%1$s odocs-col-%2$s" style="%3$s">%4$s</th>',
			esc_attr( $column['align'] ),
			esc_attr( $column['key'] ),
			$column['width'] > 0 ? esc_attr( 'width:' . $column['width'] . '%' ) : '',
			esc_html( $column['label'] )
		);
	}
	echo '</tr></thead>';
}
echo '<tbody>';
foreach ( $items as $i => $row ) {
	echo '<tr class="' . ( $i % 2 ? 'odocs-even' : 'odocs-odd' ) . '">';
	foreach ( $columns as $column ) {
		$key = $column['key'];
		if ( 'image' === $key ) {
			$cell = $r->img( $row['image'] ?? null, 'width:' . $size . 'mm;height:auto;' );
		} elseif ( 'checkbox' === $key ) {
			$cell = '<span class="odocs-checkbox">&#9744;</span>';
		} else {
			$cell = (string) ( $row[ $key ] ?? '' );
		}
		printf(
			'<td class="odocs-c-%1$s odocs-col-%2$s">%3$s</td>',
			esc_attr( $column['align'] ),
			esc_attr( $key ),
			in_array( $key, array( 'image', 'checkbox' ), true ) ? $cell : wp_kses_post( $cell ) // phpcs:ignore -- built by Renderer::img() / static markup.
		);
	}
	echo '</tr>';
}
echo '</tbody></table>';
