<?php
/**
 * Turns documents into HTML (for Dompdf or for the browser preview).
 *
 * Modes:
 *   pdf     — local file paths for images and fonts, fixed footers, for Dompdf.
 *   html    — URLs, page simulated on screen (debug output).
 *   preview — like html, plus block markers so the visual editor can select blocks.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

use OrderDocs\Documents\Document;
use OrderDocs\Settings\Settings;

defined( 'ABSPATH' ) || exit;

class Renderer {

	/** @var string */
	protected $mode;

	/** @var array Overrides: template, layout, style. */
	protected $overrides;

	/** @var bool Rendering more than one document into a single file. */
	protected $bulk = false;

	public function __construct( string $mode = 'pdf', array $overrides = array() ) {
		$this->mode      = in_array( $mode, array( 'pdf', 'html', 'preview' ), true ) ? $mode : 'pdf';
		$this->overrides = $overrides;
	}

	public function mode(): string {
		return $this->mode;
	}

	public function is_pdf(): bool {
		return 'pdf' === $this->mode;
	}

	public function is_bulk(): bool {
		return $this->bulk;
	}

	/**
	 * Design settings, merged with overrides from the editor.
	 */
	public function style(): array {
		$style = Settings::section( 'design' );
		if ( ! empty( $this->overrides['style'] ) && is_array( $this->overrides['style'] ) ) {
			$style = array_merge( $style, array_intersect_key( $this->overrides['style'], $style ) );
		}
		return $style;
	}

	public function template_id( Document $document ): string {
		if ( ! empty( $this->overrides['template'] ) && TemplateRegistry::get( $this->overrides['template'] ) ) {
			return $this->overrides['template'];
		}
		return $document->get_template_id();
	}

	public function layout( Document $document ): array {
		if ( ! empty( $this->overrides['layout'] ) && is_array( $this->overrides['layout'] ) ) {
			return Layout::sanitize( $this->overrides['layout'], $document->get_type() );
		}
		return Layout::get( $this->template_id( $document ), $document->get_type() );
	}

	/**
	 * Paper for a document: size for Dompdf plus dimensions in mm.
	 */
	public function paper( Document $document, int $item_count = 0 ): array {
		$template = TemplateRegistry::get( $this->template_id( $document ) );
		if ( $template && '80mm' === ( $template['paper'] ?? null ) ) {
			$height = max( 180, 190 + 16 * $item_count );
			return array(
				'size'      => array( 0, 0, self::mm_to_pt( 80 ), self::mm_to_pt( $height ) ),
				'width_mm'  => 80,
				'height_mm' => $height,
			);
		}
		$sizes = array(
			'a4'     => array( 210, 297 ),
			'a5'     => array( 148, 210 ),
			'letter' => array( 215.9, 279.4 ),
		);
		$size  = (string) Settings::get( 'general', 'paper_size', 'a4' );
		$size  = isset( $sizes[ $size ] ) ? $size : 'a4';
		$paper = array(
			'size'      => $size,
			'width_mm'  => $sizes[ $size ][0],
			'height_mm' => $sizes[ $size ][1],
		);
		return apply_filters( 'odocs_paper', $paper, $document );
	}

	/**
	 * Page margins in mm: top, right, bottom, left.
	 */
	public function margins( Document $document ): array {
		$template = TemplateRegistry::get( $this->template_id( $document ) );
		if ( $template && ! empty( $template['margins'] ) ) {
			return array_map( 'floatval', $template['margins'] );
		}
		$style = $this->style();
		return array( (float) $style['margin_top'], (float) $style['margin_right'], (float) $style['margin_bottom'], (float) $style['margin_left'] );
	}

	public static function mm_to_pt( float $mm ): float {
		return $mm * 72 / 25.4;
	}

	/**
	 * Full HTML for one or more documents of the same type.
	 *
	 * @param Document[] $documents Documents.
	 */
	public function render( array $documents ): string {
		$documents = array_values( array_filter( $documents ) );
		if ( ! $documents ) {
			return '';
		}
		$this->bulk  = count( $documents ) > 1;
		$first       = $documents[0];
		$template_id = $this->template_id( $first );
		$bodies      = array();
		foreach ( $documents as $index => $document ) {
			$bodies[] = $this->with_locale(
				$document,
				function () use ( $document, $index ) {
					return $this->render_document( $document, $index );
				}
			);
		}
		$css   = $this->css( $template_id, $first );
		$title = esc_html( $this->bulk ? $first::plural_label() : $first->get_title() . ( $first->get_number() ? ' ' . $first->get_number() : '' ) );
		$html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $title . '</title>';
		$html .= '<style>' . $css . '</style>';
		$html .= '</head><body class="' . esc_attr( 'odocs odocs-tpl-' . $template_id . ' odocs-type-' . $first->get_type() . ' odocs-mode-' . $this->mode ) . '">';
		$html .= implode( '', $bodies );
		if ( 'preview' === $this->mode ) {
			$html .= $this->preview_script();
		}
		$html .= '</body></html>';
		return apply_filters( 'odocs_html', $html, $documents, $this );
	}

	/**
	 * Runs a callback in the document's language.
	 */
	public function with_locale( Document $document, callable $callback ) {
		$locale = get_locale();
		if ( 'customer' === Settings::get( 'general', 'language', 'site' ) && $document->get_order()->get_customer_id() ) {
			$user_locale = get_user_meta( $document->get_order()->get_customer_id(), 'locale', true );
			if ( $user_locale ) {
				$locale = $user_locale;
			}
		}
		$locale   = (string) apply_filters( 'odocs_document_locale', $locale, $document );
		$switched = $locale && determine_locale() !== $locale ? switch_to_locale( $locale ) : false;
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * HTML for a single document (inside <body>).
	 */
	public function render_document( Document $document, int $index = 0 ): string {
		$data   = new OrderData( $document, $this->is_pdf() ? 'pdf' : 'html' );
		$layout = $this->layout( $document );

		$fixed  = '';
		$rows   = '';
		foreach ( $layout['rows'] as $row ) {
			// Fixed footers are taken out of the flow and repeated on every page (single documents in PDF mode only).
			if ( $this->is_pdf() && ! $this->bulk ) {
				foreach ( $row['columns'] as $c => $column ) {
					foreach ( $column['blocks'] as $b => $block ) {
						if ( 'footer' === $block['type'] && ! empty( $block['settings']['fixed'] ) ) {
							$fixed .= '<div class="odocs-fixed-footer">' . $this->block_html( $block, $document, $data ) . '</div>';
							unset( $row['columns'][ $c ]['blocks'][ $b ] );
						}
					}
				}
			}
			$rows .= $this->row_html( $row, $document, $data );
		}

		$classes = 'odocs-document' . ( $index > 0 ? ' odocs-page-break' : '' );
		$html    = '<div class="' . esc_attr( $classes ) . '">';
		if ( ! $this->is_pdf() ) {
			$html .= '<div class="odocs-sheet">';
		}
		$html .= $fixed;
		ob_start();
		do_action( 'odocs_before_document', $document, $data, $this );
		$html .= ob_get_clean();
		$html .= $rows;
		ob_start();
		do_action( 'odocs_after_document', $document, $data, $this );
		$html .= ob_get_clean();
		if ( ! $this->is_pdf() ) {
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	protected function row_html( array $row, Document $document, OrderData $data ): string {
		$cells = '';
		$count = count( $row['columns'] );
		$has   = false;
		foreach ( array_values( $row['columns'] ) as $i => $column ) {
			$inner = '';
			foreach ( $column['blocks'] as $block ) {
				$inner .= $this->block_html( $block, $document, $data );
			}
			if ( '' !== $inner ) {
				$has = true;
			}
			$padding = array();
			if ( $i > 0 ) {
				$padding[] = 'padding-left:3mm';
			}
			if ( $i < $count - 1 ) {
				$padding[] = 'padding-right:3mm';
			}
			$cells .= sprintf(
				'<td class="odocs-col" style="width:%s%%;vertical-align:%s;%s">%s</td>',
				esc_attr( (string) $column['width'] ),
				esc_attr( $column['valign'] ?? 'top' ),
				esc_attr( implode( ';', $padding ) ),
				$inner
			);
		}
		if ( ! $has && 'preview' !== $this->mode ) {
			return '';
		}
		$s     = $row['settings'];
		$style = array( 'margin-bottom:' . (float) $s['margin_bottom'] . 'mm' );
		if ( $s['background'] ) {
			$style[] = 'background-color:' . $this->color( $s['background'] );
		}
		if ( $s['text_color'] ) {
			$style[] = 'color:' . $this->color( $s['text_color'] );
		}
		$table_style = array();
		if ( $s['padding'] ) {
			$table_style[] = 'padding:' . (float) $s['padding'] . 'mm';
		}
		$classes = 'odocs-row' . ( $s['border'] ? ' odocs-row-border' : '' ) . ( $s['background'] ? ' odocs-row-band' : '' );
		$attr    = 'preview' === $this->mode ? ' data-odocs-row="' . esc_attr( $row['id'] ) . '"' : '';
		if ( 1 === $count ) {
			// Single column: plain blocks, so long content (e.g. the products table) can break across pages.
			$column = array_values( $row['columns'] )[0];
			$inner  = '';
			foreach ( $column['blocks'] as $block ) {
				$inner .= $this->block_html( $block, $document, $data );
			}
			return sprintf(
				'<div class="%s" style="%s"%s><div class="odocs-row-inner" style="%s">%s</div></div>',
				esc_attr( $classes . ' odocs-row-single' ),
				esc_attr( implode( ';', $style ) ),
				$attr,
				esc_attr( implode( ';', $table_style ) ),
				$inner
			);
		}
		return sprintf(
			'<div class="%s" style="%s"%s><table class="odocs-row-table" cellspacing="0" cellpadding="0" style="%s"><tr>%s</tr></table></div>',
			esc_attr( $classes ),
			esc_attr( implode( ';', $style ) ),
			$attr,
			esc_attr( implode( ';', $table_style ) ),
			$cells
		);
	}

	/**
	 * HTML of one block, wrapped with its common styling. Empty blocks render nothing.
	 */
	public function block_html( array $block, Document $document, OrderData $data ): string {
		$types = Layout::block_types();
		if ( ! isset( $types[ $block['type'] ] ) ) {
			return '';
		}
		$settings = array_merge( Layout::sanitize_settings( array(), $types[ $block['type'] ]['settings'], $block['type'], $document->get_type() ), (array) $block['settings'] );
		$inner    = $this->partial(
			$this->template_id( $document ),
			'blocks/' . $block['type'] . '.php',
			array(
				'block'    => $settings,
				'document' => $document,
				'data'     => $data,
				'r'        => $this,
			)
		);
		$inner = apply_filters( 'odocs_block_html', $inner, $block, $document, $data );
		if ( '' === trim( $inner ) ) {
			if ( 'preview' === $this->mode ) {
				return sprintf( '<div class="odocs-block odocs-block-empty" data-odocs-block="%s"><span>%s</span></div>', esc_attr( $block['id'] ?? '' ), esc_html( $types[ $block['type'] ]['label'] ) );
			}
			return '';
		}
		$style = array( 'margin-bottom:' . (float) $settings['margin_bottom'] . 'mm' );
		if ( $settings['align'] ) {
			$style[] = 'text-align:' . $settings['align'];
		}
		if ( (float) $settings['font_size'] > 0 ) {
			$style[] = 'font-size:' . (float) $settings['font_size'] . 'pt';
		}
		if ( $settings['text_color'] ) {
			$style[] = 'color:' . $this->color( $settings['text_color'] );
		}
		$attr = 'preview' === $this->mode ? ' data-odocs-block="' . esc_attr( $block['id'] ?? '' ) . '"' : '';
		return sprintf(
			'<div class="odocs-block odocs-block-%s%s" style="%s"%s>%s</div>',
			esc_attr( str_replace( '_', '-', $block['type'] ) ),
			$settings['align'] ? ' odocs-align-' . esc_attr( $settings['align'] ) : '',
			esc_attr( implode( ';', $style ) ),
			$attr,
			$inner
		);
	}

	/**
	 * Includes a template file and returns its output.
	 */
	public function partial( string $template_id, string $relative, array $vars = array() ): string {
		$file = TemplateRegistry::locate( $template_id, $relative );
		if ( ! $file ) {
			return '';
		}
		ob_start();
		( static function ( $__file, $__vars ) {
			extract( $__vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
			include $__file;
		} )( $file, $vars );
		return (string) ob_get_clean();
	}

	/**
	 * Resolves a colour value ("accent" or a hex colour).
	 */
	public function color( string $value ): string {
		if ( 'accent' === $value ) {
			return (string) $this->style()['accent_color'];
		}
		return sanitize_hex_color( $value ) ?: '';
	}

	/**
	 * <img> tag for an image from OrderData::image().
	 *
	 * @param array|null $image Image.
	 */
	public function img( $image, string $style = '', string $alt = '' ): string {
		if ( empty( $image['src'] ) ) {
			return '';
		}
		$src = $this->is_pdf() ? $image['src'] : esc_url( $image['src'] );
		return sprintf( '<img src="%s" style="%s" alt="%s">', esc_attr( $src ), esc_attr( $style ), esc_attr( $alt ) );
	}

	/**
	 * Complete stylesheet: page, fonts, shared base and template styles.
	 */
	public function css( string $template_id, Document $document ): string {
		$style   = $this->style();
		$margins = $this->margins( $document );
		$paper   = $this->paper( $document, count( ( new OrderData( $document, 'html' ) )->items() ) );
		$template = TemplateRegistry::get( $template_id ) ?: array();
		$table_style = $style['table_style'] ?: ( $template['table_style'] ?? 'lined' );
		$vars    = array(
			's'           => $style,
			'r'           => $this,
			'template'    => $template,
			'margins'     => $margins,
			'paper'       => $paper,
			'font'        => Fonts::family_css( (string) $style['font_family'] ),
			'table_style' => $table_style,
			'mode'        => $this->mode,
		);
		$css  = Fonts::font_face_css( (string) $style['font_family'], $this->is_pdf() ? 'pdf' : 'html' );
		$css .= $this->partial( $template_id, 'base.css.php', $vars );
		$css .= $this->partial( $template_id, 'style.css.php', $vars );
		$css .= $this->partial( $template_id, 'custom.css', $vars );
		return (string) apply_filters( 'odocs_css', $css, $template_id, $style, $this );
	}

	protected function preview_script(): string {
		return '<script>(function(){document.addEventListener("click",function(e){var el=e.target.closest("[data-odocs-block]");if(!el)return;e.preventDefault();parent.postMessage({source:"odocs-preview",block:el.getAttribute("data-odocs-block")},"*");});window.addEventListener("message",function(e){if(!e.data||e.data.source!=="odocs-editor")return;document.querySelectorAll(".odocs-selected").forEach(function(n){n.classList.remove("odocs-selected");});if(e.data.block){var t=document.querySelector("[data-odocs-block=\""+e.data.block+"\"]");if(t){t.classList.add("odocs-selected");}}});})();</script>';
	}
}
