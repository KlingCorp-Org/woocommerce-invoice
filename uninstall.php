<?php
/**
 * Uninstall: removes settings, layouts and sequences only when "Delete settings on uninstall" is enabled.
 * Document data on orders and archived PDFs are always kept (bookkeeping records).
 *
 * @package OrderDocs
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$advanced = get_option( 'odocs_advanced', array() );
if ( empty( $advanced['delete_data'] ) ) {
	return;
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'odocs\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}odocs_sequences" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_clear_scheduled_hook( 'odocs_cleanup_temp' );
