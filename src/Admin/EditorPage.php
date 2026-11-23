<?php
/**
 * Visual layout editor (React app in assets/editor).
 *
 * @package OrderDocs
 */

namespace OrderDocs\Admin;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Endpoints\RestController;
use OrderDocs\Rendering\TemplateRegistry;

defined( 'ABSPATH' ) || exit;

class EditorPage {

	const SLUG = 'odocs-editor';

	/** @var string */
	private $hook = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 61 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function menu(): void {
		$this->hook = (string) add_submenu_page(
			'woocommerce',
			__( 'Document editor', 'order-documents' ),
			__( 'Document editor', 'order-documents' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
		// Keep the menu tidy: the editor is opened from the Order Documents settings.
		add_action(
			'admin_head',
			static function () {
				remove_submenu_page( 'woocommerce', self::SLUG );
			}
		);
	}

	public function render(): void {
		echo '<div class="wrap odocs-editor-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Document editor', 'order-documents' ) . '</h1>';
		echo '<div id="odocs-editor-root"><p class="odocs-editor-loading">' . esc_html__( 'Loading the editor…', 'order-documents' ) . '</p></div>';
		if ( ! file_exists( ODOCS_DIR . 'assets/editor/build/index.js' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The editor files are missing. Reinstall the plugin.', 'order-documents' ) . '</p></div>';
		}
		echo '</div>';
	}

	public function enqueue( $hook ): void {
		if ( $hook !== $this->hook || ! $this->hook ) {
			return;
		}
		$asset_file = ODOCS_DIR . 'assets/editor/build/index.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : array( 'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ), 'version' => ODOCS_VERSION );
		wp_enqueue_script( 'odocs-editor', ODOCS_URL . 'assets/editor/build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wp-components' );
		if ( file_exists( ODOCS_DIR . 'assets/editor/build/index.css' ) ) {
			wp_enqueue_style( 'odocs-editor', ODOCS_URL . 'assets/editor/build/index.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_set_script_translations( 'odocs-editor', 'order-documents', ODOCS_DIR . 'languages' );

		$type = sanitize_key( wp_unslash( $_GET['type'] ?? 'invoice' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$type = DocumentRegistry::exists( $type ) ? $type : 'invoice';
		$template = sanitize_key( wp_unslash( $_GET['template'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$template = TemplateRegistry::get( $template ) ? $template : RestController::type_template( $type );
		$latest   = wc_get_orders( array( 'limit' => 1, 'type' => 'shop_order', 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );

		wp_localize_script(
			'odocs-editor',
			'odocsEditor',
			array(
				'type'          => $type,
				'template'      => $template,
				'orderId'       => $latest ? (int) $latest[0] : 0,
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'pdfNonce'      => wp_create_nonce( 'odocs_preview_pdf' ),
				'settingsUrl'   => admin_url( 'admin.php?page=' . SettingsPage::SLUG ),
			)
		);
	}
}
