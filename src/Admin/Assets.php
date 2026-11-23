<?php
/**
 * Admin scripts and styles for order screens and the settings page.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Admin;

defined( 'ABSPATH' ) || exit;

class Assets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( $hook ): void {
		$screen     = get_current_screen();
		$screen_id  = $screen ? $screen->id : '';
		$order_page = in_array( $screen_id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true );
		$settings   = 'woocommerce_page_' . SettingsPage::SLUG === $screen_id;
		if ( ! $order_page && ! $settings ) {
			return;
		}
		wp_enqueue_style( 'odocs-admin', ODOCS_URL . 'assets/admin/admin.css', array(), ODOCS_VERSION );
		$deps = array( 'jquery' );
		if ( $settings ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_media();
			$deps[] = 'wp-color-picker';
		}
		wp_enqueue_script( 'odocs-admin', ODOCS_URL . 'assets/admin/admin.js', $deps, ODOCS_VERSION, true );
		wp_localize_script(
			'odocs-admin',
			'odocsAdmin',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'odocs_admin' ),
				'documentNonce' => wp_create_nonce( 'odocs_document' ),
				'i18n'          => array(
					'confirmDelete' => __( 'Delete this document? Its number will not be reused automatically. This cannot be undone.', 'order-documents' ),
					'noOrders'      => __( 'Select at least one order.', 'order-documents' ),
					'chooseLogo'    => __( 'Choose logo', 'order-documents' ),
					'useImage'      => __( 'Use this image', 'order-documents' ),
				),
			)
		);
	}
}
