<?php
/**
 * Plugin settings store.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Typed, schema-validated settings stored in a single option.
 */
final class Settings {

	const OPTION = 'kirki_woo_settings';

	/**
	 * Settings schema. Each entry: type, default, description and optional enum/min/max.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function schema() {
		return array(
			'preview_product_id'   => array(
				'type'        => 'integer',
				'default'     => 0,
				'minimum'     => 0,
				'description' => __( 'Product used to preview product elements in the Kirki editor and on template previews. 0 picks the newest published product.', 'kirki-woocommerce-bridge' ),
			),
			'load_styles'          => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Load the small base stylesheet for WooCommerce elements.', 'kirki-woocommerce-bridge' ),
			),
			'mini_cart_auto_open'  => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Open the mini cart drawer after a product is added with AJAX.', 'kirki-woocommerce-bridge' ),
			),
			'enable_shop_filters'  => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Apply Kirki filter element query arguments (price, rating, stock, sale, attributes, categories) to the main shop query.', 'kirki-woocommerce-bridge' ),
			),
			'card_image_size'      => array(
				'type'        => 'string',
				'default'     => 'woocommerce_thumbnail',
				'description' => __( 'Image size used by product cards.', 'kirki-woocommerce-bridge' ),
			),
			'template_debug'       => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Print an HTML comment naming the template rule used for each WooCommerce page (administrators only).', 'kirki-woocommerce-bridge' ),
			),
			'respect_kirki_native' => array(
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'When a native Kirki template already targets a product or product archive, let it win over rules from this plugin.', 'kirki-woocommerce-bridge' ),
			),
		);
	}

	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		$out = array();
		foreach ( self::schema() as $key => $def ) {
			$out[ $key ] = $def['default'];
		}
		return $out;
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return self::sanitize( array_merge( self::defaults(), $stored ) );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Merge and persist a partial update. Unknown keys are rejected.
	 *
	 * @param array<string,mixed> $changes Partial settings.
	 * @return array<string,mixed>|\WP_Error New settings.
	 */
	public static function update( array $changes ) {
		$schema  = self::schema();
		$unknown = array_diff( array_keys( $changes ), array_keys( $schema ) );
		if ( $unknown ) {
			return new \WP_Error(
				'kirki_woo_unknown_setting',
				/* translators: %s: setting keys. */
				sprintf( __( 'Unknown setting(s): %s', 'kirki-woocommerce-bridge' ), implode( ', ', $unknown ) ),
				array( 'status' => 400 )
			);
		}
		$next = self::sanitize( array_merge( self::all(), $changes ) );
		update_option( self::OPTION, $next, false );
		return $next;
	}

	/**
	 * Coerce values to the schema.
	 *
	 * @param array<string,mixed> $values Values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $values ) {
		$out = array();
		foreach ( self::schema() as $key => $def ) {
			$value = array_key_exists( $key, $values ) ? $values[ $key ] : $def['default'];
			switch ( $def['type'] ) {
				case 'integer':
					$value = max( isset( $def['minimum'] ) ? (int) $def['minimum'] : PHP_INT_MIN, (int) $value );
					break;
				case 'boolean':
					$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
					$value = null === $value ? (bool) $def['default'] : $value;
					break;
				default:
					$value = sanitize_key( (string) $value );
					if ( '' === $value ) {
						$value = $def['default'];
					}
			}
			$out[ $key ] = $value;
		}
		return $out;
	}
}
