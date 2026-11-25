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
use OrderDocs\Settings\Schema;
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

	/** @var string Template being rendered (set by render()); drives template style defaults. */
	protected $current_template = '';

	/**
	 * Design settings, merged with overrides from the editor and the template's own style defaults.
	 *
	 * A template can ship its own defaults (e.g. a brand colour and font). They apply to every design
	 * setting that is still at the global default, so the user's own choices always win.
	 */
	public function style(): array {
		$style = Settings::section( 'design' );
		if ( ! empty( $this->overrides['style'] ) && is_array( $this->overrides['style'] ) ) {
			$style = array_merge( $style, array_intersect_key( $this->overrides['style'], $style ) );
		}
		$template = $this->current_template ? TemplateRegistry::get( $this->current_template ) : null;
		if ( $template && ! empty( $template['style'] ) && is_array( $template['style'] ) ) {
			$defaults = Schema::defaults( 'design' );
			foreach ( $template['style'] as $key => $value ) {
				if ( array_key_exists( $key, $style ) && strtolower( (string) $style[ $key ] ) === strtolower( (string) ( $defaults[ $key ] ?? '' ) ) ) {
					$style[ $key ] = $value;
				}
			}
		}
		return $style;
	}

	public function set_template( string $template_id ): void {
		$this->current_template = $template_id;
	}

	/**
	 * Colour palette derived from the accent colour. Templates can pin exact shades for their default accent.
	 *
	 * @return array{accent:string,accent_light:string,tint:string,line:string}
	 */
	public function palette(): array {
		$style   = $this->style();
		$accent  = (string) ( sanitize_hex_color( (string) $style['accent_color'] ) ?: '#1f4e79' );
		$palette = array(
			'accent'       => $accent,
			'accent_light' => self::mix( $accent, '#ffffff', 0.3 ),
			'tint'         => self::mix( $accent, '#ffffff', 0.92 ),
			'line'         => self::mix( $accent, '#ffffff', 0.82 ),
		);
		$template = $this->current_template ? TemplateRegistry::get( $this->current_template ) : null;
		if ( $template && ! empty( $template['palette'] ) && strtolower( $accent ) === strtolower( (string) ( $template['style']['accent_color'] ?? '' ) ) ) {
			$palette = array_merge( $palette, array_intersect_key( (array) $template['palette'], $palette ) );
		}
		return apply_filters( 'odocs_palette', $palette, $style, $this->current_template );
	}

	/**
	 * Mixes two hex colours; $amount is the share of $with (0..1).
	 */
	public static function mix( string $color, string $with, float $amount ): string {
		$a = self::rgb( $color );
		$b = self::rgb( $with );
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$out .= str_pad( dechex( (int) round( $a[ $i ] * ( 1 - $amount ) + $b[ $i ] * $amount ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	private static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return array( 0, 0, 0 );
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
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
			$layout = $this->layout( $document );
			if ( Layout::is_v2( $layout ) ) {
				$fixed = 0.0;
				foreach ( $layout['sections'] as $section ) {
					$fixed += 'flow' === $section['kind'] ? 0 : (float) $section['height'];
				}
				$height = max( 120, $fixed + 30 + 9 * $item_count );
			}
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
		$style  = $this->style();
		$footer = $this->footer_section( $document );
		$bottom = (float) $style['margin_bottom'];
		if ( $footer ) {
			// The page footer section lives in the bottom margin, so the margin is at least as tall as it.
			$bottom = max( $bottom, (float) $footer['height'] );
		}
		return array( (float) $style['margin_top'], (float) $style['margin_right'], $bottom, (float) $style['margin_left'] );
	}

	/**
	 * The page footer section of a free-form layout, if there is one.
	 */
	public function footer_section( Document $document ): ?array {
		$layout = $this->layout( $document );
		foreach ( $layout['sections'] ?? array() as $section ) {
			if ( 'footer' === $section['kind'] ) {
				return $section;
			}
		}
		return null;
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
		$this->set_template( $template_id );
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
		if ( Layout::is_v2( $layout ) ) {
			list( $rows, $fixed ) = $this->sections_html( $layout, $document, $data );
			$layout['rows']       = array();
		}
		foreach ( $layout['rows'] ?? array() as $row ) {
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

		$classes = 'odocs-document' . ( $index > 0 ? ' odocs-page-break' : '' ) . ( Layout::is_v2( $layout ) ? ' odocs-free' : '' );
		// Dompdf only repeats fixed elements on every page when they come before the document wrapper.
		$html = $this->is_pdf() ? $fixed : '';
		$html .= '<div class="' . esc_attr( $classes ) . '">';
		if ( ! $this->is_pdf() ) {
			$html .= '<div class="odocs-sheet">' . $fixed;
		}
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

	/**
	 * HTML of a free-form (version 2) layout.
	 *
	 * @return array [ body HTML, page footer HTML ]
	 */
	protected function sections_html( array $layout, Document $document, OrderData $data ): array {
		$preview = 'preview' === $this->mode;
		$body    = '';
		$footer  = '';
		foreach ( $layout['sections'] as $section ) {
			$attr = $preview ? ' data-odocs-section="' . esc_attr( $section['id'] ) . '"' : '';
			if ( 'flow' === $section['kind'] ) {
				$inner = '';
				foreach ( $section['items'] as $item ) {
					$inner .= $this->flow_item_html( $item, $document, $data );
				}
				if ( '' === $inner && ! $preview ) {
					continue;
				}
				$body .= sprintf( '<div class="odocs-section odocs-section-flow"%s>%s</div>', $attr, $inner );
				continue;
			}
			$inner = '';
			foreach ( array_values( $section['items'] ) as $z => $item ) {
				$inner .= $this->canvas_item_html( $item, $z + 1, $document, $data );
			}
			$height = (float) $section['height'];
			if ( 'footer' === $section['kind'] ) {
				if ( $this->is_pdf() && ! $this->bulk ) {
					// Repeated at the bottom of every page, inside the bottom margin.
					$margins = $this->margins( $document );
					$style   = sprintf( 'position:fixed;left:0;right:0;bottom:-%smm;height:%smm', (float) $margins[2], $height );
				} elseif ( $this->is_pdf() ) {
					// Several documents in one PDF: at the bottom of the last page of each document.
					$margins = $this->margins( $document );
					$style   = sprintf( 'position:absolute;left:0;right:0;bottom:-%smm;height:%smm', (float) $margins[2], $height );
				} else {
					$margins = $this->margins( $document );
					$paper   = $this->paper( $document );
					$style   = sprintf( 'position:absolute;left:%smm;width:%smm;bottom:0;height:%smm', (float) $margins[3], (float) $paper['width_mm'] - (float) $margins[1] - (float) $margins[3], $height );
				}
				$footer .= sprintf( '<div class="odocs-section odocs-section-footer" style="%s"%s>%s</div>', esc_attr( $style ), $attr, $inner );
				continue;
			}
			if ( '' === $inner && ! $preview && $height <= 0 ) {
				continue;
			}
			$style = sprintf( 'position:relative;height:%smm', $height );
			if ( ! empty( $section['keep_together'] ) ) {
				$style .= ';page-break-inside:avoid';
			}
			$body .= sprintf( '<div class="odocs-section odocs-section-canvas" style="%s"%s>%s</div>', esc_attr( $style ), $attr, $inner );
		}
		if ( $this->is_pdf() && $this->bulk ) {
			// After the content, so it lands on the document's last page.
			$body  .= $footer;
			$footer = '';
		}
		return array( $body, $footer );
	}

	/**
	 * A component placed freely: absolutely positioned at its spot, it never moves anything else.
	 */
	protected function canvas_item_html( array $item, int $z, Document $document, OrderData $data ): string {
		$inner = $this->block_html( $item, $document, $data, 'canvas' );
		if ( '' === $inner ) {
			return '';
		}
		$style = array(
			'position:absolute',
			'left:' . (float) $item['x'] . 'mm',
			'top:' . (float) $item['y'] . 'mm',
			'width:' . (float) $item['w'] . 'mm',
			'z-index:' . $z,
		);
		if ( 'clip' === $item['overflow'] ) {
			$style[] = 'height:' . (float) $item['h'] . 'mm';
			$style[] = 'overflow:hidden';
		} else {
			$style[] = 'min-height:' . (float) $item['h'] . 'mm';
		}
		$attr = 'preview' === $this->mode ? ' data-odocs-item="' . esc_attr( $item['id'] ) . '"' : '';
		return sprintf( '<div class="odocs-item" style="%s"%s>%s</div>', esc_attr( implode( ';', $style ) ), $attr, $inner );
	}

	/**
	 * A component in a flowing section: stacked under the previous one, can break across pages.
	 */
	protected function flow_item_html( array $item, Document $document, OrderData $data ): string {
		$inner = $this->block_html( $item, $document, $data, 'flow' );
		if ( '' === $inner ) {
			return '';
		}
		$style = sprintf( 'margin-left:%smm;width:%smm', (float) $item['x'], (float) $item['w'] );
		$attr  = 'preview' === $this->mode ? ' data-odocs-item="' . esc_attr( $item['id'] ) . '"' : '';
		return sprintf( '<div class="odocs-flow-item" style="%s"%s>%s</div>', esc_attr( $style ), $attr, $inner );
	}

	/**
	 * Whether rows and blocks carry their ids: in the editor preview, and when measuring a layout.
	 */
	protected function show_ids(): bool {
		return 'preview' === $this->mode || ! empty( $this->overrides['measure'] );
	}

	/**
	 * Box padding (vertical, horizontal in mm) of the "Box" setting for the current template.
	 */
	public function box_padding(): array {
		$template = TemplateRegistry::get( (string) $this->current_template ) ?: array();
		return array_map( 'floatval', $template['box_padding'] ?? array( 3, 4.5 ) );
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
		$attr    = $this->show_ids() ? ' data-odocs-row="' . esc_attr( $row['id'] ) . '"' : '';
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
	public function block_html( array $block, Document $document, OrderData $data, string $context = '' ): string {
		$types = Layout::block_types();
		if ( ! isset( $types[ $block['type'] ] ) ) {
			return '';
		}
		$settings = array_merge( Layout::sanitize_settings( array(), $types[ $block['type'] ]['settings'], $block['type'], $document->get_type() ), (array) $block['settings'] );
		$frame    = array(
			'context' => $context,
			'w'       => (float) ( $block['w'] ?? 0 ),
			'h'       => (float) ( $block['h'] ?? 0 ),
		);
		$inner    = $this->partial(
			$this->template_id( $document ),
			'blocks/' . $block['type'] . '.php',
			array(
				'block'    => $settings,
				'document' => $document,
				'data'     => $data,
				'r'        => $this,
				'frame'    => $frame,
			)
		);
		$inner = apply_filters( 'odocs_block_html', $inner, $block, $document, $data );
		if ( '' === trim( $inner ) ) {
			if ( 'preview' === $this->mode ) {
				$size = 'canvas' === $context ? sprintf( ' style="height:%smm;box-sizing:border-box;margin:0"', $frame['h'] ) : '';
				return sprintf( '<div class="odocs-block odocs-block-empty" data-odocs-block="%s"%s><span>%s</span></div>', esc_attr( $block['id'] ?? '' ), $size, esc_html( $types[ $block['type'] ]['label'] ) );
			}
			return '';
		}
		$align = (string) ( $settings['align'] ?? '' );
		$style = array( 'margin-bottom:' . ( 'canvas' === $context ? 0 : (float) ( $settings['margin_bottom'] ?? 0 ) ) . 'mm' );
		if ( $align ) {
			$style[] = 'text-align:' . $align;
		}
		if ( (float) ( $settings['font_size'] ?? 0 ) > 0 ) {
			$style[] = 'font-size:' . (float) $settings['font_size'] . 'pt';
		}
		if ( ! empty( $settings['text_color'] ) ) {
			$style[] = 'color:' . $this->color( $settings['text_color'] );
		}
		$box = (string) ( $settings['box'] ?? '' );
		if ( 'border' === $box ) {
			$style[] = 'border:0.75pt solid ' . $this->palette()['line'];
		} elseif ( 'filled' === $box ) {
			$style[] = 'background-color:' . $this->palette()['tint'];
		}
		if ( $box && 'canvas' === $context ) {
			// Dompdf has no box-sizing: the box fills its frame by taking the padding and border off the height.
			list( $pad_v, $pad_h ) = $this->box_padding();
			$style[] = sprintf( 'padding:%smm %smm', $pad_v, $pad_h );
			$style[] = 'min-height:' . max( 0, round( $frame['h'] - 2 * $pad_v - ( 'border' === $box ? 0.53 : 0 ), 2 ) ) . 'mm';
		}
		$attr = $this->show_ids() ? ' data-odocs-block="' . esc_attr( $block['id'] ?? '' ) . '"' : '';
		return sprintf(
			'<div class="odocs-block odocs-block-%s%s%s%s" style="%s"%s>%s</div>',
			esc_attr( str_replace( '_', '-', $block['type'] ) ),
			$align ? ' odocs-align-' . esc_attr( $align ) : '',
			! empty( $settings['text_color'] ) ? ' odocs-colored' : '',
			$box ? ' odocs-box odocs-box-' . esc_attr( $box ) : '',
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
		if ( in_array( $value, array( 'accent-light', 'tint', 'line' ), true ) ) {
			return $this->palette()[ str_replace( '-', '_', $value ) ];
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
		$this->set_template( $template_id );
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
			'heading_font' => ! empty( $template['heading_font'] ) ? Fonts::family_css( (string) $template['heading_font'] ) : Fonts::family_css( (string) $style['font_family'] ),
			'table_style' => $table_style,
			'mode'        => $this->mode,
			'palette'     => $this->palette(),
		);
		$css  = Fonts::font_face_css( (string) $style['font_family'], $this->is_pdf() ? 'pdf' : 'html' );
		if ( ! empty( $template['heading_font'] ) && $template['heading_font'] !== $style['font_family'] ) {
			$css .= Fonts::font_face_css( (string) $template['heading_font'], $this->is_pdf() ? 'pdf' : 'html' );
		}
		$css .= $this->partial( $template_id, 'base.css.php', $vars );
		$css .= $this->partial( $template_id, 'style.css.php', $vars );
		$css .= $this->partial( $template_id, 'custom.css', $vars );
		if ( ! $this->is_pdf() ) {
			$css = $this->match_pdf_line_heights( $css, (string) $style['font_family'], (string) ( $template['heading_font'] ?? '' ) );
		}
		return (string) apply_filters( 'odocs_css', $css, $template_id, $style, $this );
	}

	/**
	 * Scales the unitless line heights of a stylesheet so a browser sets lines as tall as Dompdf does.
	 * Rules that switch to the heading font use that font's factor.
	 */
	protected function match_pdf_line_heights( string $css, string $font, string $heading_font ): string {
		$body    = Fonts::line_factor( $font );
		$heading = '' !== $heading_font ? Fonts::line_factor( $heading_font ) : $body;
		$family  = '' !== $heading_font ? Fonts::get( $heading_font )['family'] : '';
		$scaled  = preg_replace_callback(
			'/([^{}]*)\{([^{}]*)\}/',
			static function ( $rule ) use ( $body, $heading, $family ) {
				$factor = ( '' !== $family && false !== strpos( $rule[2], $family ) ) ? $heading : $body;
				$decls  = preg_replace_callback(
					'/line-height\s*:\s*([0-9]*\.?[0-9]+)\s*(;|$|!)/',
					static function ( $m ) use ( $factor ) {
						return 'line-height: ' . round( (float) $m[1] * $factor, 4 ) . $m[2];
					},
					$rule[2]
				);
				return $rule[1] . '{' . $decls . '}';
			},
			$css
		);
		return null === $scaled ? $css : $scaled . sprintf( "\nhtml { line-height: %s; }\n", round( 1.2 * $body, 4 ) );
	}

	/**
	 * The editor drives the (same-origin) preview frame itself; this hook is kept for extensions.
	 */
	protected function preview_script(): string {
		return (string) apply_filters( 'odocs_preview_script', '' );
	}
}
