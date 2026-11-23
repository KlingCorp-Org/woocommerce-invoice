<?php
/**
 * Import from WooCommerce PDF Invoices & Packing Slips (WP Overnight), including its Pro documents.
 *
 * 1. scan()            — counts documents and finds the numbers to continue from.
 * 2. settings_map()    — maps the old settings onto this plugin's settings (reviewed before saving).
 * 3. apply_sequences() — continues the number sequences without gaps.
 * 4. start_copy()      — copies document data into this plugin's meta in the background (Action Scheduler).
 *
 * The old plugin's data is never modified or deleted.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Migration;

use Automattic\WooCommerce\Utilities\OrderUtil;
use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Documents\Number\Sequence;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class WpoImporter {

	const STATE_OPTION = 'odocs_wpo_import_state';
	const BATCH_HOOK   = 'odocs_wpo_import_batch';
	const BATCH_SIZE   = 50;

	public function register(): void {
		add_action( self::BATCH_HOOK, array( $this, 'run_batch' ) );
	}

	/**
	 * Our type => WPO slug (meta keys) and WPO option suffix.
	 */
	public static function type_map(): array {
		return array(
			'invoice'      => array( 'slug' => 'invoice', 'option' => 'invoice' ),
			'packing_slip' => array( 'slug' => 'packing_slip', 'option' => 'packing-slip' ),
			'credit_note'  => array( 'slug' => 'credit_note', 'option' => 'credit-note' ),
			'proforma'     => array( 'slug' => 'proforma', 'option' => 'proforma' ),
			'receipt'      => array( 'slug' => 'receipt', 'option' => 'receipt' ),
		);
	}

	public static function has_wpo_data(): bool {
		global $wpdb;
		if ( false !== get_option( 'wpo_wcpdf_settings_general', false ) ) {
			return true;
		}
		$found = $wpdb->get_var( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = '_wcpdf_invoice_number' LIMIT 1" ); // phpcs:ignore
		if ( ! $found && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'wc_orders_meta' ) ) ) {
			$found = $wpdb->get_var( "SELECT 1 FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_wcpdf_invoice_number' LIMIT 1" ); // phpcs:ignore
		}
		return (bool) $found;
	}

	public static function should_suggest_import(): bool {
		$state = self::state();
		return empty( $state['dismissed'] ) && empty( $state['settings_imported'] ) && self::has_wpo_data();
	}

	public static function state(): array {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	public static function update_state( array $changes ): void {
		update_option( self::STATE_OPTION, array_merge( self::state(), $changes ), false );
	}

	/**
	 * Meta table and id column holding order meta (HPOS or posts).
	 */
	protected static function meta_table(): array {
		global $wpdb;
		if ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return array( $wpdb->prefix . 'wc_orders_meta', 'order_id' );
		}
		return array( $wpdb->postmeta, 'post_id' );
	}

	/**
	 * Document counts, highest numbers and proposed next numbers per type.
	 */
	public static function scan(): array {
		global $wpdb;
		list( $table, $id_col ) = self::meta_table();
		$result                 = array();
		foreach ( self::type_map() as $type => $map ) {
			$slug     = $map['slug'];
			$options  = self::wpo_doc_options( $map['option'] );
			$yearly   = ! empty( $options['reset_number_yearly'] );
			$count    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT {$id_col}) FROM {$table} WHERE meta_key = %s", "_wcpdf_{$slug}_date" ) ); // phpcs:ignore
			$highest  = 0;
			$year_min = $yearly ? (int) ( new \DateTimeImmutable( 'first day of january this year 00:00', wp_timezone() ) )->getTimestamp() : 0;
			$offset   = 0;
			do {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$id_col} AS id, meta_value FROM {$table} WHERE meta_key = %s ORDER BY {$id_col} LIMIT %d, 2000", "_wcpdf_{$slug}_number_data", $offset ) ); // phpcs:ignore
				foreach ( $rows as $row ) {
					$data = maybe_unserialize( $row->meta_value );
					if ( ! is_array( $data ) || empty( $data['number'] ) ) {
						continue;
					}
					if ( $yearly ) {
						$date = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE {$id_col} = %d AND meta_key = %s LIMIT 1", $row->id, "_wcpdf_{$slug}_date" ) ); // phpcs:ignore
						if ( $date && $date < $year_min ) {
							continue;
						}
					}
					$highest = max( $highest, (int) $data['number'] );
				}
				$offset += 2000;
			} while ( count( $rows ) === 2000 );

			$wpo_next = self::wpo_store_next( $slug );
			$class    = DocumentRegistry::get_class( $type );
			$our_name = $class ? $class::sequence_name_for() : $type;
			$our_next = Sequence::peek( $our_name );

			$result[ $type ] = array(
				'label'      => $class ? $class::label() : $type,
				'count'      => $count,
				'highest'    => $highest,
				'wpo_next'   => $wpo_next,
				'yearly'     => $yearly,
				'sequence'   => $our_name,
				'our_next'   => $our_next,
				'proposed'   => max( $highest + 1, (int) $wpo_next, $our_next ),
				'has_number' => $class ? $class::supports_numbering() : false,
			);
		}
		return $result;
	}

	/**
	 * Next number of WPO's own number store table (current year table when resetting yearly).
	 */
	public static function wpo_store_next( string $slug ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wcpdf_' . $slug . '_number';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return 0;
		}
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ) ); // phpcs:ignore
		$auto   = isset( $status->Auto_increment ) ? (int) $status->Auto_increment : 0; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$max_id = (int) $wpdb->get_var( "SELECT MAX(id) FROM `{$table}`" ); // phpcs:ignore
		$calc   = (int) $wpdb->get_var( "SELECT MAX(calculated_number) FROM `{$table}`" ); // phpcs:ignore
		return max( $auto, $max_id + 1, $calc + 1 );
	}

	public static function wpo_general(): array {
		$general = get_option( 'wpo_wcpdf_settings_general', array() );
		return is_array( $general ) ? $general : array();
	}

	public static function wpo_doc_options( string $option ): array {
		$values = get_option( 'wpo_wcpdf_documents_settings_' . $option, array() );
		return is_array( $values ) ? $values : array();
	}

	/**
	 * WPO stores translatable texts either as a string or as array( 'default' => ..., 'nb_NO' => ... ).
	 *
	 * @param mixed $value Value.
	 */
	public static function text( $value ): string {
		if ( is_array( $value ) ) {
			$value = $value['default'] ?? reset( $value );
		}
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Converts WPO number placeholders ([invoice_year], [order_number]...) to this plugin's ({{year}}...).
	 */
	public static function convert_placeholders( string $text, string $slug ): string {
		return strtr(
			$text,
			array(
				"[{$slug}_year]"  => '{{year}}',
				"[{$slug}_month]" => '{{month}}',
				"[{$slug}_day]"   => '{{day}}',
				'[order_year]'    => '{{order_year}}',
				'[order_month]'   => '{{order_month}}',
				'[order_day]'     => '{{order_day}}',
				'[order_number]'  => '{{order_number}}',
			)
		);
	}

	/**
	 * Proposed settings: list of [section, key, label, value, display].
	 */
	public static function settings_map(): array {
		$map     = array();
		$general = self::wpo_general();
		$add     = static function ( $section, $key, $label, $value, $display = null ) use ( &$map ) {
			if ( null === $value || '' === $value || array() === $value ) {
				return;
			}
			$map[] = array(
				'section' => $section,
				'key'     => $key,
				'label'   => $label,
				'value'   => $value,
				'display' => null !== $display ? $display : ( is_array( $value ) ? implode( ', ', $value ) : (string) $value ),
			);
		};

		if ( $general ) {
			$add( 'general', 'shop_name', __( 'Shop name', 'order-documents' ), self::text( $general['shop_name'] ?? '' ) );
			$address = self::text( $general['shop_address'] ?? '' );
			if ( '' === $address ) {
				$city    = trim( self::text( $general['shop_address_postcode'] ?? '' ) . ' ' . self::text( $general['shop_address_city'] ?? '' ) );
				$country = self::text( $general['shop_address_country'] ?? '' );
				$country = $country && function_exists( 'WC' ) && isset( WC()->countries->countries[ $country ] ) ? WC()->countries->countries[ $country ] : $country;
				$address = implode(
					"\n",
					array_filter(
						array(
							self::text( $general['shop_address_line_1'] ?? '' ),
							self::text( $general['shop_address_line_2'] ?? '' ),
							$city,
							$country,
							self::text( $general['shop_address_additional'] ?? '' ),
						)
					)
				);
			}
			$add( 'general', 'shop_address', __( 'Shop address', 'order-documents' ), $address );
			$add( 'general', 'shop_phone', __( 'Phone', 'order-documents' ), self::text( $general['shop_phone_number'] ?? '' ) );
			$add( 'general', 'shop_email', __( 'E-mail', 'order-documents' ), self::text( $general['shop_email_address'] ?? '' ) );
			$add( 'general', 'vat_number', __( 'VAT number', 'order-documents' ), self::text( $general['vat_number'] ?? '' ) );
			$add( 'general', 'org_number', __( 'Organisation number', 'order-documents' ), self::text( $general['coc_number'] ?? '' ) );
			$add( 'general', 'footer', __( 'Footer', 'order-documents' ), self::text( $general['footer'] ?? '' ) );
			foreach ( array( 1, 2, 3 ) as $n ) {
				/* translators: %d: field number */
				$add( 'general', 'extra_' . $n, sprintf( __( 'Extra field %d', 'order-documents' ), $n ), self::text( $general[ 'extra_' . $n ] ?? '' ) );
			}
			$logo = $general['header_logo'] ?? '';
			$logo = is_array( $logo ) ? ( $logo['default'] ?? reset( $logo ) ) : $logo;
			if ( absint( $logo ) ) {
				$add( 'general', 'logo_id', __( 'Logo', 'order-documents' ), absint( $logo ), get_the_title( absint( $logo ) ) ?: '#' . absint( $logo ) );
			}
			$height = self::to_mm( (string) ( $general['header_logo_height'] ?? '' ) );
			if ( $height ) {
				$add( 'design', 'logo_height', __( 'Logo height (mm)', 'order-documents' ), $height );
			}
			$paper = strtolower( (string) ( $general['paper_size'] ?? '' ) );
			if ( in_array( $paper, array( 'a4', 'letter' ), true ) ) {
				$add( 'general', 'paper_size', __( 'Paper size', 'order-documents' ), $paper, strtoupper( $paper ) );
			}
			if ( ! empty( $general['download_display'] ) ) {
				$add( 'general', 'output', __( 'How to open PDFs', 'order-documents' ), 'download' === $general['download_display'] ? 'download' : 'inline' );
			}
			$color = sanitize_hex_color( (string) ( $general['template_color'] ?? '' ) );
			if ( $color ) {
				$add( 'design', 'accent_color', __( 'Accent colour', 'order-documents' ), $color );
			}
		}

		foreach ( self::type_map() as $type => $wpo ) {
			$options = self::wpo_doc_options( $wpo['option'] );
			$class   = DocumentRegistry::get_class( $type );
			if ( ! $options || ! $class ) {
				continue;
			}
			$section = 'doc_' . $type;
			$label   = $class::label() . ': ';
			$add( $section, 'enabled', $label . __( 'Enabled', 'order-documents' ), ! empty( $options['enabled'] ) ? 1 : 0, ! empty( $options['enabled'] ) ? __( 'Yes', 'order-documents' ) : __( 'No', 'order-documents' ) );
			if ( ! empty( $options['attach_to_email_ids'] ) && is_array( $options['attach_to_email_ids'] ) ) {
				$emails = array_keys( array_filter( $options['attach_to_email_ids'] ) );
				$add( $section, 'attach_to_emails', $label . __( 'Attach to e-mails', 'order-documents' ), $emails );
			}
			if ( ! empty( $options['disable_for_statuses'] ) && is_array( $options['disable_for_statuses'] ) ) {
				$statuses = array_map(
					static function ( $s ) {
						return preg_replace( '/^wc-/', '', (string) $s );
					},
					array_values( $options['disable_for_statuses'] )
				);
				$add( $section, 'disable_for_statuses', $label . __( 'Not available for statuses', 'order-documents' ), $statuses );
			}
			if ( isset( $options['display_shipping_address'] ) ) {
				$value = array(
					'when_different' => 'different',
					'always'         => 'always',
				)[ (string) $options['display_shipping_address'] ] ?? 'never';
				$add( $section, 'shipping_address', $label . __( 'Shipping address', 'order-documents' ), $value );
			}
			if ( isset( $options['display_billing_address'] ) ) {
				$add( $section, 'billing_address', $label . __( 'Billing address', 'order-documents' ), $options['display_billing_address'] ? 'always' : 'never' );
			}
			foreach ( array( 'display_email' => 'show_email', 'display_phone' => 'show_phone', 'disable_free' => 'disable_free' ) as $from => $to ) {
				if ( isset( $options[ $from ] ) ) {
					$add( $section, $to, $label . $from, $options[ $from ] ? 1 : 0, $options[ $from ] ? __( 'Yes', 'order-documents' ) : __( 'No', 'order-documents' ) );
				}
			}
			if ( ! empty( $options['display_date'] ) && $class::supports_numbering() ) {
				$add( $section, 'display_date', $label . __( 'Date shown', 'order-documents' ), 'order_date' === $options['display_date'] ? 'order' : 'document' );
			}
			if ( ! empty( $options['number_format'] ) && is_array( $options['number_format'] ) && $class::supports_numbering() ) {
				$nf = $options['number_format'];
				$add( $section, 'prefix', $label . __( 'Prefix', 'order-documents' ), self::convert_placeholders( self::text( $nf['prefix'] ?? '' ), $wpo['slug'] ) );
				$add( $section, 'suffix', $label . __( 'Suffix', 'order-documents' ), self::convert_placeholders( self::text( $nf['suffix'] ?? '' ), $wpo['slug'] ) );
				if ( isset( $nf['padding'] ) && '' !== $nf['padding'] ) {
					$add( $section, 'padding', $label . __( 'Padding', 'order-documents' ), absint( $nf['padding'] ) );
				}
			}
			if ( ! empty( $options['reset_number_yearly'] ) && $class::supports_numbering() ) {
				$add( $section, 'reset', $label . __( 'Restart numbering', 'order-documents' ), 'yearly', __( 'Every year', 'order-documents' ) );
			}
			if ( ! empty( $options['my_account_buttons'] ) ) {
				$value = array(
					'available' => 'created',
					'always'    => 'always',
					'never'     => 'never',
					'custom'    => 'statuses',
				)[ (string) $options['my_account_buttons'] ] ?? 'created';
				$add( $section, 'my_account', $label . __( 'My Account button', 'order-documents' ), $value );
				if ( 'statuses' === $value && ! empty( $options['my_account_restrict'] ) && is_array( $options['my_account_restrict'] ) ) {
					$add(
						$section,
						'my_account_statuses',
						$label . __( 'My Account statuses', 'order-documents' ),
						array_map(
							static function ( $s ) {
								return preg_replace( '/^wc-/', '', (string) $s );
							},
							array_keys( array_filter( $options['my_account_restrict'] ) )
						)
					);
				}
			}
			if ( ! empty( $options['due_date'] ) && ! empty( $options['due_date_days'] ) ) {
				$add( $section, 'due_days', $label . __( 'Due days', 'order-documents' ), absint( $options['due_date_days'] ) );
			}
		}
		return apply_filters( 'odocs_wpo_settings_map', $map );
	}

	private static function to_mm( string $value ): float {
		if ( ! preg_match( '/^\s*([\d.,]+)\s*(mm|cm|in|px|pt)?\s*$/i', $value, $m ) ) {
			return 0;
		}
		$number = (float) str_replace( ',', '.', $m[1] );
		switch ( strtolower( $m[2] ?? 'mm' ) ) {
			case 'cm':
				return round( $number * 10, 1 );
			case 'in':
				return round( $number * 25.4, 1 );
			case 'px':
				return round( $number * 25.4 / 96, 1 );
			case 'pt':
				return round( $number * 25.4 / 72, 1 );
			default:
				return round( $number, 1 );
		}
	}

	/**
	 * Saves the mapped settings.
	 *
	 * @param string[] $keys Only import these "section.key" entries (all when empty).
	 */
	public static function import_settings( array $keys = array() ): int {
		$by_section = array();
		foreach ( self::settings_map() as $entry ) {
			$id = $entry['section'] . '.' . $entry['key'];
			if ( $keys && ! in_array( $id, $keys, true ) ) {
				continue;
			}
			$by_section[ $entry['section'] ][ $entry['key'] ] = $entry['value'];
		}
		$count = 0;
		foreach ( $by_section as $section => $values ) {
			// Run through the normal sanitizer, keeping existing values for keys that are not imported.
			$merged = array_merge( Settings::section( $section ), $values );
			$clean  = Settings::sanitize( $section, $merged );
			update_option( Settings::option_name( $section ), $clean );
			$count += count( $values );
		}
		Settings::flush();
		self::update_state( array( 'settings_imported' => time() ) );
		return $count;
	}

	/**
	 * Sets next numbers per type.
	 *
	 * @param array<string,int> $next type => next number.
	 */
	public static function apply_sequences( array $next ): void {
		foreach ( $next as $type => $number ) {
			$class  = DocumentRegistry::get_class( (string) $type );
			$number = absint( $number );
			if ( $class && $number > 0 && $class::supports_numbering() ) {
				Sequence::set_next( $class::sequence_name_for(), $number );
			}
		}
		self::update_state( array( 'sequences_set' => time() ) );
	}

	public static function start_copy(): void {
		self::update_state(
			array(
				'copy_status'    => 'running',
				'copy_last_id'   => 0,
				'copy_processed' => 0,
				'copy_copied'    => 0,
				'copy_started'   => time(),
			)
		);
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::BATCH_HOOK, array(), 'order-documents' );
		} else {
			( new self() )->run_batch();
		}
	}

	/**
	 * Copies one batch of orders/refunds and schedules the next batch.
	 */
	public function run_batch(): void {
		global $wpdb;
		$state = self::state();
		if ( 'running' !== ( $state['copy_status'] ?? '' ) ) {
			return;
		}
		list( $table, $id_col ) = self::meta_table();
		$keys                   = array();
		foreach ( self::type_map() as $map ) {
			$keys[] = "_wcpdf_{$map['slug']}_date";
		}
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$ids          = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT {$id_col} FROM {$table} WHERE meta_key IN ({$placeholders}) AND {$id_col} > %d ORDER BY {$id_col} ASC LIMIT %d", array_merge( $keys, array( (int) ( $state['copy_last_id'] ?? 0 ), self::BATCH_SIZE ) ) ) ); // phpcs:ignore

		$copied = 0;
		foreach ( $ids as $id ) {
			$object = wc_get_order( (int) $id );
			if ( ! $object ) {
				continue;
			}
			foreach ( array_keys( self::type_map() ) as $type ) {
				$class = DocumentRegistry::get_class( $type );
				if ( ! $class ) {
					continue;
				}
				if ( $class::is_per_refund() !== ( $object instanceof \WC_Order_Refund ) ) {
					continue;
				}
				$document = DocumentRegistry::make( $type, $object );
				if ( $document && $document->is_legacy() ) {
					$document->adopt_legacy();
					++$copied;
				}
			}
		}

		$done = count( $ids ) < self::BATCH_SIZE;
		self::update_state(
			array(
				'copy_last_id'   => $ids ? (int) end( $ids ) : (int) ( $state['copy_last_id'] ?? 0 ),
				'copy_processed' => (int) ( $state['copy_processed'] ?? 0 ) + count( $ids ),
				'copy_copied'    => (int) ( $state['copy_copied'] ?? 0 ) + $copied,
				'copy_status'    => $done ? 'done' : 'running',
				'copy_finished'  => $done ? time() : null,
			)
		);
		if ( ! $done ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::BATCH_HOOK, array(), 'order-documents' );
			} else {
				$this->run_batch();
			}
		}
	}
}
