<?php
/**
 * Settings page: WooCommerce → Order Documents.
 *
 * Tabs: General, Documents (one sub-tab per type), Design, Advanced, Tools.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Admin;

use OrderDocs\Documents\DocumentRegistry;
use OrderDocs\Documents\Number\Sequence;
use OrderDocs\Migration\WpoImporter;
use OrderDocs\Rendering\Layout;
use OrderDocs\Rendering\Placeholders;
use OrderDocs\Rendering\TemplateRegistry;
use OrderDocs\Settings\Schema;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	const SLUG = 'odocs-settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_odocs_set_sequence', array( $this, 'handle_set_sequence' ) );
		ImportPage::register_handlers();
	}

	public function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Order Documents', 'order-documents' ),
			__( 'Order Documents', 'order-documents' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * All settings sections.
	 */
	public static function sections(): array {
		$sections = array( 'general', 'design', 'advanced' );
		foreach ( array_keys( DocumentRegistry::types() ) as $type ) {
			$sections[] = 'doc_' . $type;
		}
		return $sections;
	}

	public function register_settings(): void {
		foreach ( self::sections() as $section ) {
			$option = Settings::option_name( $section );
			register_setting(
				$option,
				$option,
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $input ) use ( $section ) {
						return $this->sanitize_section( $section, $input );
					},
				)
			);
			add_filter(
				'option_page_capability_' . $option,
				static function () {
					return 'manage_woocommerce';
				}
			);
		}
	}

	/**
	 * Sanitizes a section and applies "next number" changes.
	 *
	 * @param string $section Section.
	 * @param mixed  $input   Submitted values.
	 */
	public function sanitize_section( string $section, $input ): array {
		if ( 0 === strpos( $section, 'doc_' ) && is_array( $input ) && isset( $input['next_number'] ) && '' !== $input['next_number'] && current_user_can( 'manage_woocommerce' ) ) {
			$type  = substr( $section, 4 );
			$class = DocumentRegistry::get_class( $type );
			$next  = absint( $input['next_number'] );
			if ( $class && $next > 0 ) {
				// Use the submitted sequence settings, so the right sequence row is changed.
				$clean = Settings::sanitize( $section, $input );
				$base  = $type;
				$reset = (string) ( $clean['reset'] ?? 'never' );
				if ( 'invoice' === ( $clean['sequence'] ?? 'own' ) && 'invoice' !== $type ) {
					$base  = 'invoice';
					$reset = (string) Settings::doc( 'invoice', 'reset', 'never' );
				}
				$name = Sequence::name( $base, $reset );
				if ( Sequence::peek( $name ) !== $next ) {
					Sequence::set_next( $name, $next );
				}
			}
		}
		return Settings::sanitize( $section, $input );
	}

	/* ------------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------------ */

	protected function tabs(): array {
		return array(
			'general'   => __( 'General', 'order-documents' ),
			'documents' => __( 'Documents', 'order-documents' ),
			'design'    => __( 'Design', 'order-documents' ),
			'advanced'  => __( 'Advanced', 'order-documents' ),
			'tools'     => __( 'Tools', 'order-documents' ),
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tabs = $this->tabs();
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'general' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'general';
		echo '<div class="wrap odocs-settings">';
		echo '<h1>' . esc_html__( 'Order Documents', 'order-documents' ) . '</h1>';
		if ( WpoImporter::should_suggest_import() && 'tools' !== $tab ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Data from WooCommerce PDF Invoices & Packing Slips was found. Existing invoice numbers are used automatically; import the settings and continue the number sequences under Tools.', 'order-documents' ) . ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=tools' ) ) . '">' . esc_html__( 'Open the importer', 'order-documents' ) . '</a></p></div>';
		}
		settings_errors();
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $id => $label ) {
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $id ) ), $id === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'documents':
				$this->render_documents_tab();
				break;
			case 'design':
				$this->render_design_tab();
				break;
			case 'tools':
				$this->render_tools_tab();
				break;
			default:
				$this->render_form( $tab );
				if ( 'general' === $tab ) {
					$this->render_placeholder_help();
				}
		}
		echo '</div>';
	}

	protected function render_form( string $section, string $before = '' ): void {
		$option = Settings::option_name( $section );
		echo '<form method="post" action="options.php" class="odocs-settings-form">';
		settings_fields( $option );
		echo $before; // phpcs:ignore WordPress.Security.EscapeOutput
		$this->render_fields( $section );
		submit_button();
		echo '</form>';
	}

	protected function render_documents_tab(): void {
		$types   = DocumentRegistry::types();
		$current = sanitize_key( wp_unslash( $_GET['doc'] ?? 'invoice' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$current = isset( $types[ $current ] ) ? $current : 'invoice';
		echo '<ul class="subsubsub odocs-doc-tabs">';
		$links = array();
		foreach ( $types as $type => $class ) {
			$enabled = $class::is_type_enabled();
			$links[] = sprintf(
				'<li><a href="%s" class="%s"><span class="dashicons dashicons-%s"></span> %s%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=documents&doc=' . $type ) ),
				$type === $current ? 'current' : '',
				esc_attr( $class::icon() ),
				esc_html( $class::label() ),
				$enabled ? '' : ' <span class="odocs-off">(' . esc_html__( 'off', 'order-documents' ) . ')</span>'
			);
		}
		echo implode( ' | </li>', $links ) . '</li></ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput
		$editor = sprintf(
			'<p class="odocs-editor-hint"><a class="button" href="%s"><span class="dashicons dashicons-layout"></span> %s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . EditorPage::SLUG . '&type=' . $current ) ),
			esc_html__( 'Edit the layout in the visual editor', 'order-documents' )
		);
		$this->render_form( 'doc_' . $current, $editor );
	}

	protected function render_design_tab(): void {
		$this->render_form(
			'design',
			sprintf(
				'<p><a class="button button-primary" href="%s"><span class="dashicons dashicons-layout"></span> %s</a> <span class="description">%s</span></p>',
				esc_url( admin_url( 'admin.php?page=' . EditorPage::SLUG ) ),
				esc_html__( 'Open the visual editor', 'order-documents' ),
				esc_html__( 'Arrange blocks and columns per template and document type, with a live preview.', 'order-documents' )
			)
		);
	}

	protected function render_tools_tab(): void {
		( new ImportPage() )->render();

		echo '<h2>' . esc_html__( 'Number sequences', 'order-documents' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Each sequence hands out numbers without gaps. Yearly or monthly sequences get their own row.', 'order-documents' ) . '</p>';
		$rows = Sequence::all();
		echo '<table class="widefat striped odocs-sequences" style="max-width:720px"><thead><tr><th>' . esc_html__( 'Sequence', 'order-documents' ) . '</th><th>' . esc_html__( 'Next number', 'order-documents' ) . '</th><th>' . esc_html__( 'Last change (UTC)', 'order-documents' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No numbers have been issued yet.', 'order-documents' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			echo '<tr><td><code>' . esc_html( $row->name ) . '</code></td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="odocs-inline-form">';
			wp_nonce_field( 'odocs_set_sequence' );
			echo '<input type="hidden" name="action" value="odocs_set_sequence"><input type="hidden" name="sequence" value="' . esc_attr( $row->name ) . '">';
			echo '<input type="number" min="1" name="next" value="' . esc_attr( (string) $row->next_value ) . '" class="small-text"> ';
			echo '<button class="button" onclick="return confirm(\'' . esc_js( __( 'Change the next number of this sequence? Numbers must stay unique.', 'order-documents' ) ) . '\')">' . esc_html__( 'Save', 'order-documents' ) . '</button></form>';
			echo '</td><td>' . esc_html( (string) $row->updated_at ) . '</td><td></td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Test print', 'order-documents' ) . '</h2>';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" target="_blank" class="odocs-test-print">';
		echo '<input type="hidden" name="action" value="odocs_document"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'odocs_document' ) ) . '">';
		echo '<label>' . esc_html__( 'Order ID', 'order-documents' ) . ' <input type="number" name="order_ids" min="1" required class="small-text"></label> ';
		echo '<select name="type">';
		foreach ( DocumentRegistry::labels() as $type => $label ) {
			echo '<option value="' . esc_attr( $type ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select> <select name="output"><option value="pdf">PDF</option><option value="html">HTML</option></select> ';
		echo '<button class="button">' . esc_html__( 'Open', 'order-documents' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Opening a document creates it (and gives it a number) if it does not exist yet.', 'order-documents' ) . '</p>';
		echo '</form>';
	}

	public function handle_set_sequence(): void {
		check_admin_referer( 'odocs_set_sequence' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'order-documents' ) );
		}
		$name = sanitize_text_field( wp_unslash( $_POST['sequence'] ?? '' ) );
		$next = absint( $_POST['next'] ?? 0 );
		if ( $name && $next > 0 ) {
			Sequence::set_next( $name, $next );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '&tab=tools&updated=1' ) );
		exit;
	}

	protected function render_placeholder_help(): void {
		echo '<h2>' . esc_html__( 'Placeholders', 'order-documents' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Use these in the footer, extra fields, payment information and custom text blocks.', 'order-documents' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:720px"><tbody>';
		foreach ( Placeholders::help() as $code => $label ) {
			echo '<tr><td><code>' . esc_html( $code ) . '</code></td><td>' . esc_html( $label ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* ------------------------------------------------------------------
	 * Fields
	 * ------------------------------------------------------------------ */

	protected function render_fields( string $section ): void {
		$values = Settings::section( $section );
		$option = Settings::option_name( $section );
		$open   = false;
		foreach ( Schema::fields( $section ) as $field ) {
			if ( 'heading' === $field['type'] ) {
				if ( $open ) {
					echo '</tbody></table>';
				}
				echo '<h2 class="odocs-section-title">' . esc_html( $field['label'] ) . '</h2>';
				if ( ! empty( $field['description'] ) ) {
					echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				}
				echo '<table class="form-table" role="presentation"><tbody>';
				$open = true;
				continue;
			}
			if ( ! $open ) {
				echo '<table class="form-table" role="presentation"><tbody>';
				$open = true;
			}
			$id    = 'odocs-' . $section . '-' . $field['id'];
			$name  = $option . '[' . $field['id'] . ']';
			$value = $values[ $field['id'] ] ?? ( $field['default'] ?? '' );
			echo '<tr class="odocs-field odocs-field-' . esc_attr( $field['type'] ) . '"><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
			$this->render_field( $section, $field, $id, $name, $value );
			if ( ! empty( $field['description'] ) && 'checkbox' !== $field['type'] ) {
				echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		if ( $open ) {
			echo '</tbody></table>';
		}
	}

	/**
	 * @param mixed $value Current value.
	 */
	protected function render_field( string $section, array $field, string $id, string $name, $value ): void {
		switch ( $field['type'] ) {
			case 'textarea':
				printf( '<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
				break;
			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" class="small-text" min="%s" max="%s" step="%s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['min'] ?? '' ) ),
					esc_attr( (string) ( $field['max'] ?? '' ) ),
					esc_attr( (string) ( $field['step'] ?? 1 ) )
				);
				break;
			case 'checkbox':
				printf( '<input type="hidden" name="%s" value="0">', esc_attr( $name ) );
				printf(
					'<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html( $field['description'] ?? '' )
				);
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( Settings::field_options( $field ) as $key => $label ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( (string) $key ), selected( (string) $value, (string) $key, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'template':
				echo '<div class="odocs-template-gallery">';
				foreach ( TemplateRegistry::all() as $template_id => $template ) {
					$thumb = TemplateRegistry::thumbnail_url( $template_id );
					printf(
						'<label class="odocs-template-card%s"><input type="radio" name="%s" value="%s" %s><span class="odocs-template-thumb">%s</span><strong>%s</strong><span class="description">%s</span></label>',
						$template_id === $value ? ' is-selected' : '',
						esc_attr( $name ),
						esc_attr( $template_id ),
						checked( $template_id, $value, false ),
						$thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '',
						esc_html( $template['name'] ),
						esc_html( $template['description'] )
					);
				}
				echo '</div>';
				break;
			case 'color':
				printf( '<input type="text" id="%s" name="%s" value="%s" class="odocs-color" data-default-color="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), esc_attr( (string) ( $field['default'] ?? '' ) ) );
				break;
			case 'media':
				$src = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
				printf(
					'<div class="odocs-media" data-title="%5$s"><input type="hidden" id="%1$s" name="%2$s" value="%3$s"><div class="odocs-media-preview">%4$s</div><button type="button" class="button odocs-media-select">%6$s</button> <button type="button" class="button-link button-link-delete odocs-media-remove"%7$s>%8$s</button></div>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					$src ? '<img src="' . esc_url( $src ) . '" alt="">' : '',
					esc_attr__( 'Choose logo', 'order-documents' ),
					esc_html__( 'Choose image', 'order-documents' ),
					$value ? '' : ' hidden',
					esc_html__( 'Remove', 'order-documents' )
				);
				break;
			case 'multicheck':
			case 'statuses':
			case 'emails':
			case 'payment_methods':
				$options = Settings::field_options( $field );
				$value   = array_map( 'strval', (array) $value );
				printf( '<input type="hidden" name="%s[]" value="">', esc_attr( $name ) );
				if ( ! $options ) {
					echo '<em>' . esc_html__( 'Nothing to choose from.', 'order-documents' ) . '</em>';
					break;
				}
				echo '<fieldset class="odocs-checklist">';
				foreach ( $options as $key => $label ) {
					printf(
						'<label><input type="checkbox" name="%s[]" value="%s" %s> %s</label>',
						esc_attr( $name ),
						esc_attr( (string) $key ),
						checked( in_array( (string) $key, $value, true ), true, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;
			case 'sequence':
				$type  = substr( $section, 4 );
				$class = DocumentRegistry::get_class( $type );
				$name_seq = $class ? $class::sequence_name_for() : $type;
				printf( '<input type="number" id="%s" name="%s" value="%s" min="1" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) Sequence::peek( $name_seq ) ) );
				/* translators: %s: sequence name */
				echo ' <span class="odocs-muted">' . esc_html( sprintf( __( 'Sequence: %s', 'order-documents' ), $name_seq ) ) . '</span>';
				break;
			default:
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="regular-text" placeholder="%s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['placeholder'] ?? '' ) )
				);
		}
	}
}
