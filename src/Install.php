<?php
/**
 * Activation, database tables and upgrades.
 *
 * @package OrderDocs
 */

namespace OrderDocs;

defined( 'ABSPATH' ) || exit;

class Install {

	const DB_VERSION     = '1';
	const CLEANUP_HOOK   = 'odocs_cleanup_temp';
	const VERSION_OPTION = 'odocs_db_version';

	public static function activate(): void {
		self::create_tables();
		Storage::ensure_dirs();
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
		update_option( self::VERSION_OPTION, self::DB_VERSION );
		// Remember that the plugin was activated on a store with existing WPO data, so the import notice shows.
		if ( false === get_option( 'odocs_wpo_detected' ) ) {
			update_option( 'odocs_wpo_detected', Migration\WpoImporter::has_wpo_data() ? 'yes' : 'no', false );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::DB_VERSION ) {
			self::activate();
		}
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = Documents\Number\Sequence::table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(120) NOT NULL,
  next_value bigint(20) unsigned NOT NULL DEFAULT 1,
  updated_at datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY name (name)
) {$collate};"
		);
	}
}
