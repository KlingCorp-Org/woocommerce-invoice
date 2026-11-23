<?php
/**
 * Archived PDF copies: the first generated PDF of a document is kept and served from then on.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

use OrderDocs\Storage;

defined( 'ABSPATH' ) || exit;

class Archive {

	public static function enabled( Document $document ): bool {
		return $document->exists() && ! $document->is_legacy() && ! $document::is_multi_order() && (bool) $document->setting( 'archive', 0 );
	}

	/** Absolute path of the archived file, or null when there is none. */
	public static function get( Document $document ): ?string {
		$data = $document->get_data();
		if ( empty( $data['archive'] ) ) {
			return null;
		}
		$path = Storage::base_dir() . 'archive/' . ltrim( (string) $data['archive'], '/' );
		return is_readable( $path ) ? $path : null;
	}

	public static function store( Document $document, string $pdf ): void {
		if ( ! self::enabled( $document ) ) {
			return;
		}
		$date     = $document->get_date() ?: new \WC_DateTime();
		$relative = $date->date( 'Y' ) . '/' . sanitize_file_name( $document->get_key() . '-' . wp_generate_password( 12, false ) . '.pdf' );
		$dir      = Storage::dir( 'archive/' . $date->date( 'Y' ) );
		if ( false === file_put_contents( $dir . basename( $relative ), $pdf ) ) { // phpcs:ignore
			return;
		}
		$fresh = DocumentRegistry::make( $document->get_type(), $document->get_order(), $document->get_refund() );
		if ( $fresh && $fresh->exists() ) {
			$storage = $fresh->storage();
			$data    = $fresh->get_data();
			$data['archive'] = $relative;
			$storage->update_meta_data( $fresh->meta_key(), $data );
			$storage->save_meta_data();
		}
	}

	/** Removes the archived copy so the next request renders a fresh PDF. */
	public static function delete( Document $document ): void {
		$path = self::get( $document );
		if ( $path ) {
			@unlink( $path ); // phpcs:ignore
		}
		$data = $document->get_data();
		if ( isset( $data['archive'] ) && ! $document->is_legacy() ) {
			unset( $data['archive'] );
			$storage = $document->storage();
			$storage->update_meta_data( $document->meta_key(), $data );
			$storage->save_meta_data();
		}
	}
}
