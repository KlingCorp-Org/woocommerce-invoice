<?php
/**
 * Formats plain sequence numbers with prefix, suffix, padding and date placeholders.
 *
 * Placeholders in prefix/suffix: {{year}} {{month}} {{day}} (document date), {{order_year}} {{order_month}}
 * {{order_day}} and {{order_number}}.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents\Number;

defined( 'ABSPATH' ) || exit;

class NumberFormatter {

	/**
	 * @param int                     $number   Plain number.
	 * @param array                   $format   prefix, suffix, padding.
	 * @param \DateTimeInterface|null $doc_date Document date.
	 * @param \WC_Abstract_Order|null $order    Order for order placeholders.
	 */
	public static function format( int $number, array $format, ?\DateTimeInterface $doc_date = null, $order = null ): string {
		$padding = max( 0, min( 20, (int) ( $format['padding'] ?? 0 ) ) );
		$plain   = $padding ? str_pad( (string) $number, $padding, '0', STR_PAD_LEFT ) : (string) $number;
		$prefix  = self::replace( (string) ( $format['prefix'] ?? '' ), $doc_date, $order );
		$suffix  = self::replace( (string) ( $format['suffix'] ?? '' ), $doc_date, $order );
		return apply_filters( 'odocs_formatted_number', $prefix . $plain . $suffix, $number, $format, $doc_date, $order );
	}

	public static function replace( string $text, ?\DateTimeInterface $doc_date = null, $order = null ): string {
		if ( false === strpos( $text, '{{' ) ) {
			return $text;
		}
		$tz       = wp_timezone();
		$doc_date = $doc_date ? ( new \DateTimeImmutable( '@' . $doc_date->getTimestamp() ) )->setTimezone( $tz ) : new \DateTimeImmutable( 'now', $tz );

		$order_date = null;
		if ( $order && is_callable( array( $order, 'get_date_created' ) ) && $order->get_date_created() ) {
			$order_date = ( new \DateTimeImmutable( '@' . $order->get_date_created()->getTimestamp() ) )->setTimezone( $tz );
		}
		$order_date = $order_date ?: $doc_date;

		$parent = $order;
		if ( $order instanceof \WC_Order_Refund ) {
			$parent = wc_get_order( $order->get_parent_id() ) ?: $order;
		}

		return strtr(
			$text,
			array(
				'{{year}}'         => $doc_date->format( 'Y' ),
				'{{y}}'            => $doc_date->format( 'y' ),
				'{{month}}'        => $doc_date->format( 'm' ),
				'{{day}}'          => $doc_date->format( 'd' ),
				'{{order_year}}'   => $order_date->format( 'Y' ),
				'{{order_month}}'  => $order_date->format( 'm' ),
				'{{order_day}}'    => $order_date->format( 'd' ),
				'{{order_number}}' => $parent && is_callable( array( $parent, 'get_order_number' ) ) ? (string) $parent->get_order_number() : '',
			)
		);
	}
}
