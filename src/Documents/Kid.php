<?php
/**
 * Norwegian KID payment reference (MOD10 / MOD11 check digits).
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class Kid {

	public static function generate( Document $document, string $algorithm, int $length = 0 ): string {
		if ( ! in_array( $algorithm, array( 'mod10', 'mod11' ), true ) ) {
			return '';
		}
		$base = $document->get_plain_number();
		$base = null !== $base ? (string) $base : preg_replace( '/\D/', '', (string) $document->get_order()->get_order_number() );
		if ( '' === $base ) {
			return '';
		}
		if ( $length > 1 ) {
			$base = str_pad( $base, $length - 1, '0', STR_PAD_LEFT );
		}
		$check = 'mod10' === $algorithm ? self::mod10( $base ) : self::mod11( $base );
		return apply_filters( 'odocs_kid', $base . $check, $document, $algorithm, $length );
	}

	public static function mod10( string $digits ): string {
		$sum    = 0;
		$double = true;
		for ( $i = strlen( $digits ) - 1; $i >= 0; $i-- ) {
			$n = (int) $digits[ $i ];
			if ( $double ) {
				$n *= 2;
				if ( $n > 9 ) {
					$n -= 9;
				}
			}
			$sum   += $n;
			$double = ! $double;
		}
		return (string) ( ( 10 - ( $sum % 10 ) ) % 10 );
	}

	public static function mod11( string $digits ): string {
		$sum    = 0;
		$weight = 2;
		for ( $i = strlen( $digits ) - 1; $i >= 0; $i-- ) {
			$sum   += (int) $digits[ $i ] * $weight;
			$weight = 7 === $weight ? 2 : $weight + 1;
		}
		$remainder = $sum % 11;
		if ( 0 === $remainder ) {
			return '0';
		}
		$check = 11 - $remainder;
		return 10 === $check ? '-' : (string) $check;
	}
}
