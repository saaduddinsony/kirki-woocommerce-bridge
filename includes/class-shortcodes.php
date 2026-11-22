<?php
/**
 * Shortcodes: render any element anywhere (classic editor, widgets, other
 * builders) and a standalone mini cart.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode registration.
 */
final class Shortcodes {

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'kirki_woo_element', array( __CLASS__, 'element' ) );
		add_shortcode( 'kirki_woo_mini_cart', array( __CLASS__, 'mini_cart' ) );
	}

	/**
	 * [kirki_woo_element name="products" columns="3" limit="6"]
	 *
	 * Every other attribute is passed as an element setting and validated
	 * against the element schema (unknown keys are ignored).
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public static function element( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		$name = isset( $atts['name'] ) ? sanitize_key( $atts['name'] ) : '';
		unset( $atts['name'] );
		if ( '' === $name || ! Elements::get( $name ) ) {
			return '';
		}
		return Renderer::render( $name, self::settings_from_atts( $name, $atts ) );
	}

	/**
	 * [kirki_woo_mini_cart behavior="drawer|cart" show_total="yes"]
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public static function mini_cart( $atts ) {
		$atts = is_array( $atts ) ? $atts : array();
		return Renderer::render( 'mini-cart', self::settings_from_atts( 'mini-cart', $atts ) );
	}

	/**
	 * Keep only attributes that are settings of the element.
	 *
	 * @param string               $name Element.
	 * @param array<string,string> $atts Attributes.
	 * @return array<string,mixed>
	 */
	public static function settings_from_atts( $name, array $atts ) {
		$def = Elements::get( $name );
		if ( ! $def ) {
			return array();
		}
		$out = array();
		foreach ( $atts as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $def['settings'][ $key ] ) ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}
}
