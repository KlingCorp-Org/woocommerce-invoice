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
				'helvetica'    => array( 'label' => __( 'Helvetica (built-in, smallest files)', 'order-documents' ), 'family' => 'Helvetica', 'files' => array(), 'fallback' => 'sans-serif' ),
			)
		);
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
