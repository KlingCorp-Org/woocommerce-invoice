<?php
/**
 * Orders list: document buttons in the Actions column, an invoice number column, bulk print actions
 * and invoice number search. Works on both the HPOS screen and the legacy posts screen.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Admin;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Documents\Invoice;

defined( 'ABSPATH' ) || exit;

class OrderListActions {

	public function register(): void {

		// Columns: HPOS + legacy.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ), 20 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column_hpos' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_column_legacy' ), 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_sortable_columns', array( $this, 'sortable' ) );
		add_filter( 'manage_edit-shop_order_sortable_columns', array( $this, 'sortable' ) );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $this, 'hpos_sort' ) );
		add_action( 'pre_get_posts', array( $this, 'legacy_sort' ) );

		// Bulk actions.
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_actions' ), 20 );
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'bulk_actions' ), 20 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk' ), 10, 3 );

		// Search by invoice number.
		add_filter( 'woocommerce_order_table_search_query_meta_keys', array( $this, 'search_keys' ) );
		add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'search_keys' ) );
	}

	/**
	 * Types that get a button per order in the list.
	 *
	 * @return array<string,string>
	 */
	protected function list_types(): array {
		$types = array();
		foreach ( DocumentRegistry::enabled() as $type => $class ) {
			$types[ $type ] = $class;
		}
		return apply_filters( 'odocs_list_action_types', $types );
	}

	/**
	 * @param \WC_Order $order Order.
	 */
	public function action_buttons( $order ): void {
		if ( ! $order instanceof \WC_Order || ! odocs_user_can_manage() ) {
			return;
		}
		foreach ( $this->list_types() as $type => $class ) {
			if ( $class::is_per_refund() && ! $order->get_refunds() ) {
				continue;
			}
			$document = DocumentRegistry::make( $type, $order, $class::is_per_refund() ? $order->get_refunds()[0] : null );
			if ( ! $document ) {
				continue;
			}
			$exists  = $document->exists() && ! $class::is_multi_order();
			if ( ! $exists && ! $document->is_allowed() ) {
				continue;
			}
			$label = $document->get_title() . ( $exists && $document->get_number() ? ' ' . $document->get_number() : '' );
			printf(
				'<a class="button tips odocs-list-action odocs-list-%1$s%2$s" href="%3$s" target="_blank" rel="noopener" data-tip="%4$s" aria-label="%4$s"><span class="dashicons dashicons-%5$s"></span></a>',
				esc_attr( $type ),
				$exists ? ' odocs-exists' : '',
				esc_url( odocs_document_url( $type, array( $order->get_id() ) ) ),
				esc_attr( $label ),
				esc_attr( $class::icon() )
			);
		}
	}

	public function add_column( $columns ) {
		if ( ! odocs_user_can_manage() ) {
			return $columns;
		}
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_number' === $key ) {
				$new['odocs_invoice_number'] = __( 'Documents', 'order-documents' );
			}
		}
		if ( ! isset( $new['odocs_invoice_number'] ) ) {
			$new['odocs_invoice_number'] = __( 'Documents', 'order-documents' );
		}
		return $new;
	}

	/**
	 * @param string    $column Column.
	 * @param \WC_Order $order  Order.
	 */
	public function render_column_hpos( $column, $order ): void {
		if ( 'odocs_invoice_number' === $column ) {
			$this->column_value( $order instanceof \WC_Order ? $order : wc_get_order( $order ) );
		}
	}

	public function render_column_legacy( $column, $post_id ): void {
		if ( 'odocs_invoice_number' === $column ) {
			$this->column_value( wc_get_order( $post_id ) );
		}
	}

	/**
	 * @param \WC_Order|false $order Order.
	 */
	protected function column_value( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$invoice = Invoice::is_type_enabled() ? DocumentRegistry::make( 'invoice', $order ) : null;
		if ( $invoice && $invoice->exists() ) {
			echo '<span class="odocs-invoice-number">' . esc_html( $invoice->get_number() ) . '</span> <small class="odocs-muted">' . esc_html( odocs_format_date( $invoice->get_date() ) ) . '</small>';
		}
		echo '<div class="odocs-list-actions">';
		$this->action_buttons( $order );
		echo '</div>';
	}

	public function sortable( $columns ) {
		$columns['odocs_invoice_number'] = 'odocs_invoice_number';
		return $columns;
	}

	public function hpos_sort( $args ) {
		if ( isset( $_GET['orderby'] ) && 'odocs_invoice_number' === $_GET['orderby'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_query'][] = array(
				'relation'       => 'OR',
				'odocs_inv'      => array( 'key' => '_odocs_invoice_seq', 'type' => 'NUMERIC', 'compare' => 'EXISTS' ),
				'odocs_inv_none' => array( 'key' => '_odocs_invoice_seq', 'compare' => 'NOT EXISTS' ),
			);
			$args['orderby'] = array( 'odocs_inv' => strtoupper( sanitize_key( wp_unslash( $_GET['order'] ?? 'desc' ) ) ) === 'ASC' ? 'ASC' : 'DESC' ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $args;
	}

	/**
	 * @param \WP_Query $query Query.
	 */
	public function legacy_sort( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || 'odocs_invoice_number' !== $query->get( 'orderby' ) || 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}
		$query->set(
			'meta_query',
			array(
				'relation'  => 'OR',
				'odocs_inv' => array( 'key' => '_odocs_invoice_seq', 'type' => 'NUMERIC', 'compare' => 'EXISTS' ),
				array( 'key' => '_odocs_invoice_seq', 'compare' => 'NOT EXISTS' ),
			)
		);
		$query->set( 'orderby', 'odocs_inv' );
	}

	public function bulk_actions( $actions ) {
		if ( ! odocs_user_can_manage() ) {
			return $actions;
		}
		foreach ( DocumentRegistry::enabled() as $type => $class ) {
			/* translators: %s: document type, plural, e.g. "invoices" */
			$actions[ 'odocs_bulk_' . $type ] = sprintf( __( 'PDF: %s', 'order-documents' ), $class::plural_label() );
		}
		return $actions;
	}

	/**
	 * Fallback when JavaScript is off: redirect to the merged PDF.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param array  $ids      Order IDs.
	 */
	public function handle_bulk( $redirect, $action, $ids ) {
		if ( 0 !== strpos( (string) $action, 'odocs_bulk_' ) || ! odocs_user_can_manage() ) {
			return $redirect;
		}
		$type = substr( $action, strlen( 'odocs_bulk_' ) );
		if ( ! DocumentRegistry::exists( $type ) ) {
			return $redirect;
		}
		return odocs_document_url( $type, array_map( 'absint', (array) $ids ) );
	}

	public function search_keys( $keys ) {
		$keys   = is_array( $keys ) ? $keys : array();
		$keys[] = '_odocs_invoice_number';
		$keys[] = '_wcpdf_invoice_number';
		return array_values( array_unique( $keys ) );
	}
}
