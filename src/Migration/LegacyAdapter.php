<?php
/**
 * Reads document data written by WooCommerce PDF Invoices & Packing Slips (and its Pro add-on).
 *
 * The old plugin's data is only read, never changed or deleted.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Migration;

defined( 'ABSPATH' ) || exit;

class LegacyAdapter {

	/**
	 * @param \WC_Abstract_Order $object Order or refund.
	 * @param string             $slug   WPO document slug, e.g. "invoice", "credit_note".
	 * @return array|null Data in this plugin's format, or null when there is no legacy document.
	 */
	public static function read( $object, string $slug ): ?array {
		if ( ! $object instanceof \WC_Abstract_Order ) {
			return null;
		}
		$date = $object->get_meta( "_wcpdf_{$slug}_date" );
		if ( '' === $date || null === $date || false === $date ) {
			// Older versions only stored the formatted date.
			$formatted_date = $object->get_meta( "_wcpdf_{$slug}_date_formatted" );
			$date           = $formatted_date ? strtotime( $formatted_date . ' ' . wp_timezone_string() ) : null;
		}
		$number_data = $object->get_meta( "_wcpdf_{$slug}_number_data" );
		$number      = null;
		$formatted   = '';
		if ( is_array( $number_data ) && ! empty( $number_data['number'] ) ) {
			$number    = (int) $number_data['number'];
			$formatted = (string) ( $number_data['formatted_number'] ?? '' );
		}
		if ( null === $number ) {
			$plain = $object->get_meta( "_wcpdf_{$slug}_number" );
			if ( '' !== $plain && null !== $plain && false !== $plain ) {
				$number = is_numeric( $plain ) ? (int) $plain : (int) preg_replace( '/\D/', '', (string) $plain );
			}
		}
		if ( '' === $formatted ) {
			$formatted = (string) ( $object->get_meta( "_wcpdf_formatted_{$slug}_number" ) ?: $object->get_meta( "_wcpdf_{$slug}_number" ) );
		}
		if ( empty( $date ) && null === $number ) {
			return null;
		}
		if ( empty( $date ) ) {
			$created = $object->get_date_created();
			$date    = $created ? $created->getTimestamp() : time();
		}
		$notes = $object->get_meta( "_wcpdf_{$slug}_notes" );
		$data  = array(
			'date'    => (int) $date,
			'notes'   => is_string( $notes ) ? $notes : '',
			'trigger' => 'wpo_import',
		);
		if ( null !== $number && $number > 0 ) {
			$data['number']           = $number;
			$data['formatted_number'] = '' !== $formatted ? $formatted : (string) $number;
		}
		return apply_filters( 'odocs_legacy_document_data', $data, $object, $slug );
	}
}
