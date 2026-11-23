<?php
/**
 * Picking list for one or more orders. Not stored, not numbered.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class PickingList extends Document {

	/** @var \WC_Order[] */
	protected $orders = array();

	public function __construct( \WC_Order $order, ?\WC_Order_Refund $refund = null ) {
		parent::__construct( $order, null );
		$this->orders = array( $order );
	}

	public static function type(): string {
		return 'picking_list';
	}

	public static function label(): string {
		return __( 'Picking list', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Picking lists', 'order-documents' );
	}

	public static function icon(): string {
		return 'list-view';
	}

	public static function supports_numbering(): bool {
		return false;
	}

	public static function supports_emails(): bool {
		return false;
	}

	public static function shows_prices(): bool {
		return false;
	}

	public static function is_multi_order(): bool {
		return true;
	}

	public static function settings_fields(): array {
		return array(
			array( 'type' => 'heading', 'label' => __( 'General', 'order-documents' ) ),
			array( 'id' => 'enabled', 'type' => 'checkbox', 'label' => __( 'Enabled', 'order-documents' ), 'default' => 0 ),
			array( 'id' => 'title', 'type' => 'text', 'label' => __( 'Document title', 'order-documents' ), 'default' => '', 'placeholder' => self::label() ),
			array(
				'id'      => 'template',
				'type'    => 'select',
				'label'   => __( 'Template', 'order-documents' ),
				'default' => 'compact',
				'options' => static function () {
					return array( '' => __( 'Use the default template', 'order-documents' ) ) + \OrderDocs\Rendering\TemplateRegistry::options();
				},
			),
			array( 'id' => 'filename', 'type' => 'text', 'label' => __( 'File name', 'order-documents' ), 'default' => '{{document_title}}-{{date}}' ),
			array( 'type' => 'heading', 'label' => __( 'Picking', 'order-documents' ) ),
			array( 'id' => 'aggregate', 'type' => 'checkbox', 'label' => __( 'Combine products across orders', 'order-documents' ), 'description' => __( 'When printing several orders at once, identical products are added up in one line.', 'order-documents' ), 'default' => 1 ),
			array(
				'id'      => 'sort_by',
				'type'    => 'select',
				'label'   => __( 'Sort products by', 'order-documents' ),
				'default' => 'sku',
				'options' => array(
					'sku'      => __( 'SKU', 'order-documents' ),
					'name'     => __( 'Product name', 'order-documents' ),
					'category' => __( 'Category', 'order-documents' ),
					'location' => __( 'Stock location', 'order-documents' ),
					'order'    => __( 'Order', 'order-documents' ),
				),
			),
			array( 'id' => 'location_meta', 'type' => 'text', 'label' => __( 'Stock location field', 'order-documents' ), 'default' => '', 'description' => __( 'Product meta key or attribute slug (e.g. "_bin_location" or "pa_shelf") shown in the Location column.', 'order-documents' ) ),
			array( 'id' => 'show_sku', 'type' => 'checkbox', 'label' => __( 'SKU under the product name', 'order-documents' ), 'default' => 0 ),
			array( 'id' => 'show_weight', 'type' => 'checkbox', 'label' => __( 'Weight under the product name', 'order-documents' ), 'default' => 0 ),
			array( 'id' => 'show_item_meta', 'type' => 'checkbox', 'label' => __( 'Product options and variation details', 'order-documents' ), 'default' => 1 ),
		);
	}

	/**
	 * @param \WC_Order[] $orders Orders on this picking list.
	 */
	public function set_orders( array $orders ): void {
		$this->orders = array_values( array_filter( $orders, static fn( $o ) => $o instanceof \WC_Order ) );
		if ( $this->orders ) {
			$this->order = $this->orders[0];
		}
	}

	/**
	 * @return \WC_Order[]
	 */
	public function get_orders(): array {
		return $this->orders;
	}

	protected function load(): void {
		$this->data = array( 'date' => time() );
	}

	public function exists(): bool {
		return true;
	}

	public function is_allowed( ?string &$reason = null ): bool {
		if ( ! static::is_type_enabled() ) {
			$reason = __( 'This document type is disabled.', 'order-documents' );
			return false;
		}
		return true;
	}

	public function is_visible_to_customer(): bool {
		return false;
	}

	public function ensure( string $trigger = 'manual' ): bool {
		return $this->is_allowed();
	}

	public function create( string $trigger = 'manual', ?int $timestamp = null ): void {
	}

	public function save(): void {
	}

	public function delete(): void {
	}

	public function get_key(): string {
		return 'picking_list-' . implode( '-', array_map( static fn( $o ) => $o->get_id(), $this->orders ) );
	}

	public function data_rows(): array {
		$rows = array(
			'date' => array( 'label' => __( 'Printed', 'order-documents' ), 'value' => odocs_format_date( new \WC_DateTime( 'now', wp_timezone() ), true ) ),
		);
		if ( count( $this->orders ) > 1 ) {
			$rows['orders'] = array( 'label' => __( 'Orders', 'order-documents' ), 'value' => (string) count( $this->orders ) );
		}
		return $rows;
	}
}
