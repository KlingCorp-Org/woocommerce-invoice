<?php
/**
 * Base class for all order documents.
 *
 * Document data is stored as order meta (or refund meta for credit notes) through the WooCommerce CRUD
 * API, so it works with both HPOS and legacy post storage:
 *   _odocs_{type}         array: number, formatted_number, date (GMT timestamp), notes, trigger, created_by
 *   _odocs_{type}_number  formatted number (searchable)
 *   _odocs_{type}_seq     plain number (sortable)
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

use OrderDocs\Documents\Number\NumberFormatter;
use OrderDocs\Documents\Number\Sequence;
use OrderDocs\Migration\LegacyAdapter;
use OrderDocs\Settings\Settings;
use OrderDocs\Rendering\TemplateRegistry;

defined( 'ABSPATH' ) || exit;

abstract class Document {

	/** @var \WC_Order */
	protected $order;

	/** @var \WC_Order_Refund|null */
	protected $refund;

	/** @var array */
	protected $data = array();

	/** @var bool Data comes from WooCommerce PDF Invoices & Packing Slips and has not been copied yet. */
	protected $legacy = false;

	public function __construct( \WC_Order $order, ?\WC_Order_Refund $refund = null ) {
		$this->order  = $order;
		$this->refund = $refund;
		$this->load();
	}

	/* ------------------------------------------------------------------
	 * Type metadata (override in subclasses)
	 * ------------------------------------------------------------------ */

	abstract public static function type(): string;

	abstract public static function label(): string;

	public static function plural_label(): string {
		return static::label();
	}

	/** Dashicon used for buttons. */
	public static function icon(): string {
		return 'media-document';
	}

	/** Can documents of this type carry a sequential number? */
	public static function supports_numbering(): bool {
		return true;
	}

	/** Is numbering mandatory (invoices, credit notes)? */
	public static function numbering_forced(): bool {
		return false;
	}

	public static function default_numbered(): bool {
		return false;
	}

	public static function default_enabled(): bool {
		return false;
	}

	public static function default_archive(): bool {
		return false;
	}

	public static function shows_prices(): bool {
		return true;
	}

	/** Can be sent to the customer / attached to WooCommerce e-mails. */
	public static function supports_emails(): bool {
		return true;
	}

	public static function is_per_refund(): bool {
		return false;
	}

	/** Can be e-mailed automatically when the order reaches a status (one document per order). */
	public static function supports_auto_send(): bool {
		return static::supports_emails() && ! static::is_per_refund() && ! static::is_multi_order();
	}

	public static function is_multi_order(): bool {
		return false;
	}

	/** Slug of the matching document in WooCommerce PDF Invoices & Packing Slips, if any. */
	public static function legacy_slug(): ?string {
		return null;
	}

	public static function is_type_enabled(): bool {
		return (bool) Settings::doc( static::type(), 'enabled', static::default_enabled() );
	}

	/** Default value overrides for the shared settings fields. */
	protected static function field_defaults(): array {
		return array();
	}

	/** Type-specific settings fields, appended to the shared ones. */
	protected static function extra_settings_fields(): array {
		return array();
	}

	/**
	 * Settings fields for this document type.
	 */
	public static function settings_fields(): array {
		$d      = static::field_defaults();
		$def    = static function ( $key, $fallback ) use ( $d ) {
			return array_key_exists( $key, $d ) ? $d[ $key ] : $fallback;
		};
		$fields = array();

		$fields[] = array( 'type' => 'heading', 'label' => __( 'General', 'order-documents' ) );
		$fields[] = array( 'id' => 'enabled', 'type' => 'checkbox', 'label' => __( 'Enabled', 'order-documents' ), 'description' => sprintf( /* translators: %s: document type */ __( 'Show %s buttons and allow creating this document.', 'order-documents' ), static::label() ), 'default' => static::default_enabled() ? 1 : 0 );
		$fields[] = array( 'id' => 'title', 'type' => 'text', 'label' => __( 'Document title', 'order-documents' ), 'default' => '', 'placeholder' => static::label() );
		$fields[] = array(
			'id'      => 'template',
			'type'    => 'select',
			'label'   => __( 'Template', 'order-documents' ),
			'default' => $def( 'template', '' ),
			'options' => static function () {
				return array( '' => __( 'Use the default template', 'order-documents' ) ) + TemplateRegistry::options();
			},
		);
		$fields[] = array( 'id' => 'filename', 'type' => 'text', 'label' => __( 'File name', 'order-documents' ), 'default' => $def( 'filename', static::supports_numbering() && ( static::numbering_forced() || static::default_numbered() ) ? '{{document_title}}-{{document_number}}' : '{{document_title}}-{{order_number}}' ), 'description' => __( 'Without ".pdf". Placeholders such as {{document_number}}, {{order_number}} and {{document_title}} are supported.', 'order-documents' ) );

		if ( static::supports_numbering() ) {
			$fields[] = array( 'type' => 'heading', 'label' => __( 'Numbering', 'order-documents' ) );
			if ( ! static::numbering_forced() ) {
				$fields[] = array( 'id' => 'number_enabled', 'type' => 'checkbox', 'label' => __( 'Give this document a number', 'order-documents' ), 'default' => static::default_numbered() ? 1 : 0 );
			}
			$sequences = static::sequence_options();
			if ( count( $sequences ) > 1 ) {
				$fields[] = array( 'id' => 'sequence', 'type' => 'select', 'label' => __( 'Number sequence', 'order-documents' ), 'default' => $def( 'sequence', 'own' ), 'options' => $sequences );
			}
			$fields[] = array( 'id' => 'next_number', 'type' => 'sequence', 'label' => __( 'Next number', 'order-documents' ), 'description' => __( 'The number the next document gets. Change it only if you are sure, numbers must stay unique.', 'order-documents' ) );
			$fields[] = array( 'id' => 'prefix', 'type' => 'text', 'label' => __( 'Prefix', 'order-documents' ), 'default' => $def( 'prefix', '' ), 'description' => __( 'Supports {{year}}, {{month}}, {{day}}, {{order_year}}, {{order_month}} and {{order_number}}.', 'order-documents' ) );
			$fields[] = array( 'id' => 'suffix', 'type' => 'text', 'label' => __( 'Suffix', 'order-documents' ), 'default' => '' );
			$fields[] = array( 'id' => 'padding', 'type' => 'number', 'label' => __( 'Padding', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 20, 'description' => __( 'Minimum number of digits, for example 5 turns 42 into 00042.', 'order-documents' ) );
			$fields[] = array(
				'id'      => 'reset',
				'type'    => 'select',
				'label'   => __( 'Restart numbering', 'order-documents' ),
				'default' => 'never',
				'options' => array(
					'never'   => __( 'Never', 'order-documents' ),
					'yearly'  => __( 'Every year', 'order-documents' ),
					'monthly' => __( 'Every month', 'order-documents' ),
				),
			);
			$fields[] = array(
				'id'      => 'display_date',
				'type'    => 'select',
				'label'   => __( 'Date shown on the document', 'order-documents' ),
				'default' => 'document',
				'options' => array(
					'document' => __( 'Document date', 'order-documents' ),
					'order'    => __( 'Order date', 'order-documents' ),
				),
			);
		}

		$fields[] = array( 'type' => 'heading', 'label' => __( 'Content', 'order-documents' ) );
		$fields[] = array(
			'id'      => 'billing_address',
			'type'    => 'select',
			'label'   => __( 'Billing address', 'order-documents' ),
			'default' => $def( 'billing_address', 'always' ),
			'options' => array(
				'always' => __( 'Show', 'order-documents' ),
				'never'  => __( 'Hide', 'order-documents' ),
			),
		);
		$fields[] = array(
			'id'      => 'shipping_address',
			'type'    => 'select',
			'label'   => __( 'Shipping address', 'order-documents' ),
			'default' => $def( 'shipping_address', 'different' ),
			'options' => array(
				'always'    => __( 'Always show', 'order-documents' ),
				'different' => __( 'Only when different from the billing address', 'order-documents' ),
				'never'     => __( 'Hide', 'order-documents' ),
			),
		);
		$fields[] = array( 'id' => 'show_email', 'type' => 'checkbox', 'label' => __( 'Customer e-mail', 'order-documents' ), 'default' => $def( 'show_email', 1 ) );
		$fields[] = array( 'id' => 'show_phone', 'type' => 'checkbox', 'label' => __( 'Customer phone', 'order-documents' ), 'default' => $def( 'show_phone', 1 ) );
		$fields[] = array( 'id' => 'show_sku', 'type' => 'checkbox', 'label' => __( 'SKU under the product name', 'order-documents' ), 'default' => $def( 'show_sku', 1 ) );
		$fields[] = array( 'id' => 'show_weight', 'type' => 'checkbox', 'label' => __( 'Weight under the product name', 'order-documents' ), 'default' => $def( 'show_weight', 0 ) );
		$fields[] = array( 'id' => 'show_item_meta', 'type' => 'checkbox', 'label' => __( 'Product options and variation details', 'order-documents' ), 'default' => $def( 'show_item_meta', 1 ) );
		if ( static::shows_prices() ) {
			$fields[] = array(
				'id'      => 'prices',
				'type'    => 'select',
				'label'   => __( 'Prices', 'order-documents' ),
				'default' => $def( 'prices', 'excl' ),
				'options' => array(
					'excl' => __( 'Excluding tax, with tax lines in the totals', 'order-documents' ),
					'incl' => __( 'Including tax', 'order-documents' ),
				),
			);
		}

		if ( static::supports_emails() ) {
			$fields[] = array( 'type' => 'heading', 'label' => __( 'Automation', 'order-documents' ) );
			$fields[] = array( 'id' => 'attach_to_emails', 'type' => 'emails', 'label' => __( 'Attach to e-mails', 'order-documents' ), 'default' => $def( 'attach_to_emails', array() ) );
			$fields[] = array( 'id' => 'auto_create_statuses', 'type' => 'statuses', 'label' => __( 'Create automatically when the order status becomes', 'order-documents' ), 'default' => $def( 'auto_create_statuses', array() ) );
			if ( static::supports_auto_send() ) {
				$fields[] = array( 'id' => 'email_customer_statuses', 'type' => 'statuses', 'label' => __( 'E-mail to the customer when the order status becomes', 'order-documents' ), 'description' => __( 'Sends the PDF in its own e-mail to the customer, once per order.', 'order-documents' ), 'default' => array() );
				$fields[] = array( 'id' => 'email_address_statuses', 'type' => 'statuses', 'label' => __( 'E-mail to a fixed address when the order status becomes', 'order-documents' ), 'description' => __( 'Sends the PDF to the address below, once per order.', 'order-documents' ), 'default' => array() );
				$fields[] = array( 'id' => 'email_address', 'type' => 'email_list', 'label' => __( 'Fixed e-mail address', 'order-documents' ), 'default' => '', 'description' => __( 'For example the company that handles your invoice payments. Separate several addresses with commas.', 'order-documents' ) );
			}
		}

		$fields[] = array( 'type' => 'heading', 'label' => __( 'Restrictions', 'order-documents' ) );
		$fields[] = array( 'id' => 'disable_for_statuses', 'type' => 'statuses', 'label' => __( 'Not available for orders with status', 'order-documents' ), 'default' => $def( 'disable_for_statuses', array() ) );
		$fields[] = array( 'id' => 'disable_free', 'type' => 'checkbox', 'label' => __( 'Not available for free orders', 'order-documents' ), 'default' => 0 );
		$fields[] = array( 'id' => 'exclude_payment_methods', 'type' => 'payment_methods', 'label' => __( 'Not available for payment methods', 'order-documents' ), 'default' => array() );

		if ( static::supports_emails() ) {
			$fields[] = array( 'type' => 'heading', 'label' => __( 'My Account', 'order-documents' ) );
			$fields[] = array(
				'id'      => 'my_account',
				'type'    => 'select',
				'label'   => __( 'Download button for customers', 'order-documents' ),
				'default' => $def( 'my_account', 'never' ),
				'options' => array(
					'never'    => __( 'Never', 'order-documents' ),
					'created'  => __( 'Only when the document has been created', 'order-documents' ),
					'always'   => __( 'Always (created on demand)', 'order-documents' ),
					'statuses' => __( 'For the order statuses below', 'order-documents' ),
				),
			);
			$fields[] = array( 'id' => 'my_account_statuses', 'type' => 'statuses', 'label' => __( 'Order statuses', 'order-documents' ), 'default' => array( 'completed' ) );
		}

		$fields[] = array( 'type' => 'heading', 'label' => __( 'Archive', 'order-documents' ) );
		$fields[] = array( 'id' => 'archive', 'type' => 'checkbox', 'label' => __( 'Keep an archived copy', 'order-documents' ), 'description' => __( 'Saves the PDF the first time it is generated and always serves that copy afterwards, so the document never changes. Recommended for invoices and credit notes.', 'order-documents' ), 'default' => static::default_archive() ? 1 : 0 );

		return array_merge( $fields, static::extra_settings_fields() );
	}

	/**
	 * Number sequences this type may use.
	 */
	protected static function sequence_options(): array {
		return array( 'own' => __( 'Own sequence', 'order-documents' ) );
	}

	/* ------------------------------------------------------------------
	 * Instance
	 * ------------------------------------------------------------------ */

	public function get_type(): string {
		return static::type();
	}

	public function get_order(): \WC_Order {
		return $this->order;
	}

	public function get_refund(): ?\WC_Order_Refund {
		return $this->refund;
	}

	/** The object that stores this document's meta. */
	public function storage(): \WC_Abstract_Order {
		return $this->refund ?: $this->order;
	}

	public function settings(): array {
		return Settings::section( 'doc_' . static::type() );
	}

	/**
	 * @return mixed
	 */
	public function setting( string $key, $fallback = null ) {
		$settings = $this->settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	public function meta_key(): string {
		return '_odocs_' . static::type();
	}

	protected function load(): void {
		$data = $this->storage()->get_meta( $this->meta_key() );
		if ( is_array( $data ) && ! empty( $data ) ) {
			$this->data = $data;
			return;
		}
		$this->data = array();
		if ( static::legacy_slug() && Settings::get( 'advanced', 'legacy_fallback', 1 ) && ! $this->storage()->get_meta( $this->meta_key() . '_legacy_deleted' ) ) {
			$legacy = LegacyAdapter::read( $this->storage(), static::legacy_slug() );
			if ( $legacy ) {
				$this->data   = $legacy;
				$this->legacy = true;
			}
		}
	}

	public function is_legacy(): bool {
		return $this->legacy;
	}

	public function get_data(): array {
		return $this->data;
	}

	public function exists(): bool {
		return ! empty( $this->data['date'] );
	}

	public function is_numbered(): bool {
		if ( ! static::supports_numbering() ) {
			return false;
		}
		return static::numbering_forced() || (bool) $this->setting( 'number_enabled', static::default_numbered() );
	}

	public function get_title(): string {
		$title = trim( (string) $this->setting( 'title', '' ) );
		return apply_filters( 'odocs_document_title', '' !== $title ? $title : static::label(), $this );
	}

	public function get_number(): string {
		return (string) ( $this->data['formatted_number'] ?? '' );
	}

	public function get_plain_number(): ?int {
		return isset( $this->data['number'] ) && '' !== $this->data['number'] ? (int) $this->data['number'] : null;
	}

	public function get_date(): ?\WC_DateTime {
		if ( empty( $this->data['date'] ) ) {
			return null;
		}
		$date = new \WC_DateTime( '@' . (int) $this->data['date'] );
		$date->setTimezone( wp_timezone() );
		return $date;
	}

	/** Date printed on the document (document date or order date, per settings). */
	public function get_display_date(): ?\WC_DateTime {
		if ( 'order' === $this->setting( 'display_date', 'document' ) ) {
			return $this->order->get_date_created();
		}
		return $this->get_date() ?: new \WC_DateTime( 'now', wp_timezone() );
	}

	public function get_notes(): string {
		return (string) ( $this->data['notes'] ?? '' );
	}

	/** Unique key for this document instance (type + order/refund id). */
	public function get_key(): string {
		return static::type() . '-' . $this->storage()->get_id();
	}

	public function get_template_id(): string {
		$template = (string) $this->setting( 'template', '' );
		if ( '' === $template || ! TemplateRegistry::get( $template ) ) {
			$template = (string) Settings::get( 'design', 'template', 'classic' );
		}
		return TemplateRegistry::get( $template ) ? $template : 'classic';
	}

	public function get_filename(): string {
		$pattern = (string) $this->setting( 'filename', '{{document_title}}-{{order_number}}' );
		$name    = strtr(
			$pattern,
			array(
				'{{document_title}}'  => $this->get_title(),
				'{{document_type}}'   => static::type(),
				'{{document_number}}' => $this->get_number() ?: $this->order->get_order_number(),
				'{{order_number}}'    => $this->order->get_order_number(),
				'{{date}}'            => ( $this->get_date() ?: new \WC_DateTime() )->date( 'Y-m-d' ),
			)
		);
		$name = sanitize_file_name( remove_accents( strtolower( $name ) ) );
		return apply_filters( 'odocs_filename', ( $name ?: static::type() ) . '.pdf', $this );
	}

	/**
	 * Can this document be created now? Existing documents can always be opened.
	 *
	 * @param string|null $reason Set to a human readable reason when not allowed.
	 */
	public function is_allowed( ?string &$reason = null ): bool {
		if ( $this->exists() ) {
			return true;
		}
		$allowed = true;
		if ( ! static::is_type_enabled() ) {
			$reason  = __( 'This document type is disabled.', 'order-documents' );
			$allowed = false;
		} elseif ( in_array( $this->order->get_status(), (array) $this->setting( 'disable_for_statuses', array() ), true ) ) {
			$reason  = __( 'Not available for this order status.', 'order-documents' );
			$allowed = false;
		} elseif ( $this->setting( 'disable_free' ) && (float) $this->order->get_total() <= 0 ) {
			$reason  = __( 'Not available for free orders.', 'order-documents' );
			$allowed = false;
		} elseif ( in_array( $this->order->get_payment_method(), (array) $this->setting( 'exclude_payment_methods', array() ), true ) ) {
			$reason  = __( 'Not available for this payment method.', 'order-documents' );
			$allowed = false;
		} else {
			$allowed = $this->type_allows( $reason );
		}
		return (bool) apply_filters( 'odocs_document_allowed', $allowed, $this );
	}

	/** Extra type-specific checks. */
	protected function type_allows( ?string &$reason = null ): bool {
		return true;
	}

	/**
	 * Is the document available to the customer in My Account and through guest links?
	 */
	public function is_visible_to_customer(): bool {
		if ( ! static::supports_emails() || ! static::is_type_enabled() ) {
			return false;
		}
		switch ( $this->setting( 'my_account', 'never' ) ) {
			case 'created':
				$visible = $this->exists();
				break;
			case 'always':
				$visible = $this->is_allowed();
				break;
			case 'statuses':
				$visible = in_array( $this->order->get_status(), (array) $this->setting( 'my_account_statuses', array() ), true ) && $this->is_allowed();
				break;
			default:
				$visible = false;
		}
		return (bool) apply_filters( 'odocs_document_visible_to_customer', $visible, $this );
	}

	/**
	 * Creates the document if it doesn't exist yet. Returns false when not allowed.
	 */
	public function ensure( string $trigger = 'manual' ): bool {
		if ( $this->exists() ) {
			if ( $this->legacy ) {
				$this->adopt_legacy();
			}
			return true;
		}
		if ( ! $this->is_allowed() ) {
			return false;
		}
		$this->create( $trigger );
		return true;
	}

	/**
	 * Creates the document: assigns its date and (when numbered) its number.
	 */
	public function create( string $trigger = 'manual', ?int $timestamp = null ): void {
		if ( $this->exists() ) {
			return;
		}
		global $wpdb;
		// Serialise creation per document, so double clicks or parallel e-mails can't claim two numbers.
		$lock   = 'odocs_' . md5( $wpdb->prefix . $this->get_key() );
		$locked = (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 15)', $lock ) );
		try {
			// Re-read meta in case another request created the document in the meantime.
			$fresh = wc_get_order( $this->storage()->get_id() );
			if ( $fresh && $fresh->get_id() ) {
				if ( is_callable( array( $fresh, 'read_meta_data' ) ) ) {
					$fresh->read_meta_data( true );
				}
				$existing = $fresh->get_meta( $this->meta_key() );
				if ( is_array( $existing ) && ! empty( $existing['date'] ) ) {
					$this->data = $existing;
					return;
				}
			}

			$timestamp  = $timestamp ?: time();
			$this->data = array(
				'date'       => $timestamp,
				'notes'      => '',
				'trigger'    => $trigger,
				'created_by' => get_current_user_id(),
			);

			if ( $this->is_numbered() ) {
				$this->assign_number( $timestamp );
			}

			do_action( 'odocs_before_document_created', $this );
			$this->save();
		} finally {
			if ( $locked ) {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
		}
		do_action( 'odocs_document_created', $this, $trigger );
	}

	/**
	 * Claims a number from the configured sequence and stores it.
	 */
	protected function assign_number( int $timestamp ): void {
		$date   = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() );
		$number = Sequence::claim( $this->sequence_name( $date ) );
		$this->set_number( $number );
	}

	/**
	 * Sequence row name for this document, honouring shared sequences and resets.
	 */
	public function sequence_name( ?\DateTimeInterface $date = null ): string {
		return static::sequence_name_for( $date );
	}

	/**
	 * Sequence row name for this type from its current settings.
	 */
	public static function sequence_name_for( ?\DateTimeInterface $date = null ): string {
		$type  = static::type();
		$base  = $type;
		$reset = (string) Settings::doc( $type, 'reset', 'never' );
		if ( 'invoice' === Settings::doc( $type, 'sequence', 'own' ) && 'invoice' !== $type ) {
			$base  = 'invoice';
			$reset = (string) Settings::doc( 'invoice', 'reset', 'never' );
		}
		return Sequence::name( $base, $reset, $date );
	}

	/**
	 * Sets the plain number and refreshes the formatted number.
	 */
	public function set_number( int $number ): void {
		$this->data['number']           = $number;
		$this->data['formatted_number'] = NumberFormatter::format(
			$number,
			array(
				'prefix'  => $this->setting( 'prefix', '' ),
				'suffix'  => $this->setting( 'suffix', '' ),
				'padding' => $this->setting( 'padding', 0 ),
			),
			$this->get_date(),
			$this->storage()
		);
	}

	public function set_formatted_number( string $formatted ): void {
		$this->data['formatted_number'] = $formatted;
	}

	public function set_date( int $timestamp ): void {
		$this->data['date'] = $timestamp;
	}

	public function set_notes( string $notes ): void {
		$this->data['notes'] = $notes;
	}

	/**
	 * Has this document already been e-mailed automatically to a target ('customer' or 'address')?
	 */
	public function was_emailed( string $target ): bool {
		return ! empty( $this->data['emailed'][ $target ] );
	}

	/**
	 * Remembers that the document was e-mailed automatically to a target.
	 */
	public function mark_emailed( string $target ): void {
		$emailed              = isset( $this->data['emailed'] ) && is_array( $this->data['emailed'] ) ? $this->data['emailed'] : array();
		$emailed[ $target ]   = time();
		$this->data['emailed'] = $emailed;
		$this->save();
	}

	/**
	 * Copies legacy (WPO) data into this plugin's meta.
	 */
	public function adopt_legacy(): void {
		if ( ! $this->legacy ) {
			return;
		}
		$this->data['trigger'] = $this->data['trigger'] ?? 'wpo_import';
		$this->save();
	}

	public function save(): void {
		$storage = $this->storage();
		$data    = $this->data;
		unset( $data['legacy'] );
		$storage->update_meta_data( $this->meta_key(), $data );
		if ( ! empty( $data['formatted_number'] ) ) {
			$storage->update_meta_data( $this->meta_key() . '_number', $data['formatted_number'] );
		}
		if ( isset( $data['number'] ) ) {
			$storage->update_meta_data( $this->meta_key() . '_seq', (int) $data['number'] );
		}
		$storage->save_meta_data();
		$this->legacy = false;
		do_action( 'odocs_document_saved', $this );
	}

	/**
	 * Deletes the document data (not the number sequence; numbers are never reused automatically).
	 */
	public function delete(): void {
		$storage = $this->storage();
		Archive::delete( $this );
		$storage->delete_meta_data( $this->meta_key() );
		$storage->delete_meta_data( $this->meta_key() . '_number' );
		$storage->delete_meta_data( $this->meta_key() . '_seq' );
		if ( static::legacy_slug() && LegacyAdapter::read( $storage, static::legacy_slug() ) ) {
			// Keep the old plugin's data untouched, but stop using it for this order.
			$storage->update_meta_data( $this->meta_key() . '_legacy_deleted', 1 );
		}
		$storage->save_meta_data();
		$this->data   = array();
		$this->legacy = false;
		do_action( 'odocs_document_deleted', $this );
	}

	/**
	 * Rows for the "document data" block: label => value.
	 *
	 * @return array<string,array{label:string,value:string}>
	 */
	public function data_rows(): array {
		$rows = array();
		if ( $this->is_numbered() && $this->get_number() ) {
			$rows['number'] = array(
				/* translators: %s: document title, e.g. Invoice */
				'label' => sprintf( __( '%s number', 'order-documents' ), $this->get_title() ),
				'value' => $this->get_number(),
			);
		}
		$rows['date'] = array(
			/* translators: %s: document title, e.g. Invoice */
			'label' => sprintf( __( '%s date', 'order-documents' ), $this->get_title() ),
			'value' => odocs_format_date( $this->get_display_date() ),
		);
		return $rows;
	}
}
