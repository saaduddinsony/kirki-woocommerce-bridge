<?php
/**
 * Dependency detection.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Detects WooCommerce, Kirki (free builder), Kirki Pro and Kirki eCommerce.
 */
final class Dependencies {

	/**
	 * Returns which dependencies are present.
	 *
	 * @return array{woocommerce:bool,kirki:bool,kirki_pro:bool,kirki_ecommerce:bool,versions:array<string,string|null>}
	 */
	public static function check() {
		$woo     = class_exists( 'WooCommerce' ) || function_exists( 'WC' );
		$kirki   = defined( 'KIRKI_VERSION' ) && class_exists( '\Kirki\HelperFunctions' );
		$pro     = defined( 'KIRKI_PRO_VERSION' ) || class_exists( 'KirkiProMain' );
		$ecom    = defined( 'KIRKI_ECOMMERCE_VERSION' );
		$builder = $kirki && version_compare( (string) KIRKI_VERSION, '6.0.0', '>=' );

		return array(
			'woocommerce'     => $woo,
			'kirki'           => $builder,
			'kirki_pro'       => $pro,
			'kirki_ecommerce' => $ecom,
			'versions'        => array(
				'plugin'          => KIRKI_WOO_VERSION,
				'woocommerce'     => defined( 'WC_VERSION' ) ? WC_VERSION : null,
				'kirki'           => defined( 'KIRKI_VERSION' ) ? KIRKI_VERSION : null,
				'kirki_pro'       => defined( 'KIRKI_PRO_VERSION' ) ? KIRKI_PRO_VERSION : null,
				'kirki_ecommerce' => defined( 'KIRKI_ECOMMERCE_VERSION' ) ? KIRKI_ECOMMERCE_VERSION : null,
				'wordpress'       => get_bloginfo( 'version' ),
				'php'             => PHP_VERSION,
			),
		);
	}

	/**
	 * Admin notice when a dependency is missing.
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$deps    = self::check();
		$missing = array();
		if ( ! $deps['woocommerce'] ) {
			$missing[] = 'WooCommerce';
		}
		if ( ! $deps['kirki'] ) {
			$missing[] = 'Kirki 6.0+';
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma separated list of plugin names. */
					__( 'Kirki WooCommerce Bridge needs these plugins to be active: %s.', 'kirki-woocommerce-bridge' ),
					implode( ', ', $missing )
				)
			)
		);
	}
}
