<?php
/**
 * Footer block.
 *
 * @var array                          $block
 * @var \OrderDocs\Documents\Document  $document
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

if ( 'columns' === ( $block['layout'] ?? 'text' ) ) {
	$shop    = $data->shop();
	$columns = array(
		array_filter( array( '<strong>' . esc_html( $shop['shop_name'] ) . '</strong>', wp_kses_post( $shop['shop_address'] ) ) ),
		array_filter( array( esc_html( $shop['org_number'] ), $shop['vat_number'] ? esc_html( sprintf( /* translators: %s: VAT number */ __( 'VAT: %s', 'order-documents' ), $shop['vat_number'] ) ) : '', esc_html( $shop['register_text'] ) ) ),
		array_filter( array( esc_html( $shop['shop_website'] ), esc_html( $shop['shop_email'] ), esc_html( $shop['shop_phone'] ) ) ),
	);
	$extra = trim( (string) $block['content'] );
	echo '<table class="odocs-footer-columns' . ( $block['show_border'] ? ' odocs-footer-border' : '' ) . '"><tr>';
	foreach ( $columns as $lines ) {
		echo '<td>' . implode( '<br>', $lines ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
	}
	echo '</tr></table>';
	if ( '' !== $extra ) {
		echo '<div class="odocs-footer-extra">' . wp_kses_post( odocs_autop( \OrderDocs\Rendering\Placeholders::replace( $extra, $document ) ) ) . '</div>';
	}
	return;
}
$content = trim( (string) $block['content'] );
$content = '' !== $content ? odocs_autop( \OrderDocs\Rendering\Placeholders::replace( $content, $document ) ) : $data->shop()['footer'];
if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
	return;
}
echo '<div class="odocs-footer' . ( $block['show_border'] ? ' odocs-footer-border' : '' ) . '">' . wp_kses_post( $content ) . '</div>';
