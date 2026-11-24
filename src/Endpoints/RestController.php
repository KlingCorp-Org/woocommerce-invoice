<?php
/**
 * REST API for the visual editor (namespace odocs/v1). Requires manage_woocommerce.
 *
 *   GET    /editor-config              block/column types, templates, document types, design settings
 *   GET    /layout?template=&type=     current + default layout
 *   POST   /layout                     save layout {template, type, layout}
 *   DELETE /layout?template=&type=     reset to the template default
 *   POST   /design                     save design settings {style}
 *   POST   /preview                    HTML preview {template, type, layout, style, order_id}
 *   GET    /orders?search=             recent orders for the sample-order picker
 *
 * @package OrderDocs
 */

namespace OrderDocs\Endpoints;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Documents\PickingList;
use OrderDocs\Rendering\DemoOrder;
use OrderDocs\Rendering\Fonts;
use OrderDocs\Rendering\Layout;
use OrderDocs\Rendering\PdfGenerator;
use OrderDocs\Rendering\Placeholders;
use OrderDocs\Rendering\Renderer;
use OrderDocs\Rendering\TemplateRegistry;
use OrderDocs\Settings\Schema;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class RestController {

	const NS = 'odocs/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'wp_ajax_odocs_preview_pdf', array( $this, 'preview_pdf' ) );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	public function routes(): void {
		$perm = array( $this, 'can_manage' );
		register_rest_route( self::NS, '/editor-config', array( 'methods' => 'GET', 'callback' => array( $this, 'config' ), 'permission_callback' => $perm ) );
		register_rest_route(
			self::NS,
			'/layout',
			array(
				array( 'methods' => 'GET', 'callback' => array( $this, 'get_layout' ), 'permission_callback' => $perm ),
				array( 'methods' => 'POST', 'callback' => array( $this, 'save_layout' ), 'permission_callback' => $perm ),
				array( 'methods' => 'DELETE', 'callback' => array( $this, 'reset_layout' ), 'permission_callback' => $perm ),
			)
		);
		register_rest_route( self::NS, '/design', array( 'methods' => 'POST', 'callback' => array( $this, 'save_design' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/preview', array( 'methods' => 'POST', 'callback' => array( $this, 'preview' ), 'permission_callback' => $perm ) );
		register_rest_route( self::NS, '/orders', array( 'methods' => 'GET', 'callback' => array( $this, 'orders' ), 'permission_callback' => $perm ) );
	}

	/**
	 * Template used by a document type (its own setting or the default template).
	 */
	public static function type_template( string $type ): string {
		$template = (string) Settings::doc( $type, 'template', '' );
		if ( '' === $template || ! TemplateRegistry::get( $template ) ) {
			$template = (string) Settings::get( 'design', 'template', 'classic' );
		}
		return TemplateRegistry::get( $template ) ? $template : 'classic';
	}

	private function check_target( \WP_REST_Request $request ) {
		$template = sanitize_key( (string) $request->get_param( 'template' ) );
		$type     = sanitize_key( (string) $request->get_param( 'type' ) );
		if ( ! TemplateRegistry::get( $template ) || ! DocumentRegistry::exists( $type ) ) {
			return new \WP_Error( 'odocs_invalid', __( 'Unknown template or document type.', 'order-documents' ), array( 'status' => 400 ) );
		}
		return array( $template, $type );
	}

	public function config(): \WP_REST_Response {
		$templates = array();
		foreach ( TemplateRegistry::all() as $id => $template ) {
			$templates[] = array(
				'id'          => $id,
				'name'        => $template['name'],
				'description' => $template['description'],
				'thumbnail'   => TemplateRegistry::thumbnail_url( $id ),
			);
		}
		$types = array();
		foreach ( DocumentRegistry::types() as $type => $class ) {
			$types[] = array(
				'id'          => $type,
				'label'       => $class::label(),
				'enabled'     => $class::is_type_enabled(),
				'template'    => self::type_template( $type ),
				'shows_prices' => $class::shows_prices(),
			);
		}
		$design_fields = array();
		foreach ( Schema::fields( 'design' ) as $field ) {
			if ( empty( $field['id'] ) || 'template' === $field['type'] ) {
				continue;
			}
			$field['options'] = Settings::field_options( $field );
			$design_fields[]  = $field;
		}
		return rest_ensure_response(
			array(
				'blockTypes'   => Layout::block_types(),
				'columnTypes'  => Layout::column_types(),
				'templates'    => $templates,
				'documentTypes' => $types,
				'design'       => Settings::section( 'design' ),
				'designFields' => $design_fields,
				'fonts'        => Fonts::options(),
				'placeholders' => Placeholders::help(),
			)
		);
	}

	public function get_layout( \WP_REST_Request $request ) {
		$target = $this->check_target( $request );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		list( $template, $type ) = $target;
		return rest_ensure_response(
			array(
				'layout'     => Layout::get( $template, $type ),
				'default'    => Layout::default_layout( $template, $type ),
				'customized' => Layout::is_customized( $template, $type ),
			)
		);
	}

	public function save_layout( \WP_REST_Request $request ) {
		$target = $this->check_target( $request );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		list( $template, $type ) = $target;
		$layout = $request->get_param( 'layout' );
		if ( ! is_array( $layout ) ) {
			return new \WP_Error( 'odocs_invalid', __( 'Invalid layout.', 'order-documents' ), array( 'status' => 400 ) );
		}
		$saved = Layout::save( $template, $type, $layout );
		return rest_ensure_response( array( 'layout' => $saved, 'customized' => true ) );
	}

	public function reset_layout( \WP_REST_Request $request ) {
		$target = $this->check_target( $request );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		list( $template, $type ) = $target;
		Layout::reset( $template, $type );
		return rest_ensure_response( array( 'layout' => Layout::default_layout( $template, $type ), 'customized' => false ) );
	}

	public function save_design( \WP_REST_Request $request ) {
		$style = $request->get_param( 'style' );
		if ( ! is_array( $style ) ) {
			return new \WP_Error( 'odocs_invalid', __( 'Invalid design settings.', 'order-documents' ), array( 'status' => 400 ) );
		}
		$clean = Settings::sanitize( 'design', array_merge( Settings::section( 'design' ), $style ) );
		update_option( Settings::option_name( 'design' ), $clean );
		Settings::flush();
		return rest_ensure_response( array( 'design' => Settings::section( 'design' ) ) );
	}

	/**
	 * Builds the document to preview: from a real order, or from demo data.
	 */
	public static function preview_document( string $type, int $order_id ) {
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( $order instanceof \WC_Order_Refund ) {
			$order = wc_get_order( $order->get_parent_id() );
		}
		$refund = null;
		if ( ! $order instanceof \WC_Order ) {
			$order = DemoOrder::create();
			if ( 'credit_note' === $type ) {
				$refund = DemoOrder::refund( $order );
			}
		} elseif ( 'credit_note' === $type ) {
			$refunds = $order->get_refunds();
			$refund  = $refunds ? $refunds[0] : DemoOrder::refund( $order );
		}
		$document = DocumentRegistry::make( $type, $order, $refund );
		if ( $document instanceof PickingList ) {
			$document->set_orders( array( $order ) );
		}
		if ( $document && ! $document->exists() ) {
			DemoOrder::fake_document_data( $document );
		}
		return $document;
	}

	private function preview_overrides( \WP_REST_Request $request, string $template ): array {
		$overrides = array( 'template' => $template );
		$layout    = $request->get_param( 'layout' );
		if ( is_array( $layout ) ) {
			$overrides['layout'] = $layout;
		}
		$style = $request->get_param( 'style' );
		if ( is_array( $style ) ) {
			$overrides['style'] = Settings::sanitize( 'design', array_merge( Settings::section( 'design' ), $style ) );
		}
		return $overrides;
	}

	public function preview( \WP_REST_Request $request ) {
		$target = $this->check_target( $request );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		list( $template, $type ) = $target;
		$document = self::preview_document( $type, absint( $request->get_param( 'order_id' ) ) );
		if ( ! $document ) {
			return new \WP_Error( 'odocs_invalid', __( 'Could not build a preview.', 'order-documents' ), array( 'status' => 400 ) );
		}
		$renderer = new Renderer( 'preview', $this->preview_overrides( $request, $template ) );
		$html     = $renderer->render( array( $document ) );
		return rest_ensure_response(
			array(
				'html'    => $html,
				'palette' => $renderer->palette(),
				'style'   => $renderer->style(),
			)
		);
	}

	/**
	 * PDF preview from the editor (form POST into a new tab), using the unsaved layout.
	 */
	public function preview_pdf(): void {
		check_admin_referer( 'odocs_preview_pdf' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'order-documents' ), '', array( 'response' => 403 ) );
		}
		$request = new \WP_REST_Request( 'POST' );
		$payload = json_decode( wp_unslash( (string) ( $_POST['payload'] ?? '{}' ) ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized by Layout/Settings.
		$payload = is_array( $payload ) ? $payload : array();
		foreach ( array( 'template', 'type', 'layout', 'style', 'order_id' ) as $key ) {
			if ( isset( $payload[ $key ] ) ) {
				$request->set_param( $key, $payload[ $key ] );
			}
		}
		$target = $this->check_target( $request );
		if ( is_wp_error( $target ) ) {
			wp_die( esc_html( $target->get_error_message() ) );
		}
		list( $template, $type ) = $target;
		$document = self::preview_document( $type, absint( $request->get_param( 'order_id' ) ) );
		try {
			$pdf = PdfGenerator::documents( array( $document ), $this->preview_overrides( $request, $template ) );
		} catch ( \Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="preview-' . $type . '.pdf"' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public function orders( \WP_REST_Request $request ): \WP_REST_Response {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$args   = array(
			'limit'   => 20,
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
			'return'  => 'objects',
		);
		if ( '' !== $search ) {
			if ( ctype_digit( $search ) ) {
				$args['include'] = array( absint( $search ) );
			} else {
				$args['s'] = $search;
			}
		}
		$result = array();
		foreach ( wc_get_orders( $args ) as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$result[] = array(
				'id'    => $order->get_id(),
				'label' => sprintf( '#%s %s (%s)', $order->get_order_number(), $order->get_formatted_billing_full_name(), wc_get_order_status_name( $order->get_status() ) ),
			);
		}
		return rest_ensure_response( $result );
	}
}
