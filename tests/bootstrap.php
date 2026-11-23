<?php
/**
 * Loads a real WordPress + WooCommerce install with this plugin active (integration tests).
 * Point WP_ROOT at a disposable test site: WP_ROOT=/path/to/wp vendor/bin/phpunit
 */
$wp_root = getenv( 'WP_ROOT' ) ?: '/opt/wp';
if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set WP_ROOT to a WordPress test site with WooCommerce and this plugin active.\n" );
	exit( 1 );
}
define( 'WP_USE_THEMES', false );
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require $wp_root . '/wp-load.php';
if ( ! class_exists( \OrderDocs\Plugin::class ) || ! function_exists( 'WC' ) ) {
	fwrite( STDERR, "WooCommerce and Order Documents must be active on the test site.\n" );
	exit( 1 );
}
require_once __DIR__ . '/TestCase.php';
