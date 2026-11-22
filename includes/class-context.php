<?php
/**
 * Render context: which product, which page, editor or frontend.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the product an element should render and safely swaps the
 * WooCommerce/WordPress globals while it renders.
 */
final class Context {

	/**
	 * Product forced by an outer scope (REST preview, collections).
	 *
	 * @var \WC_Product[]
	 */
	private static $stack = array();

	/**
	 * Whether the current request is the Kirki editor shell or its canvas iframe.
	 *
	 * @return bool
	 */
	public static function is_editor_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing flag set by Kirki.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		return 'kirki' === $action;
	}

	/**
	 * Whether this is the Kirki canvas iframe.
	 *
	 * @return bool
	 */
	public static function is_editor_iframe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing flag set by Kirki.
		$load_for = isset( $_GET['load_for'] ) ? sanitize_key( wp_unslash( $_GET['load_for'] ) ) : '';
		return self::is_editor_request() && 'kirki-iframe' === $load_for;
	}

	/**
	 * Push a product onto the context stack.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	public static function push( $product ) {
		self::$stack[] = $product;
	}

	/**
	 * Pop the context stack.
	 *
	 * @return void
	 */
	public static function pop() {
		array_pop( self::$stack );
	}

	/**
	 * Resolve the product to render.
	 *
	 * Order: explicit id > Kirki collection item > pushed scope > queried product
	 * > global $product > preview product (editor, templates, REST only).
	 *
	 * @param int                 $explicit_id Product id from element settings (0 = auto).
	 * @param array<string,mixed> $options     Kirki render options.
	 * @param bool                $allow_preview Fall back to the preview product.
	 * @return \WC_Product|null
	 */
	public static function product( $explicit_id = 0, array $options = array(), $allow_preview = null ) {
		if ( $explicit_id > 0 ) {
			$product = wc_get_product( $explicit_id );
			return self::visible( $product ) ? $product : null;
		}

		if ( isset( $options['post'] ) && is_object( $options['post'] ) && isset( $options['post']->ID ) ) {
			$product = wc_get_product( (int) $options['post']->ID );
			if ( $product ) {
				return self::visible( $product ) ? $product : null;
			}
		}

		if ( ! empty( self::$stack ) ) {
			return end( self::$stack );
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				return $product;
			}
		}

		global $product;
		if ( $product instanceof \WC_Product ) {
			return $product;
		}

		if ( null === $allow_preview ) {
			$allow_preview = self::is_editor_request() || self::is_template_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		}
		return $allow_preview ? self::preview_product() : null;
	}

	/**
	 * Editor canvas or REST preview.
	 *
	 * @return bool
	 */
	public static function is_preview() {
		return self::is_editor_request() || self::is_editor_iframe() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Whether a Kirki template / utility / symbol is being viewed directly.
	 *
	 * @return bool
	 */
	public static function is_template_preview() {
		if ( ! is_singular() ) {
			return false;
		}
		$type = get_post_type( get_queried_object_id() );
		return in_array( $type, array( 'kirki_template', 'kirki_utility', 'kirki_symbol', 'kirki_popup' ), true );
	}

	/**
	 * Preview product (setting or newest product).
	 *
	 * @return \WC_Product|null
	 */
	public static function preview_product() {
		$id = (int) Settings::get( 'preview_product_id' );
		if ( $id > 0 ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				return $product;
			}
		}
		$ids = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);
		return $ids ? wc_get_product( (int) $ids[0] ) : null;
	}

	/**
	 * Products that are not published are only visible to users who can edit them.
	 *
	 * @param mixed $product Product.
	 * @return bool
	 */
	public static function visible( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}
		if ( 'publish' === $product->get_status() ) {
			return true;
		}
		return current_user_can( 'read_product', $product->get_id() ) || current_user_can( 'edit_post', $product->get_id() );
	}

	/**
	 * Run a callback with $product / $post globals pointing at a product, then
	 * restore them. Returns the callback's output buffer.
	 *
	 * @param \WC_Product $product  Product.
	 * @param callable    $callback Callback that echoes markup.
	 * @return string
	 */
	public static function with_product( $product, callable $callback ) {
		global $post;
		$previous_post    = isset( $post ) ? $post : null;
		$previous_product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;

		$post_object = get_post( $product->get_id() );
		if ( $post_object ) {
			$post = $post_object; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
			setup_postdata( $post_object );
		}
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce global, restored below.

		self::push( $product );
		ob_start();
		try {
			call_user_func( $callback, $product );
		} finally {
			$html = (string) ob_get_clean();
			self::pop();
			$post               = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring.
			$GLOBALS['product'] = $previous_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Restoring the WooCommerce global.
			if ( $previous_post instanceof \WP_Post ) {
				setup_postdata( $previous_post );
			}
		}
		return $html;
	}

	/**
	 * Capture output of a callback.
	 *
	 * @param callable $callback Callback.
	 * @return string
	 */
	public static function capture( callable $callback ) {
		ob_start();
		try {
			call_user_func( $callback );
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}
}
