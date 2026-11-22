<?php
/**
 * Integration points with the Kirki builder.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Connects the element registry to Kirki: frontend rendering through
 * `kirki_element_generator_{source}`, WooCommerce collections for Kirki's
 * native Collection element, and the editor configuration.
 */
final class Kirki_Bridge {

	/**
	 * External collection type id.
	 */
	const COLLECTION = 'KIRKI_WOO';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'kirki_element_generator_' . KIRKI_WOO_SOURCE, array( __CLASS__, 'generate' ), 10, 2 );
		add_filter( 'kirki_external_collection_options', array( __CLASS__, 'collection_options' ), 10, 2 );
		add_filter( 'kirki_external_collection_item_type', array( __CLASS__, 'collection_item_type' ), 10, 2 );
		add_filter( 'kirki_collection_' . self::COLLECTION, array( __CLASS__, 'collection' ), 10, 2 );
	}

	/**
	 * Render one of our elements inside Kirki output.
	 *
	 * @param mixed               $html  Previous value (false).
	 * @param array<string,mixed> $props Kirki props: element, attributes, options...
	 * @return mixed
	 */
	public static function generate( $html, $props ) {
		if ( ! is_array( $props ) || empty( $props['element']['name'] ) ) {
			return $html;
		}
		$element = $props['element'];
		if ( ! Elements::get( $element['name'] ) ) {
			return $html;
		}
		$settings   = isset( $element['properties']['settings'] ) && is_array( $element['properties']['settings'] ) ? $element['properties']['settings'] : array();
		$options    = isset( $props['options'] ) && is_array( $props['options'] ) ? $props['options'] : array();
		$attributes = isset( $props['attributes'] ) && is_string( $props['attributes'] ) ? $props['attributes'] : '';

		$out = Renderer::render( $element['name'], $settings, $options, $attributes );
		// Kirki treats an empty string as "not handled" and would fall back to a generic tag.
		return '' === $out ? '<!-- kirki-woo:' . esc_attr( Elements::slug( $element['name'] ) ) . ' -->' : $out;
	}

	/**
	 * Collections offered in Kirki's Collection element.
	 *
	 * @return array<string,string>
	 */
	public static function collections() {
		return array(
			self::COLLECTION . '-related'         => __( 'Related products (current product)', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-upsells'         => __( 'Upsells (current product)', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-cross_sells'     => __( 'Cross-sells (cart)', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-cart_items'      => __( 'Cart items', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-featured'        => __( 'Featured products', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-on_sale'         => __( 'On sale products', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-best_selling'    => __( 'Best selling products', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-top_rated'       => __( 'Top rated products', 'kirki-woocommerce-bridge' ),
			self::COLLECTION . '-recently_viewed' => __( 'Recently viewed products', 'kirki-woocommerce-bridge' ),
		);
	}

	/**
	 * `kirki_external_collection_options`.
	 *
	 * @param array<int,mixed>    $options Options.
	 * @param array<string,mixed> $args    Args.
	 * @return array<int,mixed>
	 */
	public static function collection_options( $options, $args = array() ) {
		$options = is_array( $options ) ? $options : array();
		$group   = array();
		foreach ( self::collections() as $value => $title ) {
			$group[] = array(
				'title'    => $title,
				'value'    => $value,
				'itemType' => 'post',
			);
		}
		$options[] = array(
			'title'   => __( 'WooCommerce', 'kirki-woocommerce-bridge' ),
			'options' => array(
				array(
					'title'               => __( 'WooCommerce', 'kirki-woocommerce-bridge' ),
					'value'               => self::COLLECTION,
					'inherit'             => false,
					'default_select_type' => self::COLLECTION . '-featured',
					'group'               => $group,
				),
			),
		);
		return $options;
	}

	/**
	 * `kirki_external_collection_item_type`.
	 *
	 * @param string $item_type Item type.
	 * @param string $name      Collection name.
	 * @return string
	 */
	public static function collection_item_type( $item_type, $name ) {
		return isset( self::collections()[ $name ] ) ? 'post' : $item_type;
	}

	/**
	 * Resolve a WooCommerce collection to posts.
	 *
	 * @param mixed               $value Previous.
	 * @param array<string,mixed> $args  Kirki args (name, item_per_page, current_page, context...).
	 * @return array{data:array<int,\WP_Post>,pagination:array<string,int>|null,itemType:string}
	 */
	public static function collection( $value, $args ) {
		$name  = isset( $args['name'] ) ? (string) $args['name'] : '';
		$limit = isset( $args['item_per_page'] ) ? (int) $args['item_per_page'] : 4;
		$limit = $limit > 0 ? min( $limit, 100 ) : 4;
		$ids   = self::collection_ids( $name, $limit, $args );
		$posts = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && 'publish' === $post->post_status ) {
				$posts[] = $post;
			}
		}
		return array(
			'data'       => $posts,
			'pagination' => null,
			'itemType'   => 'post',
		);
	}

	/**
	 * Product ids for a collection.
	 *
	 * @param string              $name  Collection name.
	 * @param int                 $limit Limit.
	 * @param array<string,mixed> $args  Args.
	 * @return int[]
	 */
	public static function collection_ids( $name, $limit, array $args = array() ) {
		$key     = substr( $name, strlen( self::COLLECTION ) + 1 );
		$product = null;
		if ( isset( $args['context']['id'] ) && $args['context']['id'] ) {
			$product = wc_get_product( (int) $args['context']['id'] );
		}
		if ( ! $product && isset( $args['post_parent'] ) && $args['post_parent'] ) {
			$product = wc_get_product( (int) $args['post_parent'] );
		}
		if ( ! $product ) {
			$product = Context::product( 0, array() );
		}
		$cart = function_exists( 'WC' ) && WC()->cart ? WC()->cart : null;

		switch ( $key ) {
			case 'related':
				return $product ? wc_get_related_products( $product->get_id(), $limit, $product->get_upsell_ids() ) : array();
			case 'upsells':
				return $product ? array_slice( array_map( 'absint', $product->get_upsell_ids() ), 0, $limit ) : array();
			case 'cross_sells':
				return $cart ? array_slice( $cart->get_cross_sells(), 0, $limit ) : array();
			case 'cart_items':
				if ( ! $cart ) {
					return array();
				}
				$ids = array();
				foreach ( $cart->get_cart() as $item ) {
					$ids[] = (int) $item['product_id'];
				}
				return array_slice( array_values( array_unique( $ids ) ), 0, $limit );
			case 'recently_viewed':
				$viewed = isset( $_COOKIE['woocommerce_recently_viewed'] ) ? wp_parse_id_list( (array) explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) ) ) : array();
				return array_slice( array_reverse( array_filter( $viewed ) ), 0, $limit );
			case 'featured':
			case 'on_sale':
			case 'best_selling':
			case 'top_rated':
				$result = Product_Query::run(
					array_merge(
						Elements::defaults( 'products' ),
						array(
							'source' => $key,
							'limit'  => $limit,
						)
					)
				);
				return $result['ids'];
		}
		return array();
	}

	/**
	 * Stylesheets injected into the Kirki canvas iframe. Kirki only copies
	 * theme assets into the canvas, so WooCommerce and plugin styles are
	 * added by the editor script.
	 *
	 * @return string[]
	 */
	public static function canvas_styles() {
		$styles = array();
		if ( function_exists( 'WC' ) ) {
			$base     = trailingslashit( WC()->plugin_url() ) . 'assets/css/';
			$styles[] = $base . 'woocommerce-layout.css';
			$styles[] = $base . 'woocommerce.css';
			if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
				$styles[] = $base . 'woocommerce-blocktheme.css';
			}
		}
		if ( Settings::get( 'load_styles' ) ) {
			$styles[] = add_query_arg( 'ver', KIRKI_WOO_VERSION, KIRKI_WOO_URL . 'assets/css/frontend.css' );
		}
		$styles[] = add_query_arg( 'ver', KIRKI_WOO_VERSION, KIRKI_WOO_URL . 'assets/css/canvas.css' );
		/**
		 * Filters stylesheets injected into the Kirki editor canvas.
		 *
		 * @since 1.0.0
		 * @param string[] $styles URLs.
		 */
		return array_values( array_map( 'esc_url_raw', (array) apply_filters( 'kirki_woo_canvas_styles', $styles ) ) );
	}

	/**
	 * Configuration handed to the editor script.
	 *
	 * @return array<string,mixed>
	 */
	public static function editor_config() {
		$elements = array();
		foreach ( Elements::all() as $def ) {
			$elements[] = Elements::describe( $def );
		}
		return array(
			'version'       => KIRKI_WOO_VERSION,
			'schemaVersion' => Elements::SCHEMA_VERSION,
			'source'        => KIRKI_WOO_SOURCE,
			'category'      => __( 'WooCommerce', 'kirki-woocommerce-bridge' ),
			'groups'        => Elements::groups(),
			'elements'      => $elements,
			'restUrl'       => esc_url_raw( rest_url( KIRKI_WOO_API_NAMESPACE ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'templatesUrl'  => esc_url_raw( admin_url( 'admin.php?page=kirki-woo' ) ),
			'canvasStyles'  => self::canvas_styles(),
			'canvasClasses' => array( 'woocommerce', 'woocommerce-page', 'kirki-woo-canvas' ),
			'i18n'          => array(
				'loading' => __( 'Loading WooCommerce preview…', 'kirki-woocommerce-bridge' ),
				'error'   => __( 'Preview unavailable', 'kirki-woocommerce-bridge' ),
			),
		);
	}
}
