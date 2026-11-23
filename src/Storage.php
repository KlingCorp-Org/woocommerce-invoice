<?php
/**
 * Protected storage folders inside uploads: temp files, archived PDFs and the Dompdf font cache.
 *
 * @package OrderDocs
 */

namespace OrderDocs;

defined( 'ABSPATH' ) || exit;

class Storage {

	public static function base_dir(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( apply_filters( 'odocs_storage_dir', trailingslashit( $uploads['basedir'] ) . 'order-documents' ) );
	}

	public static function dir( string $sub ): string {
		$dir = self::base_dir() . trim( $sub, '/' ) . '/';
		if ( ! is_dir( $dir ) ) {
			self::ensure_dirs();
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	public static function ensure_dirs(): void {
		$base = self::base_dir();
		foreach ( array( '', 'tmp', 'archive', 'fonts' ) as $sub ) {
			$dir = $base . $sub;
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
		}
		// Deny direct web access (Apache) and directory listing; nginx users should add a location rule.
		if ( ! file_exists( $base . '.htaccess' ) ) {
			@file_put_contents( $base . '.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
		}
		foreach ( array( '', 'tmp', 'archive', 'fonts' ) as $sub ) {
			$index = trailingslashit( $base . $sub ) . 'index.php';
			if ( is_dir( $base . $sub ) && ! file_exists( $index ) ) {
				@file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore
			}
		}
	}

	/**
	 * Writes PDF data to a uniquely named temp folder and returns the path (used for e-mail attachments).
	 */
	public static function write_temp( string $filename, string $data ): string {
		$dir = self::dir( 'tmp/' . wp_generate_password( 16, false ) );
		$path = $dir . sanitize_file_name( $filename );
		file_put_contents( $path, $data ); // phpcs:ignore
		return $path;
	}

	public static function cleanup_temp(): void {
		$tmp = self::base_dir() . 'tmp/';
		if ( ! is_dir( $tmp ) ) {
			return;
		}
		$limit = time() - (int) apply_filters( 'odocs_temp_max_age', DAY_IN_SECONDS );
		foreach ( (array) glob( $tmp . '*', GLOB_ONLYDIR ) as $dir ) {
			if ( filemtime( $dir ) > $limit ) {
				continue;
			}
			foreach ( (array) glob( $dir . '/*' ) as $file ) {
				@unlink( $file ); // phpcs:ignore
			}
			@rmdir( $dir ); // phpcs:ignore
		}
	}
}
