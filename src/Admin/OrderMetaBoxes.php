<?php
/**
 * Meta boxes on the order edit screen (HPOS and legacy):
 *   - "Documents" (sidebar): a button per document type, credit notes per refund.
 *   - "Document data" (main column): number, date and notes of each document, with edit and delete.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Admin;

use OrderDocs\Documents\Archive;
use OrderDocs\Documents\Document;
use OrderDocs\Documents\DocumentRegistry;

defined( 'ABSPATH' ) || exit;

class OrderMetaBoxes {

	const NOTICE_TRANSIENT = 'odocs_admin_notice_';

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add' ), 30, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ), 60, 2 );
		add_action( 'wp_ajax_odocs_metabox', array( $this, 'ajax_refresh' ) );
		add_action( 'wp_ajax_odocs_delete_document', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_odocs_regenerate_document', array( $this, 'ajax_regenerate' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * @param string            $screen_or_post_type Screen ID or post type.
	 * @param \WP_Post|\WC_Order $post_or_order      Post or order.
	 */
	public function add( $screen_or_post_type, $post_or_order = null ): void {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		if ( ! in_array( $screen_or_post_type, array( $screen, 'shop_order', 'woocommerce_page_wc-orders' ), true ) || ! odocs_user_can_manage() ) {
			return;
		}
		add_meta_box( 'odocs-documents', __( 'Documents', 'order-documents' ), array( $this, 'render_buttons_box' ), $screen, 'side', 'high' );
		add_meta_box( 'odocs-document-data', __( 'Document data', 'order-documents' ), array( $this, 'render_data_box' ), $screen, 'normal', 'default' );
	}

	/**
	 * @param \WP_Post|\WC_Order $post_or_order Post or order.
	 */
	private function order_from( $post_or_order ): ?\WC_Order {
		$order = $post_or_order instanceof \WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		return $order instanceof \WC_Order ? $order : null;
	}

	/**
	 * @param \WP_Post|\WC_Order $post_or_order Post or order.
	 */
	public function render_buttons_box( $post_or_order ): void {
		$order = $this->order_from( $post_or_order );
		if ( $order ) {
			echo $this->buttons_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		}
	}

	/**
	 * @param \WP_Post|\WC_Order $post_or_order Post or order.
	 */
	public function render_data_box( $post_or_order ): void {
		$order = $this->order_from( $post_or_order );
		if ( $order ) {
			wp_nonce_field( 'odocs_save_document_data', 'odocs_document_data_nonce' );
			echo $this->data_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		}
	}

	/**
	 * Document buttons for the sidebar box.
	 */
	public function buttons_html( \WC_Order $order ): string {
		$html = '<div class="odocs-metabox" data-order-id="' . esc_attr( (string) $order->get_id() ) . '"><ul class="odocs-doc-buttons">';
		$any  = false;
		foreach ( DocumentRegistry::enabled() as $type => $class ) {
			if ( $class::is_per_refund() ) {
				foreach ( array_reverse( $order->get_refunds() ) as $refund ) {
					$document = DocumentRegistry::make( $type, $order, $refund );
					if ( $document ) {
						/* translators: %s: refund amount */
						$html .= $this->button_html( $document, sprintf( __( 'Refund %s', 'order-documents' ), wp_strip_all_tags( wc_price( $refund->get_amount(), array( 'currency' => $order->get_currency() ) ) ) ) );
						$any   = true;
					}
				}
				continue;
			}
			$document = DocumentRegistry::make( $type, $order );
			if ( $document ) {
				$html .= $this->button_html( $document );
				$any   = true;
			}
		}
		$html .= '</ul>';
		if ( ! $any ) {
			$html .= '<p>' . sprintf(
				/* translators: %s: settings link */
				esc_html__( 'No document types are enabled. %s', 'order-documents' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=odocs-settings&tab=documents' ) ) . '">' . esc_html__( 'Settings', 'order-documents' ) . '</a>'
			) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	protected function button_html( Document $document, string $context = '' ): string {
		$reason  = null;
		$allowed = $document->is_allowed( $reason );
		$args    = array();
		if ( $document->get_refund() ) {
			$args['refund_id'] = $document->get_refund()->get_id();
		}
		$url    = odocs_document_url( $document->get_type(), array( $document->get_order()->get_id() ), $args );
		$status = '';
		if ( $document::is_multi_order() ) {
			$status = '';
		} elseif ( $document->exists() ) {
			$status = $document->get_number() ? $document->get_number() : odocs_format_date( $document->get_date() );
		} elseif ( $allowed ) {
			$status = __( 'Not created yet', 'order-documents' );
		}
		$classes = 'odocs-doc odocs-doc-' . $document->get_type() . ( $document->exists() && ! $document::is_multi_order() ? ' odocs-exists' : '' );
		$html    = '<li class="' . esc_attr( $classes ) . '">';
		if ( $allowed ) {
			$html .= sprintf(
				'<a class="button odocs-open" href="%s" target="_blank" rel="noopener"><span class="dashicons dashicons-%s"></span> %s</a>',
				esc_url( $url ),
				esc_attr( $document::icon() ),
				esc_html( $document->get_title() )
			);
		} else {
			$html .= sprintf(
				'<span class="button disabled odocs-open" title="%s" aria-disabled="true"><span class="dashicons dashicons-%s"></span> %s</span>',
				esc_attr( (string) $reason ),
				esc_attr( $document::icon() ),
				esc_html( $document->get_title() )
			);
		}
		$meta = array_filter( array( $context, $status ) );
		if ( ! $allowed && $reason ) {
			$meta[] = $reason;
		}
		if ( $meta ) {
			$html .= '<span class="odocs-doc-status">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
		}
		$html .= '</li>';
		return $html;
	}

	/**
	 * Document data (edit number/date/notes) for the main column box.
	 */
	public function data_html( \WC_Order $order ): string {
		$documents = array();
		foreach ( DocumentRegistry::types() as $type => $class ) {
			if ( $class::is_multi_order() ) {
				continue;
			}
			if ( $class::is_per_refund() ) {
				foreach ( array_reverse( $order->get_refunds() ) as $refund ) {
					$document = DocumentRegistry::make( $type, $order, $refund );
					if ( $document && $document->exists() ) {
						$documents[] = $document;
					}
				}
				continue;
			}
			$document = DocumentRegistry::make( $type, $order );
			if ( $document && $document->exists() ) {
				$documents[] = $document;
			}
		}
		if ( ! $documents ) {
			return '<div class="odocs-data-empty"><p>' . esc_html__( 'No documents have been created for this order yet. Use the buttons in the Documents box.', 'order-documents' ) . '</p></div>';
		}
		$html = '<div class="odocs-data" data-order-id="' . esc_attr( (string) $order->get_id() ) . '">';
		foreach ( $documents as $document ) {
			$html .= $this->document_data_html( $document );
		}
		$html .= '</div>';
		return $html;
	}

	protected function document_data_html( Document $document ): string {
		$key      = $document->get_type() . ( $document->get_refund() ? ':' . $document->get_refund()->get_id() : '' );
		$field    = 'odocs_doc[' . $key . ']';
		$date     = $document->get_date();
		$archived = Archive::get( $document );
		$title    = $document->get_title() . ( $document->get_number() ? ' ' . $document->get_number() : '' );

		$html  = '<div class="odocs-data-doc" data-type="' . esc_attr( $document->get_type() ) . '" data-refund-id="' . esc_attr( $document->get_refund() ? (string) $document->get_refund()->get_id() : '' ) . '">';
		$html .= '<h4><span class="dashicons dashicons-' . esc_attr( $document::icon() ) . '"></span> ' . esc_html( $title );
		if ( $document->is_legacy() ) {
			$html .= ' <span class="odocs-badge">' . esc_html__( 'from previous plugin', 'order-documents' ) . '</span>';
		}
		if ( $archived ) {
			$html .= ' <span class="odocs-badge odocs-badge-archived" title="' . esc_attr__( 'The archived PDF is served. Regenerate to apply changes.', 'order-documents' ) . '">' . esc_html__( 'archived', 'order-documents' ) . '</span>';
		}
		$html .= '</h4>';

		// Read-only view.
		$html .= '<table class="odocs-data-view"><tbody>';
		if ( $document->is_numbered() ) {
			$html .= '<tr><th>' . esc_html__( 'Number', 'order-documents' ) . '</th><td>' . esc_html( $document->get_number() ?: '–' ) . '</td></tr>';
		}
		$html .= '<tr><th>' . esc_html__( 'Date', 'order-documents' ) . '</th><td>' . esc_html( $date ? odocs_format_date( $date, true ) : '–' ) . '</td></tr>';
		if ( $document->get_notes() ) {
			$html .= '<tr><th>' . esc_html__( 'Notes', 'order-documents' ) . '</th><td>' . wp_kses_post( nl2br( $document->get_notes() ) ) . '</td></tr>';
		}
		$html .= '</tbody></table>';

		// Edit form (submitted with the order).
		$html .= '<div class="odocs-data-edit" hidden>';
		$html .= '<input type="hidden" class="odocs-edited" name="' . esc_attr( $field . '[edited]' ) . '" value="0">';
		if ( $document->is_numbered() ) {
			$html .= '<p><label>' . esc_html__( 'Number', 'order-documents' ) . '<br><input type="number" min="1" step="1" name="' . esc_attr( $field . '[number]' ) . '" value="' . esc_attr( (string) $document->get_plain_number() ) . '"></label>';
			$html .= ' <span class="description">' . esc_html__( 'Plain number; prefix, suffix and padding are added automatically.', 'order-documents' ) . '</span></p>';
		}
		$html .= '<p><label>' . esc_html__( 'Date', 'order-documents' ) . '<br>';
		$html .= '<input type="date" name="' . esc_attr( $field . '[date]' ) . '" value="' . esc_attr( $date ? $date->date( 'Y-m-d' ) : '' ) . '"> ';
		$html .= '<input type="time" name="' . esc_attr( $field . '[time]' ) . '" value="' . esc_attr( $date ? $date->date( 'H:i' ) : '' ) . '"></label></p>';
		$html .= '<p><label>' . esc_html__( 'Notes (printed in the "Document notes" block)', 'order-documents' ) . '<br><textarea rows="3" name="' . esc_attr( $field . '[notes]' ) . '">' . esc_textarea( $document->get_notes() ) . '</textarea></label></p>';
		$html .= '<p class="description">' . esc_html__( 'Changes are saved when you update the order.', 'order-documents' ) . '</p>';
		$html .= '</div>';

		$html .= '<p class="odocs-data-actions">';
		$html .= '<button type="button" class="button-link odocs-edit-document">' . esc_html__( 'Edit', 'order-documents' ) . '</button>';
		if ( $archived ) {
			$html .= ' | <button type="button" class="button-link odocs-regenerate-document">' . esc_html__( 'Regenerate PDF', 'order-documents' ) . '</button>';
		}
		$html .= ' | <button type="button" class="button-link button-link-delete odocs-delete-document">' . esc_html__( 'Delete', 'order-documents' ) . '</button>';
		$html .= '</p></div>';
		return $html;
	}

	/**
	 * Saves edited document data when the order is saved.
	 *
	 * @param int                $order_id      Order ID.
	 * @param \WP_Post|\WC_Order $post_or_order Post or order.
	 */
	public function save( $order_id, $post_or_order = null ): void {
		if ( empty( $_POST['odocs_document_data_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['odocs_document_data_nonce'] ) ), 'odocs_save_document_data' ) || ! odocs_user_can_manage() ) {
			return;
		}
		$submitted = isset( $_POST['odocs_doc'] ) && is_array( $_POST['odocs_doc'] ) ? wp_unslash( $_POST['odocs_doc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
		$order     = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$errors = array();
		foreach ( $submitted as $key => $values ) {
			if ( empty( $values['edited'] ) ) {
				continue;
			}
			list( $type, $refund_id ) = array_pad( explode( ':', sanitize_text_field( (string) $key ) ), 2, 0 );
			$document = DocumentRegistry::make( sanitize_key( $type ), $order, $refund_id ? absint( $refund_id ) : null );
			if ( ! $document || ! $document->exists() ) {
				continue;
			}
			$changed = false;
			if ( ! empty( $values['date'] ) ) {
				$time = preg_match( '/^\d{2}:\d{2}$/', (string) ( $values['time'] ?? '' ) ) ? $values['time'] : '00:00';
				try {
					$date = new \DateTimeImmutable( sanitize_text_field( $values['date'] ) . ' ' . $time, wp_timezone() );
					if ( ! $document->get_date() || $date->format( 'Y-m-d H:i' ) !== $document->get_date()->date( 'Y-m-d H:i' ) ) {
						$document->set_date( $date->getTimestamp() );
						$changed = true;
					}
				} catch ( \Exception $e ) {
					$errors[] = __( 'Invalid document date.', 'order-documents' );
				}
			}
			if ( $document->is_numbered() && isset( $values['number'] ) && '' !== $values['number'] ) {
				$number = absint( $values['number'] );
				if ( $number > 0 && ( $number !== $document->get_plain_number() || $changed ) ) {
					$previous = $document->get_data();
					$document->set_number( $number );
					$duplicate = self::find_duplicate( $document );
					if ( $duplicate ) {
						$errors[] = sprintf(
							/* translators: 1: document number, 2: order number */
							__( 'Number %1$s is already used by order #%2$s. The number was not changed.', 'order-documents' ),
							$document->get_number(),
							$duplicate
						);
						$document->set_number( (int) $previous['number'] );
						$document->set_formatted_number( (string) $previous['formatted_number'] );
					} else {
						$changed = true;
					}
				}
			}
			$notes = sanitize_textarea_field( (string) ( $values['notes'] ?? '' ) );
			if ( $notes !== $document->get_notes() ) {
				$document->set_notes( $notes );
				$changed = true;
			}
			if ( $changed ) {
				Archive::delete( $document );
				$document->save();
				$order->add_order_note(
					sprintf(
						/* translators: %s: document title */
						__( '%s data was edited.', 'order-documents' ),
						$document->get_title() . ( $document->get_number() ? ' ' . $document->get_number() : '' )
					),
					false,
					true
				);
			}
		}
		if ( $errors ) {
			set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $errors, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Order number of another order/refund that already uses this document's formatted number, or null.
	 */
	public static function find_duplicate( Document $document ): ?string {
		global $wpdb;
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$table  = $wpdb->prefix . 'wc_orders_meta';
			$id_col = 'order_id';
		} else {
			$table  = $wpdb->postmeta;
			$id_col = 'post_id';
		}
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT {$id_col} FROM {$table} WHERE meta_key = %s AND meta_value = %s AND {$id_col} <> %d LIMIT 1", $document->meta_key() . '_number', $document->get_number(), $document->storage()->get_id() ) ); // phpcs:ignore
		if ( ! $id ) {
			return null;
		}
		$other = wc_get_order( (int) $id );
		if ( $other instanceof \WC_Order_Refund ) {
			$other = wc_get_order( $other->get_parent_id() );
		}
		return $other ? (string) $other->get_order_number() : (string) $id;
	}

	public function notices(): void {
		$errors = get_transient( self::NOTICE_TRANSIENT . get_current_user_id() );
		if ( ! $errors ) {
			return;
		}
		delete_transient( self::NOTICE_TRANSIENT . get_current_user_id() );
		foreach ( (array) $errors as $error ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	private function ajax_document(): Document {
		check_ajax_referer( 'odocs_admin', 'nonce' );
		if ( ! odocs_user_can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'order-documents' ) ), 403 );
		}
		$order_id  = absint( $_POST['order_id'] ?? 0 );
		$refund_id = absint( $_POST['refund_id'] ?? 0 );
		$type      = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
		$document  = DocumentRegistry::make( $type, $order_id, $refund_id ?: null );
		if ( ! $document ) {
			wp_send_json_error( array( 'message' => __( 'Document not found.', 'order-documents' ) ), 404 );
		}
		return $document;
	}

	private function send_boxes( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wp_send_json_error( array(), 404 );
		}
		wp_send_json_success(
			array(
				'buttons' => $this->buttons_html( $order ),
				'data'    => $this->data_html( $order ),
			)
		);
	}

	public function ajax_refresh(): void {
		check_ajax_referer( 'odocs_admin', 'nonce' );
		if ( ! odocs_user_can_manage() ) {
			wp_send_json_error( array(), 403 );
		}
		$this->send_boxes( absint( $_POST['order_id'] ?? 0 ) );
	}

	public function ajax_delete(): void {
		$document = $this->ajax_document();
		$title    = $document->get_title() . ( $document->get_number() ? ' ' . $document->get_number() : '' );
		$document->delete();
		$document->get_order()->add_order_note(
			/* translators: %s: document title and number */
			sprintf( __( '%s was deleted.', 'order-documents' ), $title ),
			false,
			true
		);
		$this->send_boxes( $document->get_order()->get_id() );
	}

	public function ajax_regenerate(): void {
		$document = $this->ajax_document();
		Archive::delete( $document );
		$this->send_boxes( $document->get_order()->get_id() );
	}
}
