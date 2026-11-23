<?php
/**
 * Global helper functions.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formats a date with the document date format.
 *
 * @param \DateTimeInterface|null $date      Date.
 * @param bool                    $with_time Append the time.
 */
function odocs_format_date( $date, bool $with_time = false ): string {
	if ( ! $date instanceof \DateTimeInterface ) {
		return '';
	}
	$format = (string) \OrderDocs\Settings\Settings::get( 'general', 'date_format', '' );
	if ( '' === $format ) {
		$format = wc_date_format();
	}
	if ( $with_time ) {
		$format .= ' ' . wc_time_format();
	}
	return wp_date( $format, $date->getTimestamp() );
}

/**
 * Gets a document object.
 *
 * @param string                    $type   Document type, e.g. "invoice".
 * @param \WC_Order|int             $order  Order.
 * @param \WC_Order_Refund|int|null $refund Refund (credit notes).
 * @return \OrderDocs\Documents\Document|null
 */
function odocs_get_document( string $type, $order, $refund = null ) {
	return \OrderDocs\Documents\DocumentRegistry::make( $type, $order, $refund );
}

/**
 * Capability needed to create documents in the admin.
 */
function odocs_capability(): string {
	return (string) apply_filters( 'odocs_capability', \OrderDocs\Settings\Settings::get( 'advanced', 'capability', 'manage_woocommerce' ) );
}

function odocs_user_can_manage(): bool {
	return current_user_can( odocs_capability() ) || current_user_can( 'manage_woocommerce' );
}

/**
 * URL that outputs a document (PDF).
 *
 * @param string $type      Document type.
 * @param int[]  $order_ids Order IDs.
 * @param array  $args      Extra query args: refund_id, output, context (admin|customer), order_key.
 */
function odocs_document_url( string $type, array $order_ids, array $args = array() ): string {
	$context = $args['context'] ?? 'admin';
	unset( $args['context'] );
	$query = array_merge(
		array(
			'action'    => 'odocs_document',
			'type'      => $type,
			'order_ids' => implode( ',', array_map( 'absint', $order_ids ) ),
		),
		$args
	);
	if ( 'guest' !== $context ) {
		$query['_wpnonce'] = wp_create_nonce( 'odocs_document' );
	}
	return add_query_arg( $query, admin_url( 'admin-ajax.php' ) );
}

/**
 * Light nl2br: keeps existing HTML blocks, turns plain line breaks into <br>.
 */
function odocs_autop( string $text ): string {
	if ( '' === trim( $text ) ) {
		return '';
	}
	if ( preg_match( '/<(p|div|br|ul|ol|table|h[1-6])[\s>\/]/i', $text ) ) {
		return $text;
	}
	return nl2br( $text );
}
