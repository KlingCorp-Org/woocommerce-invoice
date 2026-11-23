<?php
/**
 * Default layouts for each template and document type.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

defined( 'ABSPATH' ) || exit;

class DefaultLayouts {

	public static function get( string $template, string $type ): array {
		$meta    = TemplateRegistry::get( $template );
		$variant = $meta['variant'] ?? $template;
		$method  = 'layout_' . str_replace( '-', '_', (string) $variant );
		if ( 'picking_list' === $type && 'receipt_80mm' !== str_replace( '-', '_', (string) $variant ) ) {
			$layout = self::picking_list( $variant );
		} elseif ( method_exists( __CLASS__, $method ) ) {
			$layout = self::$method( $type );
		} else {
			$layout = self::layout_classic( $type );
		}
		return apply_filters( 'odocs_default_layout', $layout, $template, $type );
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	private static function b( string $type, array $settings = array() ): array {
		return array( 'type' => $type, 'settings' => $settings );
	}

	/**
	 * @param array $columns  List of [width, blocks[]].
	 * @param array $settings Row settings.
	 */
	private static function row( array $columns, array $settings = array() ): array {
		$cols = array();
		foreach ( $columns as $column ) {
			$cols[] = array( 'width' => $column[0], 'blocks' => $column[1], 'valign' => $column[2] ?? 'top' );
		}
		return array( 'settings' => array_merge( array( 'margin_bottom' => 5 ), $settings ), 'columns' => $cols );
	}

	private static function has_prices( string $type ): bool {
		return ! in_array( $type, array( 'packing_slip', 'picking_list' ), true );
	}

	public static function item_columns( string $type ): array {
		switch ( $type ) {
			case 'packing_slip':
				return Layout::columns( array( 'description', 'quantity' ) );
			case 'picking_list':
				return Layout::columns( array( 'checkbox', 'location', 'description', 'quantity', 'orders' ) );
			case 'receipt':
				return Layout::columns( array( 'description', 'quantity', 'total' ) );
			case 'delivery_note':
				return Layout::columns( array( 'description', 'quantity', 'unit_price', 'total' ) );
			default:
				return Layout::columns( array( 'description', 'quantity', 'unit_price', 'tax_rate', 'total' ) );
		}
	}

	/** Address + document data row. */
	private static function addresses( string $type, array $data_settings = array(), array $address_settings = array() ): array {
		$billing  = self::b( 'billing_address', $address_settings );
		$shipping = self::b( 'shipping_address', $address_settings );
		$data     = self::b( 'document_data', $data_settings );
		if ( 'packing_slip' === $type ) {
			return self::row( array( array( 34, array( $shipping ) ), array( 33, array( $billing ) ), array( 33, array( $data ) ) ), array( 'margin_bottom' => 7 ) );
		}
		return self::row( array( array( 34, array( $billing ) ), array( 33, array( $shipping ) ), array( 33, array( $data ) ) ), array( 'margin_bottom' => 7 ) );
	}

	/** Notes / totals rows after the products table. */
	private static function after_items( string $type, array $totals_settings = array() ): array {
		$left = array( self::b( 'document_notice' ) );
		if ( in_array( $type, array( 'invoice', 'credit_note' ), true ) ) {
			$left[] = self::b( 'vat_summary' );
		}
		$left[] = self::b( 'customer_note' );
		$left[] = self::b( 'document_notes' );
		if ( in_array( $type, array( 'invoice', 'proforma' ), true ) ) {
			$left[] = self::b( 'payment_info' );
		}
		if ( ! self::has_prices( $type ) ) {
			return array( self::row( array( array( 100, $left ) ) ) );
		}
		$right = array( self::b( 'totals', $totals_settings ) );
		if ( 'receipt' === $type ) {
			$right[] = self::b( 'paid_stamp', array( 'align' => 'right' ) );
		}
		return array( self::row( array( array( 55, $left ), array( 45, $right ) ) ) );
	}

	private static function items( string $type, array $settings = array() ): array {
		return self::row( array( array( 100, array( self::b( 'items_table', array_merge( array( 'columns' => self::item_columns( $type ) ), $settings ) ) ) ) ), array( 'margin_bottom' => 4 ) );
	}

	private static function footer( array $settings = array() ): array {
		return self::row( array( array( 100, array( self::b( 'footer', array_merge( array( 'align' => 'center' ), $settings ) ) ) ) ), array( 'margin_bottom' => 0 ) );
	}

	/* ------------------------------------------------------------------
	 * Template variants
	 * ------------------------------------------------------------------ */

	private static function layout_classic( string $type ): array {
		return array(
			'rows' => array_merge(
				array(
					self::row( array( array( 50, array( self::b( 'logo' ) ) ), array( 50, array( self::b( 'shop_info', array( 'align' => 'right' ) ) ) ) ), array( 'margin_bottom' => 8 ) ),
					self::row( array( array( 100, array( self::b( 'document_title', array( 'font_size' => 18 ) ) ) ) ), array( 'margin_bottom' => 4 ) ),
					self::addresses( $type ),
					self::items( $type ),
				),
				self::after_items( $type ),
				array( self::footer() )
			),
		);
	}

	private static function layout_modern( string $type ): array {
		return array(
			'rows' => array_merge(
				array(
					self::row( array( array( 50, array( self::b( 'logo' ) ), 'middle' ), array( 50, array( self::b( 'shop_info', array( 'align' => 'right', 'show_vat_number' => 0 ) ) ), 'middle' ) ), array( 'margin_bottom' => 6 ) ),
					self::row(
						array( array( 100, array( self::b( 'document_title', array( 'uppercase' => 1, 'show_number' => 1, 'font_size' => 16, 'margin_bottom' => 0 ) ) ) ) ),
						array( 'background' => 'accent', 'text_color' => '#ffffff', 'padding' => 4, 'margin_bottom' => 7, 'bleed' => 1 )
					),
					self::addresses( $type ),
					self::items( $type ),
				),
				self::after_items( $type ),
				array( self::footer() )
			),
		);
	}

	private static function layout_minimal( string $type ): array {
		$billing_col = 'packing_slip' === $type
			? array( self::b( 'shipping_address' ), self::b( 'billing_address' ) )
			: array( self::b( 'billing_address' ), self::b( 'shipping_address' ) );
		return array(
			'rows' => array_merge(
				array(
					self::row( array( array( 60, array( self::b( 'document_title', array( 'font_size' => 24 ) ) ), 'bottom' ), array( 40, array( self::b( 'logo', array( 'align' => 'right' ) ) ), 'bottom' ) ), array( 'margin_bottom' => 10 ) ),
					self::row( array( array( 33, array( self::b( 'shop_info', array( 'show_vat_number' => 0, 'show_register_text' => 0 ) ) ) ), array( 34, $billing_col ), array( 33, array( self::b( 'document_data' ) ) ) ), array( 'margin_bottom' => 10 ) ),
					self::items( $type ),
				),
				self::after_items( $type ),
				array( self::footer( array( 'show_border' => 0 ) ) )
			),
		);
	}

	private static function layout_business( string $type ): array {
		$address_cols = 'packing_slip' === $type
			? array( array( 50, array( self::b( 'shipping_address' ) ) ), array( 50, array( self::b( 'billing_address' ) ) ) )
			: array( array( 50, array( self::b( 'billing_address' ) ) ), array( 50, array( self::b( 'shipping_address' ) ) ) );
		return array(
			'rows' => array_merge(
				array(
					self::row(
						array(
							array( 50, array( self::b( 'logo', array( 'margin_bottom' => 3 ) ), self::b( 'shop_info' ) ) ),
							array( 50, array( self::b( 'document_title', array( 'align' => 'right', 'font_size' => 20, 'uppercase' => 1 ) ), self::b( 'document_data', array( 'boxed' => 1 ) ) ) ),
						),
						array( 'margin_bottom' => 6 )
					),
					self::row( $address_cols, array( 'border' => 1, 'padding' => 3, 'margin_bottom' => 6 ) ),
					self::items( $type ),
				),
				self::after_items( $type ),
				array( self::footer() )
			),
		);
	}

	private static function layout_compact( string $type ): array {
		return array(
			'rows' => array_merge(
				array(
					self::row(
						array(
							array( 22, array( self::b( 'logo', array( 'max_height' => 12 ) ) ) ),
							array( 38, array( self::b( 'shop_info', array( 'show_vat_number' => 0, 'show_register_text' => 0 ) ) ) ),
							array( 40, array( self::b( 'document_title', array( 'font_size' => 14, 'align' => 'right', 'margin_bottom' => 1 ) ), self::b( 'document_data', array( 'align' => 'right' ) ) ) ),
						),
						array( 'margin_bottom' => 4 )
					),
					self::row(
						'packing_slip' === $type
							? array( array( 50, array( self::b( 'shipping_address' ) ) ), array( 50, array( self::b( 'billing_address' ) ) ) )
							: array( array( 50, array( self::b( 'billing_address' ) ) ), array( 50, array( self::b( 'shipping_address' ) ) ) ),
						array( 'margin_bottom' => 4 )
					),
					self::items( $type, array( 'image_size' => 9 ) ),
				),
				self::after_items( $type ),
				array( self::footer() )
			),
		);
	}

	/**
	 * Fresh: logo left, big title right, green rule, data columns, rounded boxes, product images,
	 * large total, payment band (invoices) or signature fields (packing slips, delivery notes).
	 */
	private static function layout_fresh( string $type ): array {
		$is_shipping_doc = in_array( $type, array( 'packing_slip', 'delivery_note' ), true );
		$rows            = array();

		$rows[] = self::row(
			array(
				array( 50, array( self::b( 'logo', array( 'margin_bottom' => 0 ) ) ) ),
				array( 50, array( self::b( 'document_title', array( 'align' => 'right', 'uppercase' => 1, 'subline' => $is_shipping_doc ? 'order' : 'number', 'margin_bottom' => 0 ) ) ) ),
			),
			array( 'margin_bottom' => 0 )
		);
		$rows[] = self::row( array( array( 100, array( self::b( 'divider', array( 'color' => 'accent-light', 'thickness' => 1.5, 'margin_bottom' => 4.5 ) ) ) ) ), array( 'margin_bottom' => 0 ) );

		$data = array(
			'layout'              => 'grid',
			'grid_columns'        => 4,
			'show_number'         => 0,
			'show_order_date'     => $is_shipping_doc ? 1 : 0,
			'show_order_number'   => $is_shipping_doc ? 0 : 1,
			'show_payment_method' => 0,
			'exclude'             => 'kid',
			'margin_bottom'       => 4,
		);
		$rows[] = self::row( array( array( 100, array( self::b( 'document_data', $data ) ) ) ), array( 'margin_bottom' => 0 ) );

		if ( $is_shipping_doc ) {
			$info = array( 'box' => 'border', 'show_weight' => 'packing_slip' === $type ? 1 : 1, 'show_quantity' => 'packing_slip' === $type ? 1 : 0 );
			$rows[] = self::row(
				array(
					array( 50, array( self::b( 'shipping_address', array( 'box' => 'filled', 'heading' => __( 'Delivery address', 'order-documents' ), 'margin_bottom' => 0 ) ) ) ),
					array( 50, array( self::b( 'shipping_info', $info + array( 'margin_bottom' => 0 ) ) ) ),
				),
				array( 'margin_bottom' => 4 )
			);
		} else {
			$rows[] = self::row(
				array(
					array( 50, array( self::b( 'billing_address', array( 'box' => 'border', 'compact' => 1, 'margin_bottom' => 0 ) ) ) ),
					array( 50, array( self::b( 'shipping_address', array( 'box' => 'border', 'compact' => 1, 'heading' => __( 'Delivery address', 'order-documents' ), 'margin_bottom' => 0 ) ) ) ),
				),
				array( 'margin_bottom' => 4 )
			);
		}

		if ( 'packing_slip' === $type ) {
			$columns = self::label_columns(
				Layout::columns( array( 'image', 'description', 'quantity', 'delivered', 'checkbox' ) ),
				array(
					'quantity' => __( 'Ordered', 'order-documents' ),
					'checkbox' => __( 'Done', 'order-documents' ),
				)
			);
			$image = 14.8;
		} else {
			$columns = Layout::columns( array( 'image', 'description', 'quantity', 'unit_price', 'total' ) );
			$image   = 'delivery_note' === $type ? 12.7 : 11.6;
		}
		$rows[] = self::row( array( array( 100, array( self::b( 'items_table', array( 'columns' => $columns, 'image_size' => $image, 'margin_bottom' => 2 ) ) ) ) ), array( 'margin_bottom' => 2 ) );

		$left = array( self::b( 'document_notice' ), self::b( 'payment_info', array( 'show_heading' => 0 ) ), self::b( 'customer_note' ), self::b( 'document_notes' ) );
		if ( 'packing_slip' === $type ) {
			$rows[] = self::row( array( array( 60, $left ), array( 40, array( self::b( 'item_count' ) ) ) ), array( 'margin_bottom' => 5 ) );
			$rows[] = self::row(
				array(
					array(
						100,
						array(
							self::b(
								'signature_fields',
								array(
									'box'      => 'filled',
									'labels'   => implode( ', ', array( __( 'Picked by', 'order-documents' ), __( 'Checked by', 'order-documents' ), __( 'Received by / date', 'order-documents' ) ) ),
									'position' => 'above',
								)
							),
						),
					),
				)
			);
		} else {
			$right = array( self::b( 'totals', array( 'show_subtotal' => 1 ) ) );
			if ( 'receipt' === $type ) {
				$right[] = self::b( 'paid_stamp', array( 'align' => 'right' ) );
			}
			$rows[] = self::row( array( array( 55, $left ), array( 45, $right ) ), array( 'margin_bottom' => 2.5 ) );
			if ( in_array( $type, array( 'invoice', 'proforma' ), true ) ) {
				$rows[] = self::row( array( array( 100, array( self::b( 'payment_summary', array( 'box' => 'filled', 'hide_if_paid' => 0 ) ) ) ) ) );
			} elseif ( 'delivery_note' === $type ) {
				$rows[] = self::row( array( array( 100, array( self::b( 'signature_fields', array( 'box' => 'filled', 'heading' => __( 'Goods received', 'order-documents' ), 'position' => 'below' ) ) ) ) ) );
			}
		}

		$rows[] = self::footer( array( 'layout' => 'columns', 'align' => 'left' ) );
		return array( 'rows' => $rows );
	}

	/**
	 * Sets custom headings on columns from Layout::columns().
	 */
	private static function label_columns( array $columns, array $labels ): array {
		foreach ( $columns as &$column ) {
			if ( isset( $labels[ $column['key'] ] ) ) {
				$column['label'] = $labels[ $column['key'] ];
			}
		}
		return $columns;
	}

	private static function layout_receipt_80mm( string $type ): array {
		$blocks = array(
			self::b( 'logo', array( 'align' => 'center', 'max_height' => 14 ) ),
			self::b( 'shop_info', array( 'align' => 'center', 'show_vat_number' => 0, 'show_register_text' => 0 ) ),
			self::b( 'divider', array( 'style' => 'dashed' ) ),
			self::b( 'document_title', array( 'align' => 'center', 'font_size' => 12, 'uppercase' => 1 ) ),
			self::b( 'document_data' ),
		);
		if ( in_array( $type, array( 'packing_slip', 'delivery_note' ), true ) ) {
			$blocks[] = self::b( 'shipping_address' );
		}
		$blocks[] = self::b( 'divider', array( 'style' => 'dashed' ) );
		$blocks[] = self::b( 'items_table', array( 'columns' => 'picking_list' === $type ? Layout::columns( array( 'checkbox', 'description', 'quantity' ) ) : ( self::has_prices( $type ) ? Layout::columns( array( 'description', 'quantity', 'total' ) ) : Layout::columns( array( 'description', 'quantity' ) ) ) ) );
		if ( self::has_prices( $type ) ) {
			$blocks[] = self::b( 'divider', array( 'style' => 'dashed' ) );
			$blocks[] = self::b( 'totals' );
		}
		if ( 'receipt' === $type ) {
			$blocks[] = self::b( 'paid_stamp', array( 'align' => 'center' ) );
		}
		$blocks[] = self::b( 'document_notice' );
		$blocks[] = self::b( 'customer_note' );
		$blocks[] = self::b( 'payment_info' );
		$blocks[] = self::b( 'footer', array( 'align' => 'center', 'fixed' => 0, 'show_border' => 0 ) );
		return array( 'rows' => array( self::row( array( array( 100, $blocks ) ), array( 'margin_bottom' => 0 ) ) ) );
	}

	private static function picking_list( string $variant ): array {
		return array(
			'rows' => array(
				self::row( array( array( 60, array( self::b( 'document_title', array( 'font_size' => 16 ) ) ) ), array( 40, array( self::b( 'document_data', array( 'align' => 'right' ) ) ) ) ), array( 'margin_bottom' => 5 ) ),
				self::items( 'picking_list' ),
				self::row( array( array( 100, array( self::b( 'customer_note' ) ) ) ) ),
			),
		);
	}
}
