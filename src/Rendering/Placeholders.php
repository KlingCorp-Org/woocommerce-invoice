<?php
/**
 * {{placeholder}} replacement in texts (footer, custom text, payment information...).
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

use OrderDocs\Documents\Document;
use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class Placeholders {

	/**
	 * Documented placeholders, for the help text in settings and the editor.
	 */
	public static function help(): array {
		return array(
			'{{document_title}}'   => __( 'Document title', 'order-documents' ),
			'{{document_number}}'  => __( 'Document number', 'order-documents' ),
			'{{document_date}}'    => __( 'Document date', 'order-documents' ),
			'{{invoice_number}}'   => __( 'Invoice number', 'order-documents' ),
			'{{invoice_date}}'     => __( 'Invoice date', 'order-documents' ),
			'{{due_date}}'         => __( 'Due date', 'order-documents' ),
			'{{kid}}'              => __( 'KID payment reference', 'order-documents' ),
			'{{order_number}}'     => __( 'Order number', 'order-documents' ),
			'{{order_date}}'       => __( 'Order date', 'order-documents' ),
			'{{order_total}}'      => __( 'Order total', 'order-documents' ),
			'{{payment_method}}'   => __( 'Payment method', 'order-documents' ),
			'{{shipping_method}}'  => __( 'Shipping method', 'order-documents' ),
			'{{customer_note}}'    => __( 'Customer note', 'order-documents' ),
			'{{billing_first_name}}' => __( 'Any billing_ or shipping_ field, e.g. billing_company', 'order-documents' ),
			'{{shop_name}}'        => __( 'Shop name', 'order-documents' ),
			'{{org_number}}'       => __( 'Organisation number', 'order-documents' ),
			'{{vat_number}}'       => __( 'VAT number', 'order-documents' ),
			'{{bank_account}}'     => __( 'Bank account number', 'order-documents' ),
			'{{iban}}'             => __( 'IBAN', 'order-documents' ),
			'{{bic}}'              => __( 'BIC / SWIFT', 'order-documents' ),
			'{{site_title}}'       => __( 'Site title', 'order-documents' ),
			'{{site_url}}'         => __( 'Site address', 'order-documents' ),
			'{{year}}'             => __( 'Current year', 'order-documents' ),
			'{{meta:_your_key}}'   => __( 'Any order meta field', 'order-documents' ),
		);
	}

	/**
	 * Replaces placeholders. Values are HTML-escaped; the surrounding text is not touched.
	 */
	public static function replace( string $text, Document $document ): string {
		if ( false === strpos( $text, '{{' ) ) {
			return $text;
		}
		return (string) preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_:\-\.]+)\s*\}\}/',
			static function ( $match ) use ( $document ) {
				$value = self::value( $match[1], $document );
				return null === $value ? $match[0] : esc_html( $value );
			},
			$text
		);
	}

	/**
	 * Value for one placeholder, or null when unknown.
	 */
	public static function value( string $key, Document $document ): ?string {
		$order   = $document->get_order();
		$general = Settings::section( 'general' );

		if ( 0 === strpos( $key, 'meta:' ) ) {
			$meta = $order->get_meta( substr( $key, 5 ) );
			return is_scalar( $meta ) ? (string) $meta : '';
		}
		if ( preg_match( '/^(billing|shipping)_[a-z0-9_]+$/', $key ) && is_callable( array( $order, 'get_' . $key ) ) ) {
			return (string) $order->{'get_' . $key}();
		}

		switch ( $key ) {
			case 'document_title':
				return $document->get_title();
			case 'document_number':
				return $document->get_number() ?: (string) $order->get_order_number();
			case 'document_date':
				return odocs_format_date( $document->get_display_date() );
			case 'invoice_number':
			case 'invoice_date':
				$invoice = 'invoice' === $document->get_type() ? $document : DocumentRegistry::make( 'invoice', $order );
				if ( ! $invoice || ! $invoice->exists() ) {
					return '';
				}
				return 'invoice_number' === $key ? $invoice->get_number() : odocs_format_date( $invoice->get_display_date() );
			case 'due_date':
				return method_exists( $document, 'get_due_date' ) && $document->get_due_date() ? odocs_format_date( $document->get_due_date() ) : '';
			case 'kid':
				return method_exists( $document, 'get_kid' ) ? $document->get_kid() : '';
			case 'order_number':
				return (string) $order->get_order_number();
			case 'order_date':
				return odocs_format_date( $order->get_date_created() );
			case 'order_total':
				return html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
			case 'payment_method':
				return (string) $order->get_payment_method_title();
			case 'shipping_method':
				return (string) $order->get_shipping_method();
			case 'customer_note':
				return (string) $order->get_customer_note();
			case 'customer_id':
				return (string) $order->get_customer_id();
			case 'shop_name':
				return (string) $general['shop_name'];
			case 'org_number':
			case 'vat_number':
			case 'bank_account':
			case 'bank_name':
			case 'iban':
			case 'bic':
			case 'shop_phone':
			case 'shop_email':
			case 'shop_website':
				return (string) ( $general[ $key ] ?? '' );
			case 'site_title':
				return get_bloginfo( 'name' );
			case 'site_url':
				return home_url();
			case 'year':
				return wp_date( 'Y' );
		}
		$custom = apply_filters( 'odocs_placeholder_value', null, $key, $document );
		return null === $custom ? null : (string) $custom;
	}
}
