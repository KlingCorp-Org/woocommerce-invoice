<?php
/**
 * Atomic, gap-free number sequences.
 *
 * A number is claimed with a single UPDATE that stores the claimed value in LAST_INSERT_ID(), so two
 * requests can never receive the same number, even under heavy load.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents\Number;

defined( 'ABSPATH' ) || exit;

class Sequence {

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'odocs_sequences';
	}

	/**
	 * Sequence row name for a base name, reset period and date, e.g. "invoice", "invoice_2026" or "invoice_2026_11".
	 */
	public static function name( string $base, string $reset, ?\DateTimeInterface $date = null ): string {
		$date = $date ?: new \DateTimeImmutable( 'now', wp_timezone() );
		if ( $date instanceof \DateTime || $date instanceof \DateTimeImmutable ) {
			$date = ( new \DateTimeImmutable( '@' . $date->getTimestamp() ) )->setTimezone( wp_timezone() );
		}
		switch ( $reset ) {
			case 'yearly':
				return $base . '_' . $date->format( 'Y' );
			case 'monthly':
				return $base . '_' . $date->format( 'Y_m' );
			default:
				return $base;
		}
	}

	/**
	 * Claims the next number in a sequence and returns it.
	 */
	public static function claim( string $name ): int {
		global $wpdb;
		$table = self::table();
		self::ensure_row( $name );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET next_value = LAST_INSERT_ID(next_value) + 1, updated_at = %s WHERE name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', true ),
				$name
			)
		);
		$number = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		// phpcs:enable
		if ( $number < 1 ) {
			throw new \RuntimeException( 'Could not claim a document number for sequence ' . $name );
		}
		do_action( 'odocs_number_claimed', $number, $name );
		return $number;
	}

	/**
	 * The number that the next claim() will return.
	 */
	public static function peek( string $name ): int {
		global $wpdb;
		$table = self::table();
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT next_value FROM {$table} WHERE name = %s", $name ) ); // phpcs:ignore
		return null === $value ? 1 : max( 1, (int) $value );
	}

	public static function set_next( string $name, int $next ): void {
		global $wpdb;
		$table = self::table();
		self::ensure_row( $name );
		$wpdb->update( $table, array( 'next_value' => max( 1, $next ), 'updated_at' => current_time( 'mysql', true ) ), array( 'name' => $name ) ); // phpcs:ignore
		do_action( 'odocs_sequence_set', $name, $next );
	}

	/**
	 * All stored sequences, for the overview in Tools.
	 *
	 * @return array<int,object>
	 */
	public static function all(): array {
		global $wpdb;
		$table = self::table();
		return (array) $wpdb->get_results( "SELECT name, next_value, updated_at FROM {$table} ORDER BY name" ); // phpcs:ignore
	}

	private static function ensure_row( string $name ): void {
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (name, next_value, updated_at) VALUES (%s, 1, %s)", $name, current_time( 'mysql', true ) ) ); // phpcs:ignore
	}
}
