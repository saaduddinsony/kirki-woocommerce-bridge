<?php
/**
 * Coexistence with Kirki eCommerce and migration helpers.
 *
 * This plugin is a WooCommerce-backed alternative to Kirki eCommerce. Both can
 * be active at once: every identifier here is namespaced (kirki-woo-*,
 * kirki_woo_*, KirkiWoo\, REST kirki-woo/v1, abilities kirki-woo/*), while
 * Kirki eCommerce uses kecom / kirki_ecommerce / kirki-ecommerce. This class
 * reports possible overlaps and, when Kirki eCommerce is NOT active, keeps its
 * public shortcodes working on top of WooCommerce for sites that switched.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility layer.
 */
final class Compat {

	/**
	 * Shortcodes shipped by Kirki eCommerce that we can serve from WooCommerce.
	 *
	 * @var array<string,string> shortcode => our handler method.
	 */
	const KECOM_ALIASES = array(
		'kecom_mini_cart' => 'kecom_mini_cart',
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// After Kirki eCommerce (default priority) so we can see what it registered.
		add_action( 'init', array( __CLASS__, 'register_aliases' ), 99 );
	}

	/**
	 * Whether Kirki eCommerce is loaded.
	 *
	 * @return bool
	 */
	public static function kirki_ecommerce_active() {
		return defined( 'KIRKI_ECOMMERCE_VERSION' );
	}

	/**
	 * Whether the Kirki eCommerce shortcode aliases are enabled.
	 *
	 * @return bool
	 */
	public static function aliases_enabled() {
		/**
		 * Serve Kirki eCommerce shortcodes ([kecom_mini_cart]) from WooCommerce
		 * when Kirki eCommerce is not active.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Default true.
		 */
		return ! self::kirki_ecommerce_active() && (bool) apply_filters( 'kirki_woo_kecom_shortcode_aliases', true );
	}

	/**
	 * Register the aliases that nobody else owns.
	 *
	 * @return void
	 */
	public static function register_aliases() {
		if ( ! self::aliases_enabled() ) {
			return;
		}
		foreach ( self::KECOM_ALIASES as $tag => $method ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, array( __CLASS__, $method ) );
			}
		}
	}

	/**
	 * [kecom_mini_cart class=""] served by WooCommerce: a cart link with a live
	 * item count, like the Kirki eCommerce original.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public static function kecom_mini_cart( $atts ) {
		$atts  = shortcode_atts( array( 'class' => '' ), is_array( $atts ) ? $atts : array(), 'kecom_mini_cart' );
		$html  = Renderer::render(
			'mini-cart',
			array(
				'show_icon'  => true,
				'show_count' => true,
				'show_total' => false,
				'behavior'   => 'cart',
			)
		);
		$class = trim( 'kecom-mini-cart ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) ) );
		return '<span class="' . esc_attr( $class ) . '">' . $html . '</span>';
	}

	/**
	 * Store pages used by both plugins. A page assigned to both a WooCommerce
	 * page and a Kirki eCommerce page is a real conflict (two stores rendering
	 * into one page).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function page_conflicts() {
		if ( ! self::kirki_ecommerce_active() || ! function_exists( 'wc_get_page_id' ) ) {
			return array();
		}
		$utils = '\Kirki\Ecommerce\App\Supports\Utils';
		$map   = array(
			'shop'     => 'shop',
			'cart'     => 'cart',
			'checkout' => 'checkout',
			'account'  => 'myaccount',
		);
		$woo   = array();
		foreach ( $map as $woo_key ) {
			$id = (int) wc_get_page_id( $woo_key );
			if ( $id > 0 ) {
				$woo[ $id ] = $woo_key;
			}
		}
		$out = array();
		foreach ( $map as $ke_key => $woo_key ) {
			$method = 'get_' . $ke_key . '_page_id';
			if ( ! class_exists( $utils ) || ! method_exists( $utils, $method ) ) {
				continue;
			}
			try {
				$id = (int) call_user_func( array( $utils, $method ) );
			} catch ( \Throwable $e ) {
				continue;
			}
			if ( $id > 0 && isset( $woo[ $id ] ) ) {
				$out[] = array(
					'page_id'         => $id,
					'kirki_ecommerce' => $ke_key,
					'woocommerce'     => $woo[ $id ],
				);
			}
		}
		return $out;
	}

	/**
	 * Summary for the status endpoint and the admin screen.
	 *
	 * @return array<string,mixed>
	 */
	public static function report() {
		return array(
			'kirki_ecommerce_active'  => self::kirki_ecommerce_active(),
			'kecom_shortcode_aliases' => self::aliases_enabled() ? array_keys( self::KECOM_ALIASES ) : array(),
			'underscore_fix'          => self::kirki_ecommerce_active() && (bool) apply_filters( 'kirki_woo_underscore_compat', true ),
			'page_conflicts'          => self::page_conflicts(),
		);
	}
}
