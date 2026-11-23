<?php
/**
 * Plugin Name:       Order Documents for WooCommerce
 * Description:       PDF invoices, packing slips, delivery notes, credit notes, proforma invoices, receipts and picking lists for WooCommerce, with design templates and a visual layout editor.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.5
 * WC tested up to:   11.3
 * Text Domain:       order-documents
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

define( 'ODOCS_VERSION', '1.0.0' );
define( 'ODOCS_FILE', __FILE__ );
define( 'ODOCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ODOCS_URL', plugin_dir_url( __FILE__ ) );

// Dompdf and its dependencies, re-namespaced to OrderDocs\Vendor\* so they never clash with other plugins.
if ( file_exists( ODOCS_DIR . 'vendor-prefixed/autoload.php' ) ) {
	require_once ODOCS_DIR . 'vendor-prefixed/autoload.php';
}

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'OrderDocs\\';
		if ( 0 !== strpos( $class, $prefix ) || 0 === strpos( $class, 'OrderDocs\\Vendor\\' ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = ODOCS_DIR . 'src/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once ODOCS_DIR . 'src/functions.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, array( \OrderDocs\Install::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \OrderDocs\Install::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Order Documents for WooCommerce requires WooCommerce to be installed and active.', 'order-documents' ) . '</p></div>';
				}
			);
			return;
		}
		\OrderDocs\Plugin::instance()->boot();
	},
	20
);
