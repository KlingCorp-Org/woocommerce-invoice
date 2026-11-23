<?php
/**
 * Dompdf wrapper.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

use OrderDocs\Documents\Document;
use OrderDocs\Storage;
use OrderDocs\Vendor\Dompdf\Dompdf;
use OrderDocs\Vendor\Dompdf\Options;

defined( 'ABSPATH' ) || exit;

class PdfGenerator {

	/**
	 * Renders documents (all of the same type) into one PDF and returns the PDF bytes.
	 *
	 * @param Document[] $documents Documents.
	 * @param array      $overrides Renderer overrides (template, layout, style) from the editor.
	 */
	public static function documents( array $documents, array $overrides = array() ): string {
		$documents = array_values( $documents );
		$renderer  = new Renderer( 'pdf', $overrides );
		$html      = $renderer->render( $documents );
		$first     = $documents[0];
		$items     = 0;
		if ( 1 === count( $documents ) ) {
			$items = count( ( new OrderData( $first, 'html' ) )->items() );
		}
		$paper   = $renderer->paper( $first, $items );
		$margins = $renderer->margins( $first );
		$style   = $renderer->style();
		return self::html_to_pdf(
			$html,
			$paper['size'],
			array(
				'page_numbers' => 1 === count( $documents ) && ! empty( $style['page_numbers'] ) && ! is_array( $paper['size'] ),
				'font'         => Fonts::get( (string) $style['font_family'] )['family'],
				'color'        => (string) $style['muted_color'],
				'margins'      => $margins,
				'label'        => $renderer->with_locale(
					$first,
					static function () {
						/* translators: 1: page number, 2: page count. Keep {PAGE_NUM} style placeholders out; numbers are filled in. */
						return __( 'Page %1$d of %2$d', 'order-documents' );
					}
				),
			)
		);
	}

	/**
	 * @param string       $html    HTML.
	 * @param string|array $size    Paper size name or [0, 0, width, height] in points.
	 * @param array        $args    page_numbers, font, color, margins (mm), label.
	 */
	public static function html_to_pdf( string $html, $size = 'a4', array $args = array() ): string {
		if ( ! class_exists( Dompdf::class ) ) {
			throw new \RuntimeException( 'The PDF library is missing. Reinstall the plugin.' );
		}
		$uploads = wp_upload_dir( null, false );
		$options = new Options();
		$options->setIsRemoteEnabled( false );
		$options->setIsPhpEnabled( false );
		$options->setIsJavascriptEnabled( false );
		$options->setIsFontSubsettingEnabled( true );
		$options->setDefaultFont( 'DejaVu Sans' );
		$options->setDpi( 96 );
		$options->setDefaultMediaType( 'print' );
		$options->setChroot(
			array_values(
				array_unique(
					array_filter(
						array(
							untrailingslashit( ODOCS_DIR ),
							untrailingslashit( WP_CONTENT_DIR ),
							untrailingslashit( $uploads['basedir'] ),
							untrailingslashit( get_stylesheet_directory() ),
							untrailingslashit( get_template_directory() ),
						)
					)
				)
			)
		);
		$font_dir = Storage::dir( 'fonts' );
		$options->setFontDir( $font_dir );
		$options->setFontCache( $font_dir );
		$options->setTempDir( Storage::dir( 'tmp' ) );
		$options = apply_filters( 'odocs_dompdf_options', $options );

		$dompdf = new Dompdf( $options );
		$dompdf->setPaper( $size, 'portrait' );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->render();

		if ( ! empty( $args['page_numbers'] ) ) {
			$label   = (string) ( $args['label'] ?? 'Page %1$d of %2$d' );
			$margins = $args['margins'] ?? array( 15, 15, 20, 15 );
			$color   = self::hex_to_rgb( (string) ( $args['color'] ?? '#666666' ) );
			$family  = (string) ( $args['font'] ?? 'DejaVu Sans' );
			$dompdf->getCanvas()->page_script(
				static function ( $page, $count, $canvas, $metrics ) use ( $label, $margins, $color, $family ) {
					if ( $count < 2 ) {
						return;
					}
					$font = $metrics->getFont( $family ) ?: $metrics->getFont( 'DejaVu Sans' );
					$size = 7;
					$text = sprintf( $label, $page, $count );
					$w    = $metrics->getTextWidth( $text, $font, $size );
					$x    = $canvas->get_width() - Renderer::mm_to_pt( (float) $margins[1] ) - $w;
					$y    = $canvas->get_height() - Renderer::mm_to_pt( max( 4, (float) $margins[2] / 2 ) );
					$canvas->text( $x, $y, $text, $font, $size, $color );
				}
			);
		}
		return (string) $dompdf->output();
	}

	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return array( 0.4, 0.4, 0.4 );
		}
		return array( hexdec( substr( $hex, 0, 2 ) ) / 255, hexdec( substr( $hex, 2, 2 ) ) / 255, hexdec( substr( $hex, 4, 2 ) ) / 255 );
	}
}
