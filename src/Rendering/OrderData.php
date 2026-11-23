<?php
/**
 * View model for a document: shop details, addresses, item lines, totals and VAT summary.
 * Every amount shown on a document is formatted here, so all templates agree.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

use OrderDocs\Documents\Document;
use OrderDocs\Documents\PickingList;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class OrderData {

	/** @var Document */
	protected $document;

	/** @var \WC_Order */
	protected $order;

	/** @var \WC_Abstract_Order Order or refund whose items and totals are shown. */
	protected $source;

	/** @var string pdf|html */
	protected $mode;

	/** @var array|null */
	private $items_cache = null;

	public function __construct( Document $document, string $mode = 'pdf' ) {
		$this->document = $document;
		$this->order    = $document->get_order();
		$this->source   = $document->get_refund() ?: $document->get_order();
		$this->mode     = $mode;
	}

	public function document(): Document {
		return $this->document;
	}

	public function order(): \WC_Order {
		return $this->order;
	}

	public function is_refund(): bool {
		return $this->source instanceof \WC_Order_Refund;
	}

	public function prices_incl_tax(): bool {
		return 'incl' === $this->document->setting( 'prices', 'excl' );
	}

	/* ------------------------------------------------------------------
	 * Formatting
	 * ------------------------------------------------------------------ */

	/**
	 * Formats an amount. Credit notes show negative amounts unless configured otherwise.
	 */
	public function money( $amount ): string {
		$amount = (float) $amount;
		if ( $this->is_refund() ) {
			$amount = $this->document->setting( 'negative_amounts', 1 ) ? -abs( $amount ) : abs( $amount );
		}
		$html = wc_price( $amount, array( 'currency' => $this->order->get_currency() ) );
		return apply_filters( 'odocs_money', $html, $amount, $this->document );
	}

	public function quantity( $qty ): string {
		$qty = (float) $qty;
		if ( $this->is_refund() ) {
			$qty = abs( $qty );
		}
		return wc_stock_amount( $qty ) == $qty ? (string) wc_stock_amount( $qty ) : wc_format_localized_decimal( $qty ); // phpcs:ignore WordPress.PHP.StrictComparisons
	}

	/* ------------------------------------------------------------------
	 * Shop
	 * ------------------------------------------------------------------ */

	public function shop(): array {
		$general = Settings::section( 'general' );
		$shop    = array();
		foreach ( array( 'shop_name', 'shop_phone', 'shop_email', 'shop_website', 'org_number', 'vat_number', 'register_text' ) as $key ) {
			$shop[ $key ] = trim( (string) ( $general[ $key ] ?? '' ) );
		}
		foreach ( array( 'shop_address', 'extra_1', 'extra_2', 'extra_3', 'footer' ) as $key ) {
			$text         = Placeholders::replace( trim( (string) ( $general[ $key ] ?? '' ) ), $this->document );
			$shop[ $key ] = odocs_autop( $text );
		}
		return apply_filters( 'odocs_shop_data', $shop, $this->document );
	}

	/**
	 * @return array{src:string,width:int,height:int}|null
	 */
	public function logo(): ?array {
		$id = (int) Settings::get( 'general', 'logo_id', 0 );
		if ( ! $id ) {
			return null;
		}
		$image = $this->image( $id, 'medium_large' );
		return apply_filters( 'odocs_logo', $image, $this->document );
	}

	/**
	 * Image source for an attachment: local file path in PDF mode, URL in HTML mode.
	 *
	 * @return array{src:string,width:int,height:int}|null
	 */
	public function image( int $attachment_id, string $size = 'thumbnail' ): ?array {
		if ( ! $attachment_id ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $attachment_id, $size );
		if ( ! $src ) {
			return null;
		}
		if ( 'pdf' !== $this->mode ) {
			return array( 'src' => $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2] );
		}
		$path = self::local_path( $attachment_id, $size );
		if ( ! $path ) {
			return null;
		}
		return array( 'src' => $path, 'width' => (int) $src[1], 'height' => (int) $src[2] );
	}

	public static function local_path( int $attachment_id, string $size ): ?string {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return null;
		}
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( 'full' !== $size && ! empty( $meta['sizes'][ $size ]['file'] ) ) {
			$candidate = trailingslashit( dirname( $file ) ) . $meta['sizes'][ $size ]['file'];
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}
		// Dompdf can't read WebP/AVIF reliably everywhere; fall back to the original only for common formats.
		return is_readable( $file ) && preg_match( '/\.(jpe?g|png|gif|webp)$/i', $file ) ? $file : null;
	}

	/* ------------------------------------------------------------------
	 * Customer
	 * ------------------------------------------------------------------ */

	public function billing_address(): string {
		if ( 'never' === $this->document->setting( 'billing_address', 'always' ) ) {
			return '';
		}
		$address = $this->order->get_formatted_billing_address();
		return (string) apply_filters( 'odocs_billing_address', $address, $this->document );
	}

	public function shipping_address( bool $fallback_to_billing = false ): string {
		$mode = $this->document->setting( 'shipping_address', 'different' );
		if ( $fallback_to_billing && 'never' !== $mode ) {
			$shipping = $this->order->has_shipping_address() ? $this->order->get_formatted_shipping_address() : '';
			if ( '' === trim( wp_strip_all_tags( $shipping ) ) ) {
				$shipping = $this->order->get_formatted_billing_address();
			}
			return (string) apply_filters( 'odocs_shipping_address', $shipping, $this->document );
		}
		if ( 'never' === $mode || ! $this->order->has_shipping_address() ) {
			return '';
		}
		$shipping = $this->order->get_formatted_shipping_address();
		if ( 'different' === $mode && $this->normalize( $shipping ) === $this->normalize( $this->order->get_formatted_billing_address() ) ) {
			return '';
		}
		return (string) apply_filters( 'odocs_shipping_address', $shipping, $this->document );
	}

	/**
	 * Compact address HTML: first line in bold, the remaining lines joined with commas.
	 */
	public static function compact_address( string $address ): string {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '#<br\s*/?>#i', $address ) ), 'strlen' ) );
		if ( ! $lines ) {
			return '';
		}
		$first = array_shift( $lines );
		$html  = '<strong class="odocs-address-name">' . $first . '</strong>';
		if ( $lines ) {
			$html .= '<br>' . implode( ', ', $lines );
		}
		return $html;
	}

	private function normalize( string $address ): string {
		return strtolower( preg_replace( '/\s+/', '', wp_strip_all_tags( str_replace( '<br/>', ' ', $address ) ) ) );
	}

	public function email(): string {
		return $this->document->setting( 'show_email', 1 ) ? (string) $this->order->get_billing_email() : '';
	}

	public function phone(): string {
		return $this->document->setting( 'show_phone', 1 ) ? (string) $this->order->get_billing_phone() : '';
	}

	public function shipping_phone(): string {
		return $this->document->setting( 'show_phone', 1 ) && is_callable( array( $this->order, 'get_shipping_phone' ) ) ? (string) $this->order->get_shipping_phone() : '';
	}

	public function customer_note(): string {
		$note = $this->order->get_customer_note();
		return $note ? odocs_autop( esc_html( $note ) ) : '';
	}

	public function document_notes(): string {
		$notes = $this->document->get_notes();
		return $notes ? odocs_autop( wp_kses_post( $notes ) ) : '';
	}

	public function payment_info(): string {
		$text = trim( (string) $this->document->setting( 'payment_info', '' ) );
		return $text ? odocs_autop( Placeholders::replace( wp_kses_post( $text ), $this->document ) ) : '';
	}

	public function notice(): string {
		$text = trim( (string) $this->document->setting( 'disclaimer', '' ) );
		return $text ? odocs_autop( Placeholders::replace( wp_kses_post( $text ), $this->document ) ) : '';
	}

	/**
	 * Rows for the document data block.
	 *
	 * @return array<string,array{label:string,value:string,group:string}>
	 */
	public function data_rows(): array {
		$rows = array();
		foreach ( $this->document->data_rows() as $key => $row ) {
			$group        = in_array( $key, array( 'number', 'date' ), true ) ? $key : 'other';
			$rows[ $key ] = array_merge( $row, array( 'group' => $group ) );
		}
		if ( ! $this->document instanceof PickingList ) {
			$rows['order_number'] = array( 'label' => __( 'Order number', 'order-documents' ), 'value' => (string) $this->order->get_order_number(), 'group' => 'order_number' );
			$rows['order_date']   = array( 'label' => __( 'Order date', 'order-documents' ), 'value' => odocs_format_date( $this->order->get_date_created() ), 'group' => 'order_date' );
			if ( $this->order->get_payment_method_title() ) {
				$rows['payment_method'] = array( 'label' => __( 'Payment method', 'order-documents' ), 'value' => $this->order->get_payment_method_title(), 'group' => 'payment_method' );
			}
			if ( $this->order->get_shipping_method() ) {
				$rows['shipping_method'] = array( 'label' => __( 'Shipping method', 'order-documents' ), 'value' => $this->order->get_shipping_method(), 'group' => 'shipping_method' );
			}
		}
		// Put document number/date first, then order data, then the rest.
		$order = array( 'number' => 0, 'date' => 1, 'order_number' => 2, 'order_date' => 3, 'payment_method' => 4, 'shipping_method' => 5 );
		uksort(
			$rows,
			static function ( $a, $b ) use ( $order ) {
				return ( $order[ $a ] ?? 10 ) <=> ( $order[ $b ] ?? 10 );
			}
		);
		return apply_filters( 'odocs_data_rows', $rows, $this->document );
	}

	/* ------------------------------------------------------------------
	 * Items
	 * ------------------------------------------------------------------ */

	/**
	 * Item lines. Each row has raw and formatted values for every column type.
	 */
	public function items(): array {
		if ( null !== $this->items_cache ) {
			return $this->items_cache;
		}
		if ( $this->document instanceof PickingList ) {
			$rows = $this->picking_items();
		} else {
			$rows = array();
			foreach ( $this->source->get_items( 'line_item' ) as $item_id => $item ) {
				$rows[] = $this->item_row( $item, $this->source );
			}
			if ( ! $rows && $this->is_refund() ) {
				// Amount-only refund: show a single line.
				$reason = $this->source->get_reason();
				$amount = (float) $this->source->get_amount();
				$rows[] = $this->empty_row(
					array(
						'name'       => $reason ? $reason : __( 'Refund', 'order-documents' ),
						'qty'        => 1,
						'quantity'   => '1',
						'unit_price' => $this->money( $amount ),
						'total'      => $this->money( $amount ),
						'total_incl' => $this->money( $amount ),
					)
				);
			}
		}
		foreach ( $rows as $index => &$row ) {
			$row['position'] = (string) ( $index + 1 );
		}
		unset( $row );
		$this->items_cache = apply_filters( 'odocs_items', $rows, $this->document );
		return $this->items_cache;
	}

	/** Sum of all item quantities. */
	public function total_quantity(): float {
		$total = 0.0;
		foreach ( $this->items() as $row ) {
			$total += abs( (float) $row['qty'] );
		}
		return $total;
	}

	/** Total product weight of all items, in the store's weight unit. */
	public function total_weight(): float {
		$total = 0.0;
		foreach ( $this->items() as $row ) {
			$product = ! empty( $row['product_id'] ) ? wc_get_product( $row['product_id'] ) : null;
			if ( $product && $product->get_weight() ) {
				$total += (float) $product->get_weight() * abs( (float) $row['qty'] );
			}
		}
		return $total;
	}

	private function empty_row( array $values = array() ): array {
		$keys = array( 'name', 'sku', 'meta_html', 'description', 'qty', 'quantity', 'weight', 'image', 'unit_price', 'unit_price_excl', 'unit_price_incl', 'discount', 'tax_rate', 'tax', 'total', 'total_excl', 'total_incl', 'location', 'orders', 'checkbox', 'product_id', 'category' );
		$row  = array_fill_keys( $keys, '' );
		$row['checkbox'] = '&#9744;';
		return array_merge( $row, $values );
	}

	/**
	 * @param \WC_Order_Item_Product $item   Item.
	 * @param \WC_Abstract_Order     $parent Order or refund.
	 */
	protected function item_row( $item, $parent ): array {
		$product   = $item->get_product();
		$qty       = (float) $item->get_quantity();
		$abs_qty   = abs( $qty ) ?: 1;
		$subtotal  = (float) $item->get_subtotal();
		$sub_tax   = (float) $item->get_subtotal_tax();
		$total     = (float) $item->get_total();
		$total_tax = (float) $item->get_total_tax();
		$incl      = $this->prices_incl_tax();
		$sku       = $product ? (string) $product->get_sku() : '';
		$weight    = $product && $product->get_weight() ? (float) $product->get_weight() * $abs_qty : 0;

		$row = $this->empty_row(
			array(
				'item'            => $item,
				'product_id'      => $product ? $product->get_id() : 0,
				'name'            => $item->get_name(),
				'sku'             => esc_html( $sku ),
				'qty'             => $qty,
				'quantity'        => $this->quantity( $qty ),
				'weight'          => $weight ? esc_html( wc_format_weight( $weight ) ) : '',
				'meta_html'       => $this->document->setting( 'show_item_meta', 1 ) ? $this->item_meta_html( $item ) : '',
				'unit_price'      => $this->money( ( $incl ? $subtotal + $sub_tax : $subtotal ) / $abs_qty ),
				'unit_price_excl' => $this->money( $subtotal / $abs_qty ),
				'unit_price_incl' => $this->money( ( $subtotal + $sub_tax ) / $abs_qty ),
				'discount'        => abs( $subtotal - $total ) > 0.0001 ? $this->money( $incl ? ( $subtotal + $sub_tax ) - ( $total + $total_tax ) : $subtotal - $total ) : '',
				'tax_rate'        => $this->tax_rate_label( $item ),
				'tax'             => $this->money( $sub_tax ),
				'total'           => $this->money( $incl ? $subtotal + $sub_tax : $subtotal ),
				'total_excl'      => $this->money( $subtotal ),
				'total_incl'      => $this->money( $subtotal + $sub_tax ),
				'location'        => esc_html( $this->location( $product ) ),
				'category'        => $product ? $this->category( $product ) : '',
			)
		);
		$row['image']       = $product && $product->get_image_id() ? $this->image( (int) $product->get_image_id() ) : null;
		$row['description'] = $this->description_html( $row );
		return apply_filters( 'odocs_item_row', $row, $item, $this->document );
	}

	/**
	 * Product name with SKU, weight and item meta below it.
	 */
	public function description_html( array $row ): string {
		$html = '<span class="odocs-item-name">' . esc_html( wp_strip_all_tags( (string) $row['name'] ) ) . '</span>';
		$details = array();
		if ( $this->document->setting( 'show_sku', 1 ) && '' !== $row['sku'] ) {
			/* translators: %s: SKU */
			$details[] = sprintf( __( 'SKU: %s', 'order-documents' ), $row['sku'] );
		}
		if ( $this->document->setting( 'show_weight', 0 ) && '' !== $row['weight'] ) {
			/* translators: %s: weight */
			$details[] = sprintf( __( 'Weight: %s', 'order-documents' ), $row['weight'] );
		}
		if ( $details ) {
			$html .= '<div class="odocs-item-details">' . implode( ' &middot; ', $details ) . '</div>';
		}
		if ( '' !== $row['meta_html'] ) {
			$html .= '<div class="odocs-item-meta">' . $row['meta_html'] . '</div>';
		}
		return $html;
	}

	/**
	 * @param \WC_Order_Item_Product $item Item.
	 */
	protected function item_meta_html( $item ): string {
		$lines = array();
		foreach ( $item->get_all_formatted_meta_data( '_' ) as $meta ) {
			$lines[] = '<span class="odocs-meta-key">' . wp_kses_post( $meta->display_key ) . ':</span> ' . wp_kses_post( wp_strip_all_tags( $meta->display_value, true ) );
		}
		return implode( '<br>', $lines );
	}

	/**
	 * VAT rate(s) of an item, e.g. "25 %".
	 *
	 * @param \WC_Order_Item $item Item.
	 */
	public function tax_rate_label( $item ): string {
		$taxes = $item->get_taxes();
		$rates = array();
		foreach ( (array) ( $taxes['total'] ?? array() ) as $rate_id => $amount ) {
			if ( '' === $amount || null === $amount ) {
				continue;
			}
			$rates[] = $this->rate_percent( (int) $rate_id );
		}
		if ( ! $rates ) {
			return wc_tax_enabled() ? $this->format_percent( 0 ) : '';
		}
		return implode( ', ', array_unique( $rates ) );
	}

	private function rate_percent( int $rate_id ): string {
		$percent = null;
		foreach ( $this->order->get_items( 'tax' ) as $tax_item ) {
			if ( (int) $tax_item->get_rate_id() === $rate_id && is_callable( array( $tax_item, 'get_rate_percent' ) ) && null !== $tax_item->get_rate_percent() ) {
				$percent = (float) $tax_item->get_rate_percent();
				break;
			}
		}
		if ( null === $percent ) {
			$percent = (float) str_replace( '%', '', \WC_Tax::get_rate_percent_value( $rate_id ) );
		}
		return $this->format_percent( $percent );
	}

	private function rate_label( int $rate_id ): string {
		foreach ( $this->order->get_items( 'tax' ) as $tax_item ) {
			if ( (int) $tax_item->get_rate_id() === $rate_id && $tax_item->get_label() ) {
				return $tax_item->get_label();
			}
		}
		return \WC_Tax::get_rate_label( $rate_id ) ?: __( 'VAT', 'order-documents' );
	}

	private function format_percent( float $percent ): string {
		$formatted = rtrim( rtrim( number_format( $percent, 2, wc_get_price_decimal_separator(), '' ), '0' ), wc_get_price_decimal_separator() );
		return $formatted . ' %';
	}

	/**
	 * @param \WC_Product|false|null $product Product.
	 */
	protected function location( $product ): string {
		$key = trim( (string) $this->document->setting( 'location_meta', '' ) );
		if ( ! $product || '' === $key ) {
			return '';
		}
		$value = $product->get_meta( $key );
		if ( ( '' === $value || null === $value ) && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$value  = $parent ? $parent->get_meta( $key ) : '';
		}
		if ( ( '' === $value || null === $value ) && taxonomy_exists( $key ) ) {
			$value = $product->get_attribute( $key );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param \WC_Product $product Product.
	 */
	protected function category( $product ): string {
		$id    = $product->get_parent_id() ?: $product->get_id();
		$terms = get_the_terms( $id, 'product_cat' );
		return $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';
	}

	/**
	 * Picking list rows: all orders, optionally combining identical products.
	 */
	protected function picking_items(): array {
		$doc       = $this->document;
		$orders    = $doc instanceof PickingList ? $doc->get_orders() : array( $this->order );
		$aggregate = count( $orders ) > 1 && $doc->setting( 'aggregate', 1 );
		$rows      = array();
		foreach ( $orders as $order ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$qty = (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item->get_id() );
				if ( $qty <= 0 ) {
					continue;
				}
				$row        = $this->item_row( $item, $order );
				$row['qty'] = $qty;
				$key        = $aggregate ? ( $row['product_id'] . '|' . md5( (string) $row['meta_html'] ) ) : $order->get_id() . '|' . $item->get_id();
				if ( isset( $rows[ $key ] ) ) {
					$rows[ $key ]['qty']     += $qty;
					$rows[ $key ]['orders'][] = $order->get_order_number();
				} else {
					$row['orders'] = array( $order->get_order_number() );
					$rows[ $key ]  = $row;
				}
			}
		}
		foreach ( $rows as &$row ) {
			$row['quantity'] = $this->quantity( $row['qty'] );
			$row['orders']   = esc_html( implode( ', ', array_unique( $row['orders'] ) ) );
		}
		unset( $row );
		$rows    = array_values( $rows );
		$sort_by = (string) $doc->setting( 'sort_by', 'sku' );
		if ( 'order' !== $sort_by ) {
			usort(
				$rows,
				static function ( $a, $b ) use ( $sort_by ) {
					$field = array(
						'sku'      => 'sku',
						'name'     => 'name',
						'category' => 'category',
						'location' => 'location',
					)[ $sort_by ] ?? 'sku';
					$cmp   = strnatcasecmp( (string) $a[ $field ], (string) $b[ $field ] );
					return 0 !== $cmp ? $cmp : strnatcasecmp( (string) $a['name'], (string) $b['name'] );
				}
			);
		}
		return $rows;
	}

	/* ------------------------------------------------------------------
	 * Totals
	 * ------------------------------------------------------------------ */

	/**
	 * Total rows: key => [label, value, class].
	 *
	 * @param array $show Which parts to include (subtotal, discount, shipping, fees, taxes).
	 */
	public function totals( array $show = array() ): array {
		$show   = array_merge( array( 'subtotal' => 1, 'discount' => 1, 'shipping' => 1, 'fees' => 1, 'taxes' => 1 ), $show );
		$src    = $this->source;
		$incl   = $this->prices_incl_tax();
		$totals = array();

		if ( $this->is_refund() && ! $src->get_items( 'line_item' ) && ! $src->get_items( 'shipping' ) && ! $src->get_items( 'fee' ) ) {
			$totals['total'] = array( 'label' => __( 'Total', 'order-documents' ), 'value' => $this->money( $src->get_amount() ), 'class' => 'total' );
			return apply_filters( 'odocs_totals', $totals, $this->document );
		}

		if ( $show['subtotal'] ) {
			$subtotal = 0.0;
			foreach ( $src->get_items( 'line_item' ) as $item ) {
				$subtotal += (float) $item->get_subtotal() + ( $incl ? (float) $item->get_subtotal_tax() : 0 );
			}
			$totals['subtotal'] = array( 'label' => __( 'Subtotal', 'order-documents' ), 'value' => $this->money( $subtotal ), 'class' => 'subtotal' );
		}

		if ( $show['discount'] ) {
			$discount = (float) $src->get_total_discount( ! $incl );
			if ( abs( $discount ) > 0.0001 ) {
				$codes = method_exists( $src, 'get_coupon_codes' ) ? $src->get_coupon_codes() : array();
				$label = __( 'Discount', 'order-documents' ) . ( $codes ? ' (' . implode( ', ', array_map( 'wc_format_coupon_code', $codes ) ) . ')' : '' );
				$totals['discount'] = array( 'label' => $label, 'value' => $this->signed_negative( $discount ), 'class' => 'discount' );
			}
		}

		if ( $show['shipping'] ) {
			foreach ( $src->get_items( 'shipping' ) as $id => $item ) {
				$amount = (float) $item->get_total() + ( $incl ? (float) $item->get_total_tax() : 0 );
				/* translators: %s: shipping method */
				$totals[ 'shipping_' . $id ] = array( 'label' => sprintf( __( 'Shipping: %s', 'order-documents' ), $item->get_name() ), 'value' => $this->money( $amount ), 'class' => 'shipping' );
			}
		}

		if ( $show['fees'] ) {
			foreach ( $src->get_items( 'fee' ) as $id => $item ) {
				$amount = (float) $item->get_total() + ( $incl ? (float) $item->get_total_tax() : 0 );
				$totals[ 'fee_' . $id ] = array( 'label' => $item->get_name(), 'value' => $this->money( $amount ), 'class' => 'fee' );
			}
		}

		$tax_rows = array();
		foreach ( $src->get_items( 'tax' ) as $id => $tax_item ) {
			$amount = (float) $tax_item->get_tax_total() + (float) $tax_item->get_shipping_tax_total();
			if ( abs( $amount ) < 0.0001 ) {
				continue;
			}
			$percent = is_callable( array( $tax_item, 'get_rate_percent' ) ) && null !== $tax_item->get_rate_percent() ? $this->format_percent( (float) $tax_item->get_rate_percent() ) : '';
			$label   = $tax_item->get_label() ?: __( 'VAT', 'order-documents' );
			if ( $percent && false === strpos( $label, '%' ) ) {
				$label .= ' ' . $percent;
			}
			$tax_rows[ 'tax_' . $id ] = array( 'label' => $label, 'value' => $this->money( $amount ), 'class' => 'tax' );
		}

		if ( $show['taxes'] && ! $incl ) {
			$totals = array_merge( $totals, $tax_rows );
		}

		$totals['total'] = array( 'label' => __( 'Total', 'order-documents' ), 'value' => $this->money( $src->get_total() ), 'class' => 'total' );

		if ( $show['taxes'] && $incl && $tax_rows ) {
			$tax_total = 0.0;
			foreach ( $src->get_items( 'tax' ) as $tax_item ) {
				$tax_total += (float) $tax_item->get_tax_total() + (float) $tax_item->get_shipping_tax_total();
			}
			/* translators: %s: tax amount */
			$totals['tax_included'] = array( 'label' => __( 'Of which VAT', 'order-documents' ), 'value' => $this->money( $tax_total ), 'class' => 'tax-included' );
		}

		return apply_filters( 'odocs_totals', $totals, $this->document );
	}

	/**
	 * Discounts are shown as negative amounts on invoices and as positive amounts (reducing the credit) on credit notes.
	 */
	private function signed_negative( float $amount ): string {
		$amount = abs( $amount );
		if ( $this->is_refund() ) {
			return wc_price( $this->document->setting( 'negative_amounts', 1 ) ? $amount : -$amount, array( 'currency' => $this->order->get_currency() ) );
		}
		return wc_price( -$amount, array( 'currency' => $this->order->get_currency() ) );
	}

	/**
	 * VAT summary per rate: rate, basis (net amount) and VAT.
	 *
	 * @return array<int,array{rate:string,label:string,basis:string,tax:string,total:string}>
	 */
	public function vat_summary(): array {
		if ( ! wc_tax_enabled() ) {
			return array();
		}
		$buckets = array();
		foreach ( array( 'line_item', 'shipping', 'fee' ) as $type ) {
			foreach ( $this->source->get_items( $type ) as $item ) {
				$net   = (float) $item->get_total();
				$taxes = array_filter(
					(array) ( $item->get_taxes()['total'] ?? array() ),
					static function ( $amount ) {
						return '' !== $amount && null !== $amount;
					}
				);
				if ( ! $taxes ) {
					$buckets[0]['basis'] = ( $buckets[0]['basis'] ?? 0 ) + $net;
					$buckets[0]['tax']   = ( $buckets[0]['tax'] ?? 0 );
					continue;
				}
				foreach ( $taxes as $rate_id => $amount ) {
					$buckets[ $rate_id ]['basis'] = ( $buckets[ $rate_id ]['basis'] ?? 0 ) + $net;
					$buckets[ $rate_id ]['tax']   = ( $buckets[ $rate_id ]['tax'] ?? 0 ) + (float) $amount;
				}
			}
		}
		$rows = array();
		foreach ( $buckets as $rate_id => $bucket ) {
			if ( abs( $bucket['basis'] ) < 0.0001 && abs( $bucket['tax'] ) < 0.0001 ) {
				continue;
			}
			$label = $rate_id ? $this->rate_label( (int) $rate_id ) : __( 'No VAT', 'order-documents' );
			$rows[] = array(
				'rate'  => $rate_id ? $this->rate_percent( (int) $rate_id ) : $this->format_percent( 0 ),
				'label' => $label,
				'basis' => $this->money( $bucket['basis'] ),
				'tax'   => $this->money( $bucket['tax'] ),
				'total' => $this->money( $bucket['basis'] + $bucket['tax'] ),
			);
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return (float) str_replace( ',', '.', $b['rate'] ) <=> (float) str_replace( ',', '.', $a['rate'] );
			}
		);
		return apply_filters( 'odocs_vat_summary', $rows, $this->document );
	}
}

