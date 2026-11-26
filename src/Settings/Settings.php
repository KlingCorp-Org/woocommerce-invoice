<?php
/**
 * Settings storage: one option per section, merged with schema defaults.
 *
 * Sections: general, design, advanced, and doc_{type} for each document type.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Settings;

defined( 'ABSPATH' ) || exit;

class Settings {

	/** @var array<string,array> */
	private static $cache = array();

	public static function option_name( string $section ): string {
		return 'odocs_' . $section;
	}

	/**
	 * All values of a section, merged with defaults.
	 */
	public static function section( string $section ): array {
		if ( ! isset( self::$cache[ $section ] ) ) {
			$stored = get_option( self::option_name( $section ), array() );
			$stored = is_array( $stored ) ? $stored : array();
			self::$cache[ $section ] = array_merge( Schema::defaults( $section ), $stored );
		}
		return apply_filters( 'odocs_settings_' . $section, self::$cache[ $section ] );
	}

	/**
	 * @param mixed $fallback Returned when the key is unknown.
	 * @return mixed
	 */
	public static function get( string $section, string $key, $fallback = null ) {
		$values = self::section( $section );
		return array_key_exists( $key, $values ) ? $values[ $key ] : $fallback;
	}

	/**
	 * @return mixed
	 */
	public static function doc( string $type, string $key, $fallback = null ) {
		return self::get( 'doc_' . $type, $key, $fallback );
	}

	public static function update( string $section, array $values ): void {
		$current = get_option( self::option_name( $section ), array() );
		$current = is_array( $current ) ? $current : array();
		update_option( self::option_name( $section ), array_merge( $current, $values ) );
		self::flush();
	}

	public static function flush(): void {
		self::$cache = array();
	}

	/**
	 * Sanitizes a submitted section against its schema.
	 */
	public static function sanitize( string $section, $input ): array {
		$input  = is_array( $input ) ? wp_unslash( $input ) : array();
		$output = array();
		foreach ( Schema::fields( $section ) as $field ) {
			if ( empty( $field['id'] ) || in_array( $field['type'], array( 'heading', 'sequence', 'help' ), true ) ) {
				continue;
			}
			$id  = $field['id'];
			$raw = $input[ $id ] ?? null;
			switch ( $field['type'] ) {
				case 'checkbox':
					$output[ $id ] = ! empty( $raw ) ? 1 : 0;
					break;
				case 'number':
					$value = is_numeric( $raw ) ? $raw + 0 : ( $field['default'] ?? 0 );
					if ( isset( $field['min'] ) ) {
						$value = max( $field['min'], $value );
					}
					if ( isset( $field['max'] ) ) {
						$value = min( $field['max'], $value );
					}
					$output[ $id ] = $value;
					break;
				case 'textarea':
					$output[ $id ] = wp_kses_post( (string) $raw );
					break;
				case 'email_list':
					$output[ $id ] = self::sanitize_email_list( (string) $raw );
					break;
				case 'color':
					$output[ $id ] = sanitize_hex_color( (string) $raw ) ?: ( $field['default'] ?? '' );
					break;
				case 'media':
					$output[ $id ] = absint( $raw );
					break;
				case 'select':
				case 'template':
					$options       = self::field_options( $field );
					$output[ $id ] = array_key_exists( (string) $raw, $options ) ? (string) $raw : ( $field['default'] ?? '' );
					break;
				case 'multicheck':
				case 'statuses':
				case 'emails':
				case 'payment_methods':
					$options       = self::field_options( $field );
					$raw           = is_array( $raw ) ? array_map( 'strval', $raw ) : array();
					$output[ $id ] = array_values( array_intersect( $raw, array_map( 'strval', array_keys( $options ) ) ) );
					break;
				default:
					$output[ $id ] = sanitize_text_field( (string) $raw );
			}
		}
		self::flush();
		return $output;
	}

	/**
	 * Resolves dynamic option lists (order statuses, e-mails, payment gateways).
	 */
	public static function field_options( array $field ): array {
		switch ( $field['type'] ) {
			case 'statuses':
				$statuses = array();
				foreach ( wc_get_order_statuses() as $key => $label ) {
					$statuses[ preg_replace( '/^wc-/', '', $key ) ] = $label;
				}
				return $statuses;
			case 'emails':
				return self::email_options();
			case 'payment_methods':
				$methods = array();
				if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
					foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
						$methods[ $id ] = $gateway->get_method_title() ?: $id;
					}
				}
				return $methods;
		}
		$options = $field['options'] ?? array();
		return is_callable( $options ) ? (array) call_user_func( $options ) : (array) $options;
	}

	/**
	 * Cleans a comma separated list of e-mail addresses, dropping invalid ones.
	 */
	public static function sanitize_email_list( string $raw ): string {
		$valid = array();
		foreach ( preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY ) as $address ) {
			$address = sanitize_email( $address );
			if ( $address && is_email( $address ) && ! in_array( $address, $valid, true ) ) {
				$valid[] = $address;
			}
		}
		return implode( ', ', $valid );
	}

	public static function email_options(): array {
		$options = array();
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			foreach ( WC()->mailer()->get_emails() as $email ) {
				// Our own document e-mails carry their document already.
				if ( empty( $email->id ) || $email instanceof \OrderDocs\Emails\DocumentEmail ) {
					continue;
				}
				$options[ $email->id ] = $email->get_title() . ( $email->is_customer_email() ? '' : ' (' . __( 'admin', 'order-documents' ) . ')' );
				if ( 'customer_refunded_order' === $email->id ) {
					$options[ $email->id ] = __( 'Refunded order (full and partial refunds)', 'order-documents' );
				}
			}
		}
		return $options;
	}
}
