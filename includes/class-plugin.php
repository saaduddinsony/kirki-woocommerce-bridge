<?php
/**
 * Plugin bootstrap.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every subsystem together once WooCommerce and Kirki are available.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared instance, booting the plugin on first call.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Activation hook: seed default settings.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', false );
		}
		if ( false === get_option( Templates::OPTION, false ) ) {
			add_option( Templates::OPTION, array(), '', false );
		}
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );

		$deps = Dependencies::check();
		if ( ! $deps['woocommerce'] || ! $deps['kirki'] ) {
			add_action( 'admin_notices', array( '\KirkiWoo\Dependencies', 'render_notice' ) );
			// The REST status route still answers so tools can see what is missing.
			add_action( 'rest_api_init', array( '\KirkiWoo\Rest_Controller', 'register_status_only' ) );
			return;
		}

		Shop_Filters::init();
		Shortcodes::init();
		Compat::init();
		Dynamic_Data::init();
		Kirki_Bridge::init();
		Templates::init();
		Assets::init();
		Fragments::init();
		Rest_Controller::init();
		Abilities::init();
		if ( is_admin() ) {
			Admin::init();
		}

		/**
		 * Fires after Kirki WooCommerce Bridge has booted.
		 *
		 * @since 1.0.0
		 */
		do_action( 'kirki_woo_loaded' );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'kirki-woocommerce-bridge', false, dirname( plugin_basename( KIRKI_WOO_FILE ) ) . '/languages' );
	}
}
