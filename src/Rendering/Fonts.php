<?php
/**
 * Fonts available in documents.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

defined( 'ABSPATH' ) || exit;

class Fonts {

	/**
	 * @return array<string,array{label:string,family:string,files:array,fallback:string}>
	 */
	public static function all(): array {
		$bundled = static function ( string $dir, string $prefix ) {
			return array(
				'normal'      => "{$dir}/{$prefix}_400Regular.ttf",
				'bold'        => "{$dir}/{$prefix}_700Bold.ttf",
				'italic'      => "{$dir}/{$prefix}_400Regular_Italic.ttf",
				'bold_italic' => "{$dir}/{$prefix}_700Bold_Italic.ttf",
			);
		};
		return apply_filters(
			'odocs_fonts',
			array(
				'dejavu-sans'  => array( 'label' => 'DejaVu Sans', 'family' => 'DejaVu Sans', 'files' => array(), 'fallback' => 'sans-serif' ),
				'dejavu-serif' => array( 'label' => 'DejaVu Serif', 'family' => 'DejaVu Serif', 'files' => array(), 'fallback' => 'serif' ),
				'open-sans'    => array( 'label' => 'Open Sans', 'family' => 'Open Sans', 'files' => $bundled( 'open-sans', 'OpenSans' ), 'fallback' => 'sans-serif' ),
				'roboto'       => array( 'label' => 'Roboto', 'family' => 'Roboto', 'files' => $bundled( 'roboto', 'Roboto' ), 'fallback' => 'sans-serif' ),
				'lato'         => array( 'label' => 'Lato', 'family' => 'Lato', 'files' => $bundled( 'lato', 'Lato' ), 'fallback' => 'sans-serif' ),
				'merriweather' => array( 'label' => 'Merriweather', 'family' => 'Merriweather', 'files' => $bundled( 'merriweather', 'Merriweather' ), 'fallback' => 'serif' ),
				'nunito'       => array( 'label' => 'Nunito', 'family' => 'Nunito', 'files' => $bundled( 'nunito', 'Nunito' ), 'fallback' => 'sans-serif' ),
				'fredoka'      => array(
					'label'    => 'Fredoka',
					'family'   => 'Fredoka',
					'files'    => array(
						'normal' => 'fredoka/Fredoka_400Regular.ttf',
						'bold'   => 'fredoka/Fredoka_600SemiBold.ttf',
					),
					'fallback' => 'sans-serif',
				),
				'helvetica'    => array( 'label' => __( 'Helvetica (built-in, smallest files)', 'order-documents' ), 'family' => 'Helvetica', 'files' => array(), 'fallback' => 'sans-serif' ),
			)
		);
	}

	/**
	 * How much taller Dompdf sets lines than browsers for a font.
	 *
	 * Dompdf multiplies line-height by the font's own height (ascender to descender) times its
	 * font height ratio, where browsers multiply it by the font size. The preview scales its
	 * line heights by this factor so it matches the PDF. Measured once per font with Dompdf itself.
	 */
	public static function line_factor( string $id ): float {
		$key   = $id . '@' . ( defined( 'ODOCS_VERSION' ) ? ODOCS_VERSION : '' );
		$cache = get_option( 'odocs_line_factors', array() );
		$cache = is_array( $cache ) ? $cache : array();
		if ( isset( $cache[ $key ] ) ) {
			return (float) $cache[ $key ];
		}
		$factor = 1.0;
		try {
			$html   = '<html><head><style>' . self::font_face_css( $id, 'pdf' ) . 'body{margin:0;font-family:' . self::family_css( $id ) . ';font-size:20pt;line-height:1}</style></head><body><div id="a">Ag</div><div id="b">Ag</div></body></html>';
			$y      = array();
			$dompdf = PdfGenerator::dompdf();
			$dompdf->setPaper( 'a4', 'portrait' );
			// Frames are freed page by page, so positions are read while rendering.
			$dompdf->setCallbacks(
				array(
					array(
						'event' => 'begin_frame',
						'f'     => static function ( $frame ) use ( &$y ) {
							$node = $frame->get_node();
							if ( $node instanceof \DOMElement && in_array( $node->getAttribute( 'id' ), array( 'a', 'b' ), true ) ) {
								$y[ $node->getAttribute( 'id' ) ] = (float) $frame->get_position( 'y' );
							}
						},
					),
				)
			);
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->render();
			if ( isset( $y['a'], $y['b'] ) && $y['b'] > $y['a'] ) {
				$factor = round( ( $y['b'] - $y['a'] ) / 20, 4 );
			}
		} catch ( \Throwable $e ) {
			$factor = 1.0;
		}
		$factor        = max( 0.8, min( 2.5, $factor ) );
		$cache         = array_slice( $cache, -30, null, true );
		$cache[ $key ] = $factor;
		update_option( 'odocs_line_factors', $cache, false );
		return $factor;
	}

	public static function options(): array {
		return wp_list_pluck( self::all(), 'label' );
	}

	public static function get( string $id ): array {
		$all = self::all();
		return $all[ $id ] ?? $all['dejavu-sans'];
	}

	public static function family_css( string $id ): string {
		$font = self::get( $id );
		return '"' . $font['family'] . '", ' . ( 'serif' === $font['fallback'] ? '"DejaVu Serif"' : '"DejaVu Sans"' ) . ', ' . $font['fallback'];
	}

	/**
	 * @font-face rules for bundled fonts. In PDF mode the source is a local path, in HTML mode a URL.
	 */
	public static function font_face_css( string $id, string $mode ): string {
		$font = self::get( $id );
		if ( empty( $font['files'] ) && 'pdf' !== $mode && 0 === strpos( $font['family'], 'DejaVu' ) ) {
			// Built into Dompdf; served to the browser too, so the preview wraps text exactly like the PDF.
			$base = ODOCS_URL . 'vendor-prefixed/dompdf/dompdf/lib/fonts/' . str_replace( ' ', '', $font['family'] );
			$css  = '';
			foreach ( array( '' => array( 'normal', 'normal' ), '-Bold' => array( 'bold', 'normal' ) ) as $suffix => $variant ) {
				$css .= sprintf( "@font-face { font-family: '%s'; src: url('%s') format('truetype'); font-weight: %s; font-style: %s; }\n", $font['family'], esc_url_raw( $base . $suffix . '.ttf' ), $variant[0], $variant[1] );
			}
			return $css;
		}
		if ( empty( $font['files'] ) ) {
			return '';
		}
		$css = '';
		$map = array(
			'normal'      => array( 'normal', 'normal' ),
			'bold'        => array( 'bold', 'normal' ),
			'italic'      => array( 'normal', 'italic' ),
			'bold_italic' => array( 'bold', 'italic' ),
		);
		foreach ( $font['files'] as $variant => $file ) {
			$path = 0 === strpos( $file, '/' ) ? $file : ODOCS_DIR . 'assets/fonts/' . $file;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$src = 'pdf' === $mode ? $path : ODOCS_URL . 'assets/fonts/' . $file;
			list( $weight, $style ) = $map[ $variant ];
			$css .= sprintf(
				"@font-face { font-family: '%s'; src: url('%s') format('truetype'); font-weight: %s; font-style: %s; }\n",
				esc_attr( $font['family'] ),
				esc_url_raw( $src, array( 'http', 'https', 'file' ) ) ?: $src,
				$weight,
				$style
			);
		}
		return $css;
	}
}
