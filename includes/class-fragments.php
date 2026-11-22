<?php
/**
 * Live cart fragments for the mini cart.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers WooCommerce cart fragments so the mini cart count and total update
 * after AJAX add to cart, cart updates and on cached pages.
 */
final class Fragments {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
	}

	/**
	 * Item count.
	 *
	 * @return int
	 */
	public static function cart_count() {
		return function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	}

	/**
	 * Count badge.
	 *
	 * @param int|null $count Count.
	 * @return string
	 */
	public static function count_html( $count = null ) {
		$count = null === $count ? self::cart_count() : (int) $count;
		return sprintf(
			'<span class="kwi-mini-cart__count" data-count="%1$d" aria-label="%2$s">%1$d</span>',
			$count,
			esc_attr(
				sprintf(
					/* translators: %d: number of items. */
					_n( '%d item in cart', '%d items in cart', $count, 'kirki-woocommerce-bridge' ),
					$count
				)
			)
		);
	}

	/**
	 * Total.
	 *
	 * @return string
	 */
	public static function total_html() {
		$total = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_subtotal() : wc_price( 0 );
		return '<span class="kwi-mini-cart__total">' . wp_kses_post( $total ) . '</span>';
	}

	/**
	 * Fragment filter.
	 *
	 * @param array<string,string> $fragments Fragments.
	 * @return array<string,string>
	 */
	public static function fragments( $fragments ) {
		$fragments                              = is_array( $fragments ) ? $fragments : array();
		$fragments['span.kwi-mini-cart__count'] = self::count_html();
		$fragments['span.kwi-mini-cart__total'] = self::total_html();
		return $fragments;
	}
}
