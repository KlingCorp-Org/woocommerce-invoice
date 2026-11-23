<?php
/**
 * Declarative settings definitions.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Settings;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Rendering\Fonts;
use OrderDocs\Rendering\TemplateRegistry;

defined( 'ABSPATH' ) || exit;

class Schema {

	/** @var array<string,array> */
	private static $defaults_cache = array();

	/**
	 * Field definitions for a section.
	 */
	public static function fields( string $section ): array {
		if ( 0 === strpos( $section, 'doc_' ) ) {
			$type  = substr( $section, 4 );
			$class = DocumentRegistry::get_class( $type );
			$fields = $class ? $class::settings_fields() : array();
		} else {
			switch ( $section ) {
				case 'general':
					$fields = self::general();
					break;
				case 'design':
					$fields = self::design();
					break;
				case 'advanced':
					$fields = self::advanced();
					break;
				default:
					$fields = array();
			}
		}
		return apply_filters( 'odocs_settings_fields', $fields, $section );
	}

	public static function defaults( string $section ): array {
		if ( ! isset( self::$defaults_cache[ $section ] ) ) {
			$defaults = array();
			foreach ( self::fields( $section ) as $field ) {
				if ( ! empty( $field['id'] ) && array_key_exists( 'default', $field ) ) {
					$defaults[ $field['id'] ] = $field['default'];
				}
			}
			self::$defaults_cache[ $section ] = $defaults;
		}
		return self::$defaults_cache[ $section ];
	}

	private static function general(): array {
		return array(
			array( 'type' => 'heading', 'label' => __( 'Shop details', 'order-documents' ), 'description' => __( 'Shown in the header of every document.', 'order-documents' ) ),
			array( 'id' => 'logo_id', 'type' => 'media', 'label' => __( 'Logo', 'order-documents' ), 'default' => 0 ),
			array( 'id' => 'shop_name', 'type' => 'text', 'label' => __( 'Shop name', 'order-documents' ), 'default' => get_bloginfo( 'name' ) ),
			array( 'id' => 'shop_address', 'type' => 'textarea', 'label' => __( 'Shop address', 'order-documents' ), 'default' => self::wc_store_address() ),
			array( 'id' => 'shop_phone', 'type' => 'text', 'label' => __( 'Phone', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'shop_email', 'type' => 'text', 'label' => __( 'E-mail', 'order-documents' ), 'default' => get_option( 'woocommerce_email_from_address', '' ) ),
			array( 'id' => 'shop_website', 'type' => 'text', 'label' => __( 'Website', 'order-documents' ), 'default' => preg_replace( '#^https?://#', '', home_url() ) ),
			array( 'id' => 'org_number', 'type' => 'text', 'label' => __( 'Organisation number', 'order-documents' ), 'default' => '', 'description' => __( 'For example "Org.nr. 123 456 789 MVA". Shown as entered.', 'order-documents' ) ),
			array( 'id' => 'vat_number', 'type' => 'text', 'label' => __( 'VAT number', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'register_text', 'type' => 'text', 'label' => __( 'Business register text', 'order-documents' ), 'default' => '', 'description' => __( 'For example "Foretaksregisteret" (Norwegian limited companies).', 'order-documents' ) ),
			array( 'type' => 'heading', 'label' => __( 'Payment details', 'order-documents' ), 'description' => __( 'Available as placeholders in payment information texts.', 'order-documents' ) ),
			array( 'id' => 'bank_name', 'type' => 'text', 'label' => __( 'Bank', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'bank_account', 'type' => 'text', 'label' => __( 'Bank account number', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'iban', 'type' => 'text', 'label' => __( 'IBAN', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'bic', 'type' => 'text', 'label' => __( 'BIC / SWIFT', 'order-documents' ), 'default' => '' ),
			array( 'type' => 'heading', 'label' => __( 'Extra texts', 'order-documents' ) ),
			array( 'id' => 'extra_1', 'type' => 'textarea', 'label' => __( 'Extra field 1', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'extra_2', 'type' => 'textarea', 'label' => __( 'Extra field 2', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'extra_3', 'type' => 'textarea', 'label' => __( 'Extra field 3', 'order-documents' ), 'default' => '' ),
			array( 'id' => 'footer', 'type' => 'textarea', 'label' => __( 'Footer', 'order-documents' ), 'default' => '', 'description' => __( 'Placeholders are supported, see the list at the bottom of the page.', 'order-documents' ) ),
			array( 'type' => 'heading', 'label' => __( 'Output', 'order-documents' ) ),
			array(
				'id'      => 'paper_size',
				'type'    => 'select',
				'label'   => __( 'Paper size', 'order-documents' ),
				'default' => 'a4',
				'options' => array(
					'a4'     => 'A4',
					'letter' => __( 'Letter', 'order-documents' ),
					'a5'     => 'A5',
				),
			),
			array(
				'id'      => 'output',
				'type'    => 'select',
				'label'   => __( 'How to open PDFs', 'order-documents' ),
				'default' => 'inline',
				'options' => array(
					'inline'   => __( 'Open in the browser', 'order-documents' ),
					'download' => __( 'Download the file', 'order-documents' ),
				),
			),
			array( 'id' => 'date_format', 'type' => 'text', 'label' => __( 'Date format', 'order-documents' ), 'default' => '', 'description' => __( 'PHP date format. Leave empty to use the WordPress setting.', 'order-documents' ) ),
			array(
				'id'      => 'language',
				'type'    => 'select',
				'label'   => __( 'Document language', 'order-documents' ),
				'default' => 'site',
				'options' => array(
					'site'     => __( 'Site language', 'order-documents' ),
					'customer' => __( "Customer's profile language (when set)", 'order-documents' ),
				),
			),
		);
	}

	private static function design(): array {
		return array(
			array( 'type' => 'heading', 'label' => __( 'Template', 'order-documents' ) ),
			array( 'id' => 'template', 'type' => 'template', 'label' => __( 'Default template', 'order-documents' ), 'default' => 'classic', 'options' => array( TemplateRegistry::class, 'options' ) ),
			array( 'type' => 'heading', 'label' => __( 'Style', 'order-documents' ) ),
			array( 'id' => 'accent_color', 'type' => 'color', 'label' => __( 'Accent colour', 'order-documents' ), 'default' => '#1f4e79' ),
			array( 'id' => 'text_color', 'type' => 'color', 'label' => __( 'Text colour', 'order-documents' ), 'default' => '#222222' ),
			array( 'id' => 'muted_color', 'type' => 'color', 'label' => __( 'Secondary text colour', 'order-documents' ), 'default' => '#6b7280' ),
			array( 'id' => 'font_family', 'type' => 'select', 'label' => __( 'Font', 'order-documents' ), 'default' => 'dejavu-sans', 'options' => array( Fonts::class, 'options' ) ),
			array( 'id' => 'font_size', 'type' => 'number', 'label' => __( 'Base font size (pt)', 'order-documents' ), 'default' => 9, 'min' => 6, 'max' => 14, 'step' => 0.5 ),
			array( 'id' => 'logo_height', 'type' => 'number', 'label' => __( 'Logo height (mm)', 'order-documents' ), 'default' => 18, 'min' => 5, 'max' => 80 ),
			array(
				'id'      => 'table_style',
				'type'    => 'select',
				'label'   => __( 'Table style', 'order-documents' ),
				'default' => '',
				'options' => array(
					''        => __( 'Template default', 'order-documents' ),
					'lined'   => __( 'Lines between rows', 'order-documents' ),
					'striped' => __( 'Striped rows', 'order-documents' ),
					'boxed'   => __( 'Boxed', 'order-documents' ),
					'plain'   => __( 'Plain', 'order-documents' ),
				),
			),
			array( 'type' => 'heading', 'label' => __( 'Page', 'order-documents' ) ),
			array( 'id' => 'margin_top', 'type' => 'number', 'label' => __( 'Top margin (mm)', 'order-documents' ), 'default' => 15, 'min' => 0, 'max' => 60 ),
			array( 'id' => 'margin_right', 'type' => 'number', 'label' => __( 'Right margin (mm)', 'order-documents' ), 'default' => 15, 'min' => 0, 'max' => 60 ),
			array( 'id' => 'margin_bottom', 'type' => 'number', 'label' => __( 'Bottom margin (mm)', 'order-documents' ), 'default' => 20, 'min' => 0, 'max' => 60 ),
			array( 'id' => 'margin_left', 'type' => 'number', 'label' => __( 'Left margin (mm)', 'order-documents' ), 'default' => 15, 'min' => 0, 'max' => 60 ),
			array( 'id' => 'page_numbers', 'type' => 'checkbox', 'label' => __( 'Page numbers', 'order-documents' ), 'description' => __( 'Print "Page X of Y" at the bottom of multi-page documents.', 'order-documents' ), 'default' => 1 ),
		);
	}

	private static function advanced(): array {
		return array(
			array( 'type' => 'heading', 'label' => __( 'Access', 'order-documents' ) ),
			array(
				'id'      => 'capability',
				'type'    => 'select',
				'label'   => __( 'Who can create documents in the admin', 'order-documents' ),
				'default' => 'manage_woocommerce',
				'options' => array(
					'manage_woocommerce' => __( 'Shop managers and administrators', 'order-documents' ),
					'edit_shop_orders'   => __( 'Anyone who can edit orders', 'order-documents' ),
				),
			),
			array( 'id' => 'guest_access', 'type' => 'checkbox', 'label' => __( 'Guest access', 'order-documents' ), 'description' => __( 'Let customers without an account open their documents with a secure link that contains the order key.', 'order-documents' ), 'default' => 1 ),
			array( 'type' => 'heading', 'label' => __( 'Previous plugin data', 'order-documents' ) ),
			array( 'id' => 'legacy_fallback', 'type' => 'checkbox', 'label' => __( 'Use WooCommerce PDF Invoices & Packing Slips data', 'order-documents' ), 'description' => __( 'Orders that already have an invoice (or other document) from that plugin keep its number and date.', 'order-documents' ), 'default' => 1 ),
			array( 'type' => 'heading', 'label' => __( 'Performance and debugging', 'order-documents' ) ),
			array( 'id' => 'bulk_limit', 'type' => 'number', 'label' => __( 'Bulk limit', 'order-documents' ), 'description' => __( 'Maximum number of orders in one bulk PDF.', 'order-documents' ), 'default' => 100, 'min' => 1, 'max' => 1000 ),
			array( 'id' => 'debug_html', 'type' => 'checkbox', 'label' => __( 'Output HTML instead of PDF', 'order-documents' ), 'description' => __( 'For template development only.', 'order-documents' ), 'default' => 0 ),
			array( 'id' => 'delete_data', 'type' => 'checkbox', 'label' => __( 'Delete settings on uninstall', 'order-documents' ), 'description' => __( 'Removes settings, layouts and number sequences when the plugin is deleted. Document data on orders and archived PDFs are always kept.', 'order-documents' ), 'default' => 0 ),
		);
	}

	private static function wc_store_address(): string {
		$parts = array_filter(
			array(
				get_option( 'woocommerce_store_address', '' ),
				get_option( 'woocommerce_store_address_2', '' ),
				trim( get_option( 'woocommerce_store_postcode', '' ) . ' ' . get_option( 'woocommerce_store_city', '' ) ),
			)
		);
		return implode( "\n", $parts );
	}
}
