<?php
/**
 * Main plugin class: wires every component to WordPress.
 *
 * @package OrderDocs
 */

namespace OrderDocs;

use OrderDocs\Admin\Assets;
use OrderDocs\Admin\EditorPage;
use OrderDocs\Admin\OrderActions;
use OrderDocs\Admin\OrderListActions;
use OrderDocs\Admin\OrderMetaBoxes;
use OrderDocs\Admin\SettingsPage;
use OrderDocs\Documents\AutoCreate;
use OrderDocs\Emails\Attachments;
use OrderDocs\Emails\AutoSend;
use OrderDocs\Emails\DocumentAddressEmail;
use OrderDocs\Emails\DocumentEmail;
use OrderDocs\Endpoints\DocumentEndpoint;
use OrderDocs\Endpoints\RestController;
use OrderDocs\Frontend\MyAccount;
use OrderDocs\Migration\WpoImporter;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var bool */
	private $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		Install::maybe_upgrade();

		( new DocumentEndpoint() )->register();
		( new RestController() )->register();
		( new Attachments() )->register();
		( new AutoCreate() )->register();
		( new AutoSend() )->register();
		( new MyAccount() )->register();
		( new WpoImporter() )->register();

		add_filter( 'woocommerce_email_classes', array( $this, 'register_email' ) );

		if ( is_admin() ) {
			( new SettingsPage() )->register();
			( new EditorPage() )->register();
			( new OrderMetaBoxes() )->register();
			( new OrderListActions() )->register();
			( new OrderActions() )->register();
			( new Assets() )->register();
			add_filter( 'plugin_action_links_' . plugin_basename( ODOCS_FILE ), array( $this, 'action_links' ) );
		}

		add_action( Install::CLEANUP_HOOK, array( Storage::class, 'cleanup_temp' ) );

		do_action( 'odocs_loaded', $this );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'order-documents', false, dirname( plugin_basename( ODOCS_FILE ) ) . '/languages' );
	}

	/**
	 * @param array $emails WC email classes.
	 * @return array
	 */
	public function register_email( $emails ) {
		$emails['OrderDocs_Document_Email']         = new DocumentEmail();
		$emails['OrderDocs_Document_Address_Email'] = new DocumentAddressEmail();
		return $emails;
	}

	/**
	 * @param array $links Plugin row links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=odocs-settings' ) ), esc_html__( 'Settings', 'order-documents' ) )
		);
		return $links;
	}
}
