<?php
/**
 * Design templates: bundled ones in /templates, plus any registered through the odocs_templates filter.
 * Files can be overridden from the theme: yourtheme/order-documents/{template}/... or .../shared/...
 *
 * @package OrderDocs
 */

namespace OrderDocs\Rendering;

defined( 'ABSPATH' ) || exit;

class TemplateRegistry {

	/** @var array<string,array>|null */
	private static $templates = null;

	public static function all(): array {
		if ( null === self::$templates ) {
			$templates = array();
			foreach ( array( 'classic', 'modern', 'minimal', 'business', 'compact', 'fresh', 'receipt-80mm' ) as $id ) {
				$dir  = ODOCS_DIR . 'templates/' . $id . '/';
				$meta = is_readable( $dir . 'template.php' ) ? include $dir . 'template.php' : array();
				if ( is_array( $meta ) ) {
					$templates[ $id ] = array_merge(
						array(
							'id'          => $id,
							'name'        => $id,
							'description' => '',
							'paper'       => null,
							'table_style' => 'lined',
							'dir'         => $dir,
							'url'         => ODOCS_URL . 'templates/' . $id . '/',
						),
						$meta
					);
				}
			}
			$templates = (array) apply_filters( 'odocs_templates', $templates );
			foreach ( $templates as $id => &$template ) {
				$template['id']  = $id;
				$template['dir'] = trailingslashit( $template['dir'] ?? '' );
			}
			self::$templates = $templates;
		}
		return self::$templates;
	}

	public static function get( string $id ): ?array {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	public static function options(): array {
		return wp_list_pluck( self::all(), 'name' );
	}

	public static function thumbnail_url( string $id ): string {
		$template = self::get( $id );
		if ( $template && ! empty( $template['url'] ) && is_readable( $template['dir'] . 'thumbnail.png' ) ) {
			return $template['url'] . 'thumbnail.png';
		}
		return '';
	}

	/**
	 * Finds a template file: theme override, then template folder, then the shared folder.
	 */
	public static function locate( string $template_id, string $relative ): ?string {
		$relative   = ltrim( $relative, '/' );
		$candidates = array();
		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $theme_dir ) {
			$candidates[] = "{$theme_dir}/order-documents/{$template_id}/{$relative}";
			$candidates[] = "{$theme_dir}/order-documents/shared/{$relative}";
		}
		$template = self::get( $template_id );
		if ( $template ) {
			$candidates[] = $template['dir'] . $relative;
		}
		$candidates[] = ODOCS_DIR . 'templates/shared/' . $relative;
		$candidates   = apply_filters( 'odocs_template_file_candidates', $candidates, $template_id, $relative );
		foreach ( $candidates as $file ) {
			if ( is_readable( $file ) ) {
				return $file;
			}
		}
		return null;
	}
}
