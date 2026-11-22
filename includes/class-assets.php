<?php
/**
 * Script and style loading.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Loads only what rendered elements need, including the WooCommerce scripts
 * (gallery, variations, cart, checkout) that WooCommerce itself only loads on
 * its own page types.
 */
final class Assets {

	/**
	 * Requested asset groups.
	 *
	 * @var array<string,bool>
	 */
	private static $needed = array();

	/**
	 * Whether a gallery rendered on this request.
	 *
	 * @var bool
	 */
	private static $gallery = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'editor' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'photoswipe_template' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'underscore_compat' ), 1 );
		add_filter( 'woocommerce_single_product_zoom_enabled', array( __CLASS__, 'gallery_flag' ) );
		add_filter( 'woocommerce_single_product_flexslider_enabled', array( __CLASS__, 'gallery_flag' ) );
		add_filter( 'woocommerce_single_product_photoswipe_enabled', array( __CLASS__, 'gallery_flag' ) );
	}

	/**
	 * Asset version (file mtime in development).
	 *
	 * @param string $rel Relative path.
	 * @return string
	 */
	private static function ver( $rel ) {
		$file = KIRKI_WOO_PATH . $rel;
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $file ) ) ? KIRKI_WOO_VERSION . '.' . filemtime( $file ) : KIRKI_WOO_VERSION;
	}

	/**
	 * Register our handles.
	 *
	 * @return void
	 */
	public static function register() {
		wp_register_style( 'kirki-woo', KIRKI_WOO_URL . 'assets/css/frontend.css', array(), self::ver( 'assets/css/frontend.css' ) );
		wp_register_script( 'kirki-woo', KIRKI_WOO_URL . 'assets/js/frontend.js', array(), self::ver( 'assets/js/frontend.js' ), true );
		wp_localize_script(
			'kirki-woo',
			'kirkiWoo',
			array(
				'miniCartAutoOpen' => (bool) Settings::get( 'mini_cart_auto_open' ),
				'i18n'             => array(
					'added' => __( 'Added to cart', 'kirki-woocommerce-bridge' ),
				),
			)
		);

		// Inside the Kirki canvas iframe the base styles are always needed for previews.
		if ( Context::is_editor_iframe() ) {
			self::need( 'base' );
		}
	}

	/**
	 * Enqueue an asset group.
	 *
	 * @param string $key Group.
	 * @return void
	 */
	public static function need( $key ) {
		if ( isset( self::$needed[ $key ] ) ) {
			return;
		}
		self::$needed[ $key ] = true;
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! did_action( 'wp_enqueue_scripts' ) && ! doing_action( 'wp_enqueue_scripts' ) ) {
			// Rendering before scripts are registered (template_include): defer.
			add_action(
				'wp_enqueue_scripts',
				static function () use ( $key ) {
					self::enqueue( $key );
				},
				30
			);
			return;
		}
		self::enqueue( $key );
	}

	/**
	 * Requested groups (REST previews report them).
	 *
	 * @return string[]
	 */
	public static function needed() {
		return array_keys( self::$needed );
	}

	/**
	 * Enqueue handles for a group.
	 *
	 * @param string $key Group.
	 * @return void
	 */
	private static function enqueue( $key ) {
		$scripts = array();
		$styles  = array();
		switch ( $key ) {
			case 'base':
				if ( Settings::get( 'load_styles' ) ) {
					$styles[] = 'kirki-woo';
				}
				$scripts[] = 'kirki-woo';
				break;
			case 'gallery':
				self::$gallery = true;
				$scripts       = array( 'wc-zoom', 'wc-flexslider', 'wc-photoswipe-ui-default', 'wc-single-product' );
				$styles        = array( 'photoswipe-default-skin' );
				break;
			case 'add_to_cart':
				$scripts = array( 'wc-add-to-cart', 'wc-add-to-cart-variation', 'wc-single-product' );
				break;
			case 'single':
				$scripts = array( 'wc-single-product' );
				break;
			case 'loop':
				$scripts = array( 'wc-add-to-cart' );
				break;
			case 'mini_cart':
				$scripts = array( 'wc-cart-fragments', 'wc-add-to-cart' );
				break;
			case 'cart':
				$scripts = array( 'wc-cart', 'wc-country-select', 'selectWoo' );
				$styles  = array( 'select2' );
				break;
			case 'checkout':
				$scripts = array( 'wc-checkout', 'wc-country-select', 'wc-address-i18n', 'selectWoo' );
				$styles  = array( 'select2' );
				break;
		}
		foreach ( $scripts as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}
		foreach ( $styles as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
			}
		}
	}

	/**
	 * Turn gallery features on when our gallery element renders.
	 *
	 * @param bool $enabled Enabled.
	 * @return bool
	 */
	public static function gallery_flag( $enabled ) {
		return self::$gallery ? true : $enabled;
	}

	/**
	 * Print the PhotoSwipe markup when the theme does not.
	 *
	 * @return void
	 */
	public static function photoswipe_template() {
		if ( ! self::$gallery || current_theme_supports( 'wc-product-gallery-lightbox' ) ) {
			return;
		}
		wc_get_template( 'single-product/photoswipe.php' );
	}

	/**
	 * Editor assets: registers our elements with the Kirki editor.
	 *
	 * @return void
	 */
	public static function editor() {
		if ( ! Context::is_editor_request() || Context::is_editor_iframe() ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_register_script( 'kirki-woo-editor', KIRKI_WOO_URL . 'assets/js/editor.js', array(), self::ver( 'assets/js/editor.js' ), true );
		wp_register_style( 'kirki-woo-editor', KIRKI_WOO_URL . 'assets/css/editor.css', array(), self::ver( 'assets/css/editor.css' ) );
		wp_localize_script( 'kirki-woo-editor', 'kirkiWooEditor', Kirki_Bridge::editor_config() );
		wp_enqueue_script( 'kirki-woo-editor' );
		wp_enqueue_style( 'kirki-woo-editor' );

		// Kirki dequeues everything that is not in this list on its editor screen.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Kirki's own global.
		global $kirki_editor_assets;
		if ( ! is_array( $kirki_editor_assets ) ) {
			$kirki_editor_assets = array(
				'scripts' => array(),
				'styles'  => array(),
			);
		}
		$kirki_editor_assets['scripts'][] = 'kirki-woo-editor';
		$kirki_editor_assets['styles'][]  = 'kirki-woo-editor';
		// phpcs:enable
	}

	/**
	 * Keep WooCommerce variation forms working next to Kirki eCommerce.
	 *
	 * Kirki eCommerce 1.0.x ships its storefront bundle (site.js) as a classic
	 * script whose minified top-level `var _ = []` replaces window._ (Underscore).
	 * WooCommerce's variation form relies on wp.template(), which calls
	 * _.template(), so variable products stop showing prices and cannot be added
	 * to the cart. Kirki eCommerce keeps using its own array; we only copy the
	 * Underscore helpers that the array does not already have onto it.
	 *
	 * Disable with: add_filter( 'kirki_woo_underscore_compat', '__return_false' ).
	 *
	 * @return void
	 */
	public static function underscore_compat() {
		$handle = 'kirki-ecommerce-site-scripts';
		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}
		/**
		 * Whether to repair window._ after Kirki eCommerce's storefront script.
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'kirki_woo_underscore_compat', true ) ) {
			return;
		}
		wp_add_inline_script( $handle, 'window.kirkiWooUnderscore = window._;', 'before' );
		wp_add_inline_script(
			$handle,
			'(function(u){var k=window._;if(!u||k===u||!k||"function"===typeof k.template){return;}Object.keys(u).forEach(function(n){if(!(n in k)){try{k[n]=u[n];}catch(e){}}});})(window.kirkiWooUnderscore);',
			'after'
		);
	}
}
