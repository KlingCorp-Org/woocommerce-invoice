<?php
/**
 * Document layouts. Stored per template and document type.
 *
 * Version 2 (free-form sections, made by the editor since 1.1.1):
 * {
 *   "version": 2,
 *   "sections": [
 *     { "id": "s1", "kind": "canvas|flow|footer", "name": "", "height": 92, "keep_together": 1,
 *       "items": [ { "id": "i1", "type": "logo", "settings": { ... }, "x": 0, "y": 0, "w": 50, "h": 18,
 *                    "snap": 1, "locked": 0, "overflow": "grow|clip" } ] }
 *   ]
 * }
 * Positions and sizes are in mm. x is measured from the left page margin, y from the top of the section.
 * Item order is the layer order (last = on top). Canvas items never push each other; flow sections
 * stack their items and can break across pages (the products table); the footer section sits at the
 * bottom of every page.
 *
 * Version 1 (rows of 1-3 columns, still rendered as before):
 * {
 *   "rows": [
 *     { "id": "r1", "settings": { "background": "", "padding": 0, "margin_bottom": 6, "text_color": "" },
 *       "columns": [ { "width": 50, "blocks": [ { "id": "b1", "type": "logo", "settings": { ... } } ] } ] }
 *   ]
 * }
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

defined( 'ABSPATH' ) || exit;

class Layout {

	public static function option_name( string $template, string $type ): string {
		return 'odocs_layout_' . sanitize_key( $template ) . '_' . sanitize_key( $type );
	}

	/**
	 * Saved layout, or the template's default layout for the document type.
	 */
	public static function get( string $template, string $type ): array {
		$saved = get_option( self::option_name( $template, $type ) );
		if ( self::has_content( $saved ) ) {
			$layout = self::sanitize( $saved, $type );
		} else {
			$layout = self::default_layout( $template, $type );
		}
		return apply_filters( 'odocs_layout', $layout, $template, $type );
	}

	public static function is_customized( string $template, string $type ): bool {
		return self::has_content( get_option( self::option_name( $template, $type ) ) );
	}

	/**
	 * Whether a stored value is a usable layout (rows or sections).
	 *
	 * @param mixed $layout Stored value.
	 */
	private static function has_content( $layout ): bool {
		return is_array( $layout ) && ( ! empty( $layout['rows'] ) || ! empty( $layout['sections'] ) );
	}

	/**
	 * Whether a (sanitized) layout uses free-form sections.
	 */
	public static function is_v2( array $layout ): bool {
		return isset( $layout['sections'] );
	}

	public static function save( string $template, string $type, array $layout ): array {
		$clean = self::sanitize( $layout, $type );
		update_option( self::option_name( $template, $type ), $clean, false );
		return $clean;
	}

	public static function reset( string $template, string $type ): void {
		delete_option( self::option_name( $template, $type ) );
	}

	public static function default_layout( string $template, string $type ): array {
		return self::sanitize( DefaultLayouts::get( $template, $type ), $type );
	}

	/* ------------------------------------------------------------------
	 * Block types
	 * ------------------------------------------------------------------ */

	/**
	 * Common settings every block has.
	 */
	public static function common_settings(): array {
		return array(
			'align'         => array( 'type' => 'select', 'label' => __( 'Alignment', 'order-documents' ), 'default' => '', 'options' => array( '' => __( 'Default', 'order-documents' ), 'left' => __( 'Left', 'order-documents' ), 'center' => __( 'Center', 'order-documents' ), 'right' => __( 'Right', 'order-documents' ) ) ),
			'font_size'     => array( 'type' => 'number', 'label' => __( 'Font size (pt, 0 = default)', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 40, 'step' => 0.5 ),
			'margin_bottom' => array( 'type' => 'number', 'label' => __( 'Space below (mm)', 'order-documents' ), 'default' => 3, 'min' => 0, 'max' => 50, 'step' => 0.5 ),
			'text_color'    => array( 'type' => 'color', 'label' => __( 'Text colour', 'order-documents' ), 'default' => '' ),
			'box'           => array( 'type' => 'select', 'label' => __( 'Box', 'order-documents' ), 'default' => '', 'options' => array( '' => __( 'None', 'order-documents' ), 'border' => __( 'Rounded border', 'order-documents' ), 'filled' => __( 'Tinted background', 'order-documents' ) ) ),
		);
	}

	/**
	 * Block type definitions: label, description, icon (dashicon) and settings schema.
	 */
	public static function block_types(): array {
		$heading = static function ( $default_label ) {
			return array(
				'show_heading' => array( 'type' => 'checkbox', 'label' => __( 'Show heading', 'order-documents' ), 'default' => 1 ),
				'heading'      => array( 'type' => 'text', 'label' => __( 'Heading', 'order-documents' ), 'default' => '', 'placeholder' => $default_label ),
			);
		};
		$address = static function ( $default_label ) use ( $heading ) {
			return array_merge(
				$heading( $default_label ),
				array( 'compact' => array( 'type' => 'checkbox', 'label' => __( 'Compact: name in bold, the rest on one line', 'order-documents' ), 'default' => 0 ) )
			);
		};

		$types = array(
			'logo'              => array(
				'label'    => __( 'Logo', 'order-documents' ),
				'icon'     => 'format-image',
				'settings' => array(
					'max_height'   => array( 'type' => 'number', 'label' => __( 'Logo height (mm, 0 = automatic)', 'order-documents' ), 'description' => __( 'Automatic: fills the box on the page, or uses the logo height from the design settings.', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 100 ),
					'name_fallback' => array( 'type' => 'checkbox', 'label' => __( 'Show the shop name when there is no logo', 'order-documents' ), 'default' => 1 ),
				),
			),
			'shop_info'         => array(
				'label'    => __( 'Shop details', 'order-documents' ),
				'icon'     => 'store',
				'settings' => array(
					'show_name'          => array( 'type' => 'checkbox', 'label' => __( 'Shop name', 'order-documents' ), 'default' => 1 ),
					'show_address'       => array( 'type' => 'checkbox', 'label' => __( 'Address', 'order-documents' ), 'default' => 1 ),
					'show_phone'         => array( 'type' => 'checkbox', 'label' => __( 'Phone', 'order-documents' ), 'default' => 1 ),
					'show_email'         => array( 'type' => 'checkbox', 'label' => __( 'E-mail', 'order-documents' ), 'default' => 1 ),
					'show_website'       => array( 'type' => 'checkbox', 'label' => __( 'Website', 'order-documents' ), 'default' => 0 ),
					'show_org_number'    => array( 'type' => 'checkbox', 'label' => __( 'Organisation number', 'order-documents' ), 'default' => 1 ),
					'show_vat_number'    => array( 'type' => 'checkbox', 'label' => __( 'VAT number', 'order-documents' ), 'default' => 1 ),
					'show_register_text' => array( 'type' => 'checkbox', 'label' => __( 'Business register text', 'order-documents' ), 'default' => 1 ),
					'show_extra_1'       => array( 'type' => 'checkbox', 'label' => __( 'Extra field 1', 'order-documents' ), 'default' => 0 ),
					'show_extra_2'       => array( 'type' => 'checkbox', 'label' => __( 'Extra field 2', 'order-documents' ), 'default' => 0 ),
					'show_extra_3'       => array( 'type' => 'checkbox', 'label' => __( 'Extra field 3', 'order-documents' ), 'default' => 0 ),
				),
			),
			'document_title'    => array(
				'label'    => __( 'Document title', 'order-documents' ),
				'icon'     => 'heading',
				'settings' => array(
					'text'       => array( 'type' => 'text', 'label' => __( 'Title (empty = document title)', 'order-documents' ), 'default' => '' ),
					'uppercase'  => array( 'type' => 'checkbox', 'label' => __( 'Uppercase', 'order-documents' ), 'default' => 0 ),
					'show_number' => array( 'type' => 'checkbox', 'label' => __( 'Add the document number', 'order-documents' ), 'default' => 0 ),
					'subline'     => array(
						'type'    => 'select',
						'label'   => __( 'Line below the title', 'order-documents' ),
						'default' => '',
						'options' => array(
							''       => __( 'None', 'order-documents' ),
							'number' => __( 'Document number', 'order-documents' ),
							'order'  => __( 'Order number', 'order-documents' ),
						),
					),
				),
			),
			'billing_address'   => array(
				'label'    => __( 'Billing address', 'order-documents' ),
				'icon'     => 'id',
				'settings' => $address( __( 'Billing address', 'order-documents' ) ),
			),
			'shipping_address'  => array(
				'label'    => __( 'Shipping address', 'order-documents' ),
				'icon'     => 'location',
				'settings' => array_merge(
					$address( __( 'Ship to', 'order-documents' ) ),
					array( 'fallback' => array( 'type' => 'checkbox', 'label' => __( 'Show the billing address when there is no separate delivery address', 'order-documents' ), 'default' => 0 ) )
				),
			),
			'document_data'     => array(
				'label'    => __( 'Document data', 'order-documents' ),
				'icon'     => 'editor-table',
				'settings' => array(
					'show_number'          => array( 'type' => 'checkbox', 'label' => __( 'Document number', 'order-documents' ), 'default' => 1 ),
					'show_date'            => array( 'type' => 'checkbox', 'label' => __( 'Document date', 'order-documents' ), 'default' => 1 ),
					'show_order_number'    => array( 'type' => 'checkbox', 'label' => __( 'Order number', 'order-documents' ), 'default' => 1 ),
					'show_order_date'      => array( 'type' => 'checkbox', 'label' => __( 'Order date', 'order-documents' ), 'default' => 1 ),
					'show_payment_method'  => array( 'type' => 'checkbox', 'label' => __( 'Payment method', 'order-documents' ), 'default' => 1 ),
					'show_shipping_method' => array( 'type' => 'checkbox', 'label' => __( 'Shipping method', 'order-documents' ), 'default' => 0 ),
					'show_other'           => array( 'type' => 'checkbox', 'label' => __( 'Other details (due date, references...)', 'order-documents' ), 'default' => 1 ),
					'boxed'                => array( 'type' => 'checkbox', 'label' => __( 'Boxed', 'order-documents' ), 'default' => 0 ),
					'layout'               => array(
						'type'    => 'select',
						'label'   => __( 'Layout', 'order-documents' ),
						'default' => 'table',
						'options' => array(
							'table' => __( 'Label and value side by side', 'order-documents' ),
							'grid'  => __( 'Columns with the label above the value', 'order-documents' ),
						),
					),
					'grid_columns'         => array( 'type' => 'number', 'label' => __( 'Columns (column layout)', 'order-documents' ), 'default' => 4, 'min' => 1, 'max' => 6 ),
					'exclude'              => array( 'type' => 'text', 'label' => __( 'Hide these rows', 'order-documents' ), 'default' => '', 'placeholder' => 'due_date, payment_method' ),
				),
			),
			'items_table'       => array(
				'label'    => __( 'Products table', 'order-documents' ),
				'icon'     => 'list-view',
				'settings' => array(
					'columns'     => array( 'type' => 'columns', 'label' => __( 'Columns', 'order-documents' ), 'default' => array() ),
					'show_header' => array( 'type' => 'checkbox', 'label' => __( 'Column headings', 'order-documents' ), 'default' => 1 ),
					'image_size'  => array( 'type' => 'number', 'label' => __( 'Image size (mm)', 'order-documents' ), 'default' => 12, 'min' => 5, 'max' => 40 ),
				),
			),
			'totals'            => array(
				'label'    => __( 'Totals', 'order-documents' ),
				'icon'     => 'calculator',
				'settings' => array(
					'show_subtotal' => array( 'type' => 'checkbox', 'label' => __( 'Subtotal', 'order-documents' ), 'default' => 1 ),
					'show_discount' => array( 'type' => 'checkbox', 'label' => __( 'Discount', 'order-documents' ), 'default' => 1 ),
					'show_shipping' => array( 'type' => 'checkbox', 'label' => __( 'Shipping', 'order-documents' ), 'default' => 1 ),
					'show_fees'     => array( 'type' => 'checkbox', 'label' => __( 'Fees', 'order-documents' ), 'default' => 1 ),
					'show_taxes'    => array( 'type' => 'checkbox', 'label' => __( 'Tax lines', 'order-documents' ), 'default' => 1 ),
					'total_label'   => array( 'type' => 'text', 'label' => __( 'Total label', 'order-documents' ), 'default' => '', 'placeholder' => __( 'Total', 'order-documents' ) ),
				),
			),
			'vat_summary'       => array(
				'label'    => __( 'VAT summary', 'order-documents' ),
				'icon'     => 'chart-pie',
				'settings' => $heading( __( 'VAT summary', 'order-documents' ) ),
			),
			'customer_note'     => array(
				'label'    => __( 'Customer note', 'order-documents' ),
				'icon'     => 'format-quote',
				'settings' => $heading( __( 'Customer note', 'order-documents' ) ),
			),
			'document_notes'    => array(
				'label'    => __( 'Document notes', 'order-documents' ),
				'icon'     => 'edit',
				'settings' => $heading( __( 'Notes', 'order-documents' ) ),
			),
			'payment_info'      => array(
				'label'    => __( 'Payment information', 'order-documents' ),
				'icon'     => 'bank',
				'settings' => $heading( __( 'Payment information', 'order-documents' ) ),
			),
			'document_notice'   => array(
				'label'    => __( 'Document notice', 'order-documents' ),
				'icon'     => 'info-outline',
				'settings' => array(),
			),
			'custom_text'       => array(
				'label'    => __( 'Custom text', 'order-documents' ),
				'icon'     => 'editor-paragraph',
				'settings' => array(
					'content' => array( 'type' => 'textarea', 'label' => __( 'Text (HTML and placeholders allowed)', 'order-documents' ), 'default' => '' ),
				),
			),
			'divider'           => array(
				'label'    => __( 'Divider', 'order-documents' ),
				'icon'     => 'minus',
				'settings' => array(
					'color'     => array( 'type' => 'color', 'label' => __( 'Colour', 'order-documents' ), 'default' => '' ),
					'thickness' => array( 'type' => 'number', 'label' => __( 'Thickness (pt)', 'order-documents' ), 'default' => 0.75, 'min' => 0.25, 'max' => 10, 'step' => 0.25 ),
					'style'     => array( 'type' => 'select', 'label' => __( 'Style', 'order-documents' ), 'default' => 'solid', 'options' => array( 'solid' => __( 'Solid', 'order-documents' ), 'dashed' => __( 'Dashed', 'order-documents' ), 'dotted' => __( 'Dotted', 'order-documents' ) ) ),
				),
			),
			'spacer'            => array(
				'label'    => __( 'Spacer', 'order-documents' ),
				'icon'     => 'image-flip-vertical',
				'settings' => array(
					'height' => array( 'type' => 'number', 'label' => __( 'Height (mm)', 'order-documents' ), 'default' => 5, 'min' => 1, 'max' => 150 ),
				),
			),
			'signature'         => array(
				'label'    => __( 'Signature line', 'order-documents' ),
				'icon'     => 'edit-large',
				'settings' => array(
					'label'          => array( 'type' => 'text', 'label' => __( 'Label', 'order-documents' ), 'default' => '', 'placeholder' => __( 'Signature', 'order-documents' ) ),
					'show_date_line' => array( 'type' => 'checkbox', 'label' => __( 'Date line', 'order-documents' ), 'default' => 1 ),
				),
			),
			'paid_stamp'        => array(
				'label'    => __( 'Paid stamp', 'order-documents' ),
				'icon'     => 'yes-alt',
				'settings' => array(
					'text'         => array( 'type' => 'text', 'label' => __( 'Text (empty = receipt setting)', 'order-documents' ), 'default' => '' ),
					'color'        => array( 'type' => 'color', 'label' => __( 'Colour', 'order-documents' ), 'default' => '#15803d' ),
					'only_if_paid' => array( 'type' => 'checkbox', 'label' => __( 'Only when the order is paid', 'order-documents' ), 'default' => 1 ),
				),
			),
			'shape'             => array(
				'label'    => __( 'Shape', 'order-documents' ),
				'icon'     => 'admin-appearance',
				'common'   => false,
				'settings' => array(
					'fill'         => array( 'type' => 'color', 'label' => __( 'Fill', 'order-documents' ), 'default' => 'tint' ),
					'border_color' => array( 'type' => 'color', 'label' => __( 'Border colour', 'order-documents' ), 'default' => '' ),
					'border_width' => array( 'type' => 'number', 'label' => __( 'Border width (pt)', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 10, 'step' => 0.25 ),
					'radius'       => array( 'type' => 'number', 'label' => __( 'Corner radius (mm)', 'order-documents' ), 'default' => 0, 'min' => 0, 'max' => 50, 'step' => 0.5 ),
				),
			),
			'footer'            => array(
				'label'    => __( 'Footer', 'order-documents' ),
				'icon'     => 'editor-insertmore',
				'settings' => array(
					'content'     => array( 'type' => 'textarea', 'label' => __( 'Text (empty = footer from the general settings)', 'order-documents' ), 'default' => '' ),
					'fixed'       => array( 'type' => 'checkbox', 'label' => __( 'At the bottom of every page', 'order-documents' ), 'default' => 1 ),
					'show_border' => array( 'type' => 'checkbox', 'label' => __( 'Line above', 'order-documents' ), 'default' => 1 ),
					'layout'      => array(
						'type'    => 'select',
						'label'   => __( 'Layout', 'order-documents' ),
						'default' => 'text',
						'options' => array(
							'text'    => __( 'Footer text', 'order-documents' ),
							'columns' => __( 'Shop details in three columns', 'order-documents' ),
						),
					),
				),
			),
			'shipping_info'     => array(
				'label'    => __( 'Shipping details', 'order-documents' ),
				'icon'     => 'airplane',
				'settings' => array_merge(
					$heading( __( 'Delivery', 'order-documents' ) ),
					array(
						'show_method'   => array( 'type' => 'checkbox', 'label' => __( 'Shipping method', 'order-documents' ), 'default' => 1 ),
						'show_weight'   => array( 'type' => 'checkbox', 'label' => __( 'Total weight', 'order-documents' ), 'default' => 1 ),
						'show_quantity' => array( 'type' => 'checkbox', 'label' => __( 'Total quantity', 'order-documents' ), 'default' => 0 ),
						'tracking_meta' => array( 'type' => 'text', 'label' => __( 'Tracking number field (order meta key)', 'order-documents' ), 'default' => '', 'placeholder' => '_tracking_number' ),
						'text'          => array( 'type' => 'textarea', 'label' => __( 'Extra text (placeholders allowed)', 'order-documents' ), 'default' => '' ),
					)
				),
			),
			'item_count'        => array(
				'label'    => __( 'Quantity summary', 'order-documents' ),
				'icon'     => 'editor-ol',
				'settings' => array(
					'show_lines' => array( 'type' => 'checkbox', 'label' => __( 'Number of lines', 'order-documents' ), 'default' => 1 ),
					'unit'       => array( 'type' => 'text', 'label' => __( 'Unit after the quantity', 'order-documents' ), 'default' => '', 'placeholder' => __( 'pcs', 'order-documents' ) ),
				),
			),
			'payment_summary'   => array(
				'label'    => __( 'Payment band', 'order-documents' ),
				'icon'     => 'money-alt',
				'settings' => array(
					'account_label'   => array( 'type' => 'text', 'label' => __( 'Account label', 'order-documents' ), 'default' => '', 'placeholder' => __( 'Bank account', 'order-documents' ) ),
					'reference_label' => array( 'type' => 'text', 'label' => __( 'Reference label', 'order-documents' ), 'default' => '', 'placeholder' => __( 'Payment reference', 'order-documents' ) ),
					'show_due'        => array( 'type' => 'checkbox', 'label' => __( 'Due date', 'order-documents' ), 'default' => 1 ),
					'show_amount'     => array( 'type' => 'checkbox', 'label' => __( 'Amount to pay', 'order-documents' ), 'default' => 1 ),
					'hide_if_paid'    => array( 'type' => 'checkbox', 'label' => __( 'Hide when the order is paid', 'order-documents' ), 'default' => 0 ),
				),
			),
			'signature_fields'  => array(
				'label'    => __( 'Signature fields', 'order-documents' ),
				'icon'     => 'welcome-write-blog',
				'settings' => array(
					'heading'  => array( 'type' => 'text', 'label' => __( 'Heading', 'order-documents' ), 'default' => '' ),
					'labels'   => array( 'type' => 'text', 'label' => __( 'Fields (comma separated)', 'order-documents' ), 'default' => '', 'placeholder' => __( 'Signature, Name in print, Date', 'order-documents' ) ),
					'position' => array(
						'type'    => 'select',
						'label'   => __( 'Label position', 'order-documents' ),
						'default' => 'below',
						'options' => array(
							'above' => __( 'Above the line', 'order-documents' ),
							'below' => __( 'Below the line', 'order-documents' ),
						),
					),
				),
			),
		);

		$sizes = self::default_sizes();
		foreach ( $types as $id => &$type ) {
			if ( false !== ( $type['common'] ?? true ) ) {
				$type['settings'] = array_merge( $type['settings'], self::common_settings() );
			}
			$type['common'] = false !== ( $type['common'] ?? true );
			$type['size']   = $type['size'] ?? ( $sizes[ $id ] ?? array( 70, 15 ) );
			$type['id']     = $id;
		}
		unset( $type );
		return apply_filters( 'odocs_block_types', $types );
	}

	/**
	 * Size (width, height in mm) of a newly inserted component.
	 */
	private static function default_sizes(): array {
		return array(
			'logo'             => array( 50, 18 ),
			'shop_info'        => array( 60, 30 ),
			'document_title'   => array( 90, 12 ),
			'billing_address'  => array( 58, 28 ),
			'shipping_address' => array( 58, 28 ),
			'document_data'    => array( 60, 28 ),
			'items_table'      => array( 182, 40 ),
			'totals'           => array( 75, 32 ),
			'vat_summary'      => array( 90, 20 ),
			'customer_note'    => array( 90, 16 ),
			'document_notes'   => array( 90, 16 ),
			'payment_info'     => array( 90, 20 ),
			'document_notice'  => array( 90, 10 ),
			'custom_text'      => array( 70, 10 ),
			'divider'          => array( 182, 2 ),
			'spacer'           => array( 182, 5 ),
			'signature'        => array( 80, 20 ),
			'paid_stamp'       => array( 40, 12 ),
			'shape'            => array( 60, 20 ),
			'footer'           => array( 182, 12 ),
			'shipping_info'    => array( 70, 25 ),
			'item_count'       => array( 60, 8 ),
			'payment_summary'  => array( 182, 18 ),
			'signature_fields' => array( 182, 22 ),
		);
	}

	/**
	 * Item table columns.
	 */
	public static function column_types(): array {
		return apply_filters(
			'odocs_column_types',
			array(
				'position'        => array( 'label' => '#', 'width' => 5, 'align' => 'left' ),
				'checkbox'        => array( 'label' => __( 'Picked', 'order-documents' ), 'width' => 7, 'align' => 'center' ),
				'image'           => array( 'label' => __( 'Image', 'order-documents' ), 'width' => 10, 'align' => 'left' ),
				'sku'             => array( 'label' => __( 'SKU', 'order-documents' ), 'width' => 12, 'align' => 'left' ),
				'location'        => array( 'label' => __( 'Location', 'order-documents' ), 'width' => 12, 'align' => 'left' ),
				'description'     => array( 'label' => __( 'Product', 'order-documents' ), 'width' => 0, 'align' => 'left' ),
				'quantity'        => array( 'label' => __( 'Qty', 'order-documents' ), 'width' => 8, 'align' => 'right' ),
				'weight'          => array( 'label' => __( 'Weight', 'order-documents' ), 'width' => 10, 'align' => 'right' ),
				'unit_price'      => array( 'label' => __( 'Unit price', 'order-documents' ), 'width' => 14, 'align' => 'right' ),
				'unit_price_excl' => array( 'label' => __( 'Unit price excl. tax', 'order-documents' ), 'width' => 14, 'align' => 'right' ),
				'unit_price_incl' => array( 'label' => __( 'Unit price incl. tax', 'order-documents' ), 'width' => 14, 'align' => 'right' ),
				'discount'        => array( 'label' => __( 'Discount', 'order-documents' ), 'width' => 12, 'align' => 'right' ),
				'tax_rate'        => array( 'label' => __( 'VAT %', 'order-documents' ), 'width' => 9, 'align' => 'right' ),
				'tax'             => array( 'label' => __( 'VAT', 'order-documents' ), 'width' => 12, 'align' => 'right' ),
				'total'           => array( 'label' => __( 'Amount', 'order-documents' ), 'width' => 15, 'align' => 'right' ),
				'total_excl'      => array( 'label' => __( 'Amount excl. tax', 'order-documents' ), 'width' => 15, 'align' => 'right' ),
				'total_incl'      => array( 'label' => __( 'Amount incl. tax', 'order-documents' ), 'width' => 15, 'align' => 'right' ),
				'orders'          => array( 'label' => __( 'Orders', 'order-documents' ), 'width' => 14, 'align' => 'left' ),
				'delivered'       => array( 'label' => __( 'Delivered', 'order-documents' ), 'width' => 12, 'align' => 'center' ),
			)
		);
	}

	/**
	 * Builds a full column list with the given keys enabled (in that order) and the rest disabled.
	 */
	public static function columns( array $enabled_keys ): array {
		$types   = self::column_types();
		$columns = array();
		foreach ( $enabled_keys as $key ) {
			if ( isset( $types[ $key ] ) ) {
				$columns[] = array( 'key' => $key, 'label' => '', 'width' => 0, 'align' => '', 'enabled' => 1 );
			}
		}
		foreach ( array_keys( $types ) as $key ) {
			if ( ! in_array( $key, $enabled_keys, true ) ) {
				$columns[] = array( 'key' => $key, 'label' => '', 'width' => 0, 'align' => '', 'enabled' => 0 );
			}
		}
		return $columns;
	}

	/* ------------------------------------------------------------------
	 * Sanitizing
	 * ------------------------------------------------------------------ */

	public static function sanitize( $layout, string $type = '' ): array {
		$layout = is_array( $layout ) ? $layout : array();
		if ( isset( $layout['sections'] ) ) {
			return self::sanitize_sections( $layout, $type );
		}
		$rows   = array();
		$types  = self::block_types();
		foreach ( array_values( (array) ( $layout['rows'] ?? array() ) ) as $r_index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$columns = array();
			foreach ( array_slice( array_values( (array) ( $row['columns'] ?? array() ) ), 0, 3 ) as $column ) {
				$blocks = array();
				foreach ( array_values( (array) ( $column['blocks'] ?? array() ) ) as $b_index => $block ) {
					$block_type = sanitize_key( $block['type'] ?? '' );
					if ( ! isset( $types[ $block_type ] ) ) {
						continue;
					}
					$blocks[] = array(
						'id'       => self::sanitize_id( $block['id'] ?? '', 'b' . $r_index . '_' . $b_index . '_' . $block_type ),
						'type'     => $block_type,
						'settings' => self::sanitize_settings( (array) ( $block['settings'] ?? array() ), $types[ $block_type ]['settings'], $block_type, $type ),
					);
				}
				$columns[] = array(
					'width'  => max( 5, min( 100, (float) ( $column['width'] ?? 0 ) ) ),
					'valign' => in_array( $column['valign'] ?? 'top', array( 'top', 'middle', 'bottom' ), true ) ? ( $column['valign'] ?? 'top' ) : 'top',
					'blocks' => $blocks,
				);
			}
			if ( ! $columns ) {
				continue;
			}
			// Normalise widths to 100%.
			$total = array_sum( wp_list_pluck( $columns, 'width' ) );
			foreach ( $columns as &$column ) {
				$column['width'] = round( $column['width'] / max( 1, $total ) * 100, 2 );
			}
			unset( $column );
			$settings = (array) ( $row['settings'] ?? array() );
			$rows[]   = array(
				'id'       => self::sanitize_id( $row['id'] ?? '', 'r' . $r_index ),
				'settings' => array(
					'background'    => self::sanitize_color( $settings['background'] ?? '' ),
					'text_color'    => self::sanitize_color( $settings['text_color'] ?? '' ),
					'padding'       => max( 0, min( 30, (float) ( $settings['padding'] ?? 0 ) ) ),
					'margin_bottom' => max( 0, min( 60, (float) ( $settings['margin_bottom'] ?? 4 ) ) ),
					'border'        => ! empty( $settings['border'] ) ? 1 : 0,
					'bleed'         => ! empty( $settings['bleed'] ) ? 1 : 0,
				),
				'columns'  => $columns,
			);
		}
		return array( 'version' => 1, 'rows' => $rows );
	}

	/**
	 * Sanitizes a version 2 (free-form sections) layout.
	 *
	 * @param array  $layout Layout with "sections".
	 * @param string $type   Document type.
	 */
	private static function sanitize_sections( array $layout, string $type ): array {
		$types      = self::block_types();
		$sections   = array();
		$has_footer = false;
		foreach ( array_slice( array_values( (array) $layout['sections'] ), 0, 20 ) as $s_index => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$kind = in_array( $section['kind'] ?? '', array( 'canvas', 'flow', 'footer' ), true ) ? $section['kind'] : 'canvas';
			if ( 'footer' === $kind ) {
				$kind       = $has_footer ? 'canvas' : 'footer';
				$has_footer = true;
			}
			$items = array();
			foreach ( array_slice( array_values( (array) ( $section['items'] ?? array() ) ), 0, 80 ) as $i_index => $item ) {
				$block_type = is_array( $item ) ? sanitize_key( $item['type'] ?? '' ) : '';
				if ( ! isset( $types[ $block_type ] ) ) {
					continue;
				}
				$size    = $types[ $block_type ]['size'];
				$items[] = array(
					'id'       => self::sanitize_id( $item['id'] ?? '', 'i' . $s_index . '_' . $i_index . '_' . $block_type ),
					'type'     => $block_type,
					'settings' => self::sanitize_settings( (array) ( $item['settings'] ?? array() ), $types[ $block_type ]['settings'], $block_type, $type ),
					'x'        => self::clamp( $item['x'] ?? 0, -60, 400 ),
					'y'        => self::clamp( $item['y'] ?? 0, 0, 1500 ),
					'w'        => self::clamp( $item['w'] ?? $size[0], 2, 400 ),
					'h'        => self::clamp( $item['h'] ?? $size[1], 1, 1500 ),
					'snap'     => array_key_exists( 'snap', $item ) && empty( $item['snap'] ) ? 0 : 1,
					'locked'   => empty( $item['locked'] ) ? 0 : 1,
					'overflow' => 'clip' === ( $item['overflow'] ?? '' ) ? 'clip' : 'grow',
				);
			}
			$sections[] = array(
				'id'            => self::sanitize_id( $section['id'] ?? '', 's' . $s_index ),
				'kind'          => $kind,
				'name'          => substr( sanitize_text_field( (string) ( $section['name'] ?? '' ) ), 0, 60 ),
				'height'        => self::clamp( $section['height'] ?? 0, 0, 1500 ),
				'keep_together' => array_key_exists( 'keep_together', $section ) && empty( $section['keep_together'] ) ? 0 : 1,
				'items'         => $items,
			);
		}
		return array( 'version' => 2, 'sections' => $sections );
	}

	/**
	 * Number limited to a range and rounded to 0.01.
	 *
	 * @param mixed $value Value.
	 */
	private static function clamp( $value, float $min, float $max ): float {
		$value = is_numeric( $value ) ? (float) $value : max( $min, 0.0 );
		return round( max( $min, min( $max, $value ) ), 2 );
	}

	/**
	 * Hex colour, or the keyword "accent" (follows the design's accent colour).
	 *
	 * @param mixed $value Colour.
	 */
	public static function sanitize_color( $value ): string {
		$value = trim( (string) $value );
		if ( in_array( $value, array( 'accent', 'accent-light', 'tint', 'line' ), true ) ) {
			return $value;
		}
		return sanitize_hex_color( $value ) ?: '';
	}

	private static function sanitize_id( $id, string $fallback ): string {
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $id );
		return substr( '' !== $id ? $id : $fallback, 0, 60 );
	}

	/**
	 * @param array  $values     Submitted values.
	 * @param array  $schema     Settings schema.
	 * @param string $block_type Block type.
	 * @param string $doc_type   Document type (for column defaults).
	 */
	public static function sanitize_settings( array $values, array $schema, string $block_type, string $doc_type = '' ): array {
		$clean = array();
		foreach ( $schema as $key => $field ) {
			$raw = array_key_exists( $key, $values ) ? $values[ $key ] : ( $field['default'] ?? '' );
			switch ( $field['type'] ) {
				case 'checkbox':
					$clean[ $key ] = ! empty( $raw ) ? 1 : 0;
					break;
				case 'number':
					$value = is_numeric( $raw ) ? (float) $raw : (float) ( $field['default'] ?? 0 );
					$value = isset( $field['min'] ) ? max( (float) $field['min'], $value ) : $value;
					$value = isset( $field['max'] ) ? min( (float) $field['max'], $value ) : $value;
					$clean[ $key ] = $value;
					break;
				case 'select':
					$clean[ $key ] = array_key_exists( (string) $raw, $field['options'] ) ? (string) $raw : (string) $field['default'];
					break;
				case 'color':
					$clean[ $key ] = self::sanitize_color( $raw );
					break;
				case 'textarea':
					$clean[ $key ] = wp_kses( (string) $raw, self::allowed_html() );
					break;
				case 'columns':
					$clean[ $key ] = self::sanitize_columns( is_array( $raw ) ? $raw : array() );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( (string) $raw );
			}
		}
		return $clean;
	}

	public static function sanitize_columns( array $columns ): array {
		$types = self::column_types();
		$clean = array();
		$seen  = array();
		foreach ( $columns as $column ) {
			$key = sanitize_key( $column['key'] ?? '' );
			if ( ! isset( $types[ $key ] ) || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$clean[]      = array(
				'key'     => $key,
				'label'   => sanitize_text_field( (string) ( $column['label'] ?? '' ) ),
				'width'   => max( 0, min( 80, (float) ( $column['width'] ?? 0 ) ) ),
				'align'   => in_array( $column['align'] ?? '', array( '', 'left', 'center', 'right' ), true ) ? (string) ( $column['align'] ?? '' ) : '',
				'enabled' => ! empty( $column['enabled'] ) ? 1 : 0,
			);
		}
		// Make sure every known column is listed, so it can be switched on in the editor.
		foreach ( array_keys( $types ) as $key ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$clean[] = array( 'key' => $key, 'label' => '', 'width' => 0, 'align' => '', 'enabled' => 0 );
			}
		}
		return $clean;
	}

	public static function allowed_html(): array {
		return array(
			'a'      => array( 'href' => true, 'style' => true ),
			'b'      => array(),
			'strong' => array( 'style' => true ),
			'i'      => array(),
			'em'     => array(),
			'u'      => array(),
			'br'     => array(),
			'p'      => array( 'style' => true ),
			'span'   => array( 'style' => true ),
			'div'    => array( 'style' => true ),
			'small'  => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'h1'     => array( 'style' => true ),
			'h2'     => array( 'style' => true ),
			'h3'     => array( 'style' => true ),
			'h4'     => array( 'style' => true ),
			'hr'     => array( 'style' => true ),
			'table'  => array( 'style' => true ),
			'tr'     => array(),
			'td'     => array( 'style' => true, 'colspan' => true ),
			'th'     => array( 'style' => true, 'colspan' => true ),
			'img'    => array( 'src' => true, 'style' => true, 'alt' => true, 'width' => true, 'height' => true ),
		);
	}
}
