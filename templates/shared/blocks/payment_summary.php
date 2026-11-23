<?php
/**
 * Payment band: account, payment reference (KID/OCR or document number), due date and amount to pay.
 *
 * @var array                          $block
 * @var \OrderDocs\Documents\Document  $document
 * @var \OrderDocs\Rendering\OrderData $data
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

if ( ! $document::shows_prices() || $data->is_refund() ) {
	return;
}
$order = $document->get_order();
if ( $block['hide_if_paid'] && $order->is_paid() ) {
	return;
}
$general = \OrderDocs\Settings\Settings::section( 'general' );
$cells   = array();
$account = trim( (string) ( $general['bank_account'] ?: $general['iban'] ) );
if ( '' !== $account ) {
	$cells[] = array( '' !== $block['account_label'] ? $block['account_label'] : __( 'Bank account', 'order-documents' ), esc_html( $account ) );
}
$reference = method_exists( $document, 'get_kid' ) ? $document->get_kid() : '';
if ( '' === $reference ) {
	$reference = $document->get_number() ?: (string) $order->get_order_number();
}
$cells[] = array( '' !== $block['reference_label'] ? $block['reference_label'] : __( 'Payment reference', 'order-documents' ), esc_html( $reference ) );
if ( $block['show_due'] && method_exists( $document, 'get_due_date' ) && $document->get_due_date() ) {
	$cells[] = array( __( 'Due date', 'order-documents' ), esc_html( odocs_format_date( $document->get_due_date() ) ) );
}
if ( $block['show_amount'] ) {
	$cells[] = array( __( 'Amount to pay', 'order-documents' ), '<span class="odocs-pay-amount">' . wp_kses_post( $data->money( $order->get_total() - $order->get_total_refunded() ) ) . '</span>' );
}
$width = round( 100 / count( $cells ), 2 );
echo '<table class="odocs-data-grid odocs-payment-band"><tr>';
foreach ( $cells as $cell ) {
	printf( '<td style="width:%s%%"><div class="odocs-grid-label">%s</div><div class="odocs-grid-value">%s</div></td>', esc_attr( (string) $width ), esc_html( $cell[0] ), $cell[1] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
}
echo '</tr></table>';
