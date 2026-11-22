<?php
/**
 * PHPUnit bootstrap.
 *
 * Tests run inside a real WordPress install that has WooCommerce, Kirki and
 * this plugin active (see .github/workflows/ci.yml and bin/setup-test-site.sh).
 * Point KIRKI_WOO_WP_LOAD at that install's wp-load.php.
 *
 * @package KirkiWoo
 */

$kirki_woo_wp_load = getenv( 'KIRKI_WOO_WP_LOAD' );
if ( ! $kirki_woo_wp_load || ! file_exists( $kirki_woo_wp_load ) ) {
	fwrite( STDERR, "Set KIRKI_WOO_WP_LOAD to the wp-load.php of a test site with WooCommerce + Kirki + this plugin active.\n" );
	exit( 1 );
}

require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define( 'WP_USE_THEMES', false );
// PHPUnit includes this file inside a method, so declare the globals that
// WordPress initialises at file scope (same list as the core test suite).
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
global $wpdb, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp, $phpmailer, $wp_theme_directories, $wp_version, $wp_db_version, $wp_local_package, $wp_query, $wp_the_query, $wp_roles, $wp_locale, $wp_embed, $wp_widget_factory, $wp_filter, $wp_actions, $wp_current_filter, $wp_post_types, $wp_taxonomies, $wp_scripts, $wp_styles, $table_prefix, $allowedposttags, $allowedtags, $allowedentitynames, $l10n, $pagenow;
require_once $kirki_woo_wp_load;

if ( ! class_exists( '\KirkiWoo\Plugin' ) ) {
	fwrite( STDERR, "Kirki WooCommerce Bridge is not active on the test site.\n" );
	exit( 1 );
}
if ( function_exists( 'WC' ) && null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
	wc_load_cart();
}
do_action( 'rest_api_init', rest_get_server() );

require_once __DIR__ . '/class-test-case.php';
