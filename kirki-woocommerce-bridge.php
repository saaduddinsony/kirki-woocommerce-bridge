<?php
/**
 * Plugin Name:       Kirki WooCommerce Bridge
 * Plugin URI:        https://github.com/saaduddinsony/kirki-woocommerce-bridge
 * Description:       Design WooCommerce stores natively in the Kirki visual builder: product, shop, cart, checkout and account elements, product dynamic data, and WooCommerce template conditions.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce, kirki
 * Author:            S M Saad Uddin
 * Author URI:        https://github.com/saaduddinsony
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kirki-woocommerce-bridge
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 *
 * @package KirkiWoo
 */

/*
 * Copyright (C) 2026 S M Saad Uddin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

define( 'KIRKI_WOO_VERSION', '1.0.0' );
define( 'KIRKI_WOO_FILE', __FILE__ );
define( 'KIRKI_WOO_PATH', plugin_dir_path( __FILE__ ) );
define( 'KIRKI_WOO_URL', plugin_dir_url( __FILE__ ) );
define( 'KIRKI_WOO_SOURCE', 'kirki-woo' );
define( 'KIRKI_WOO_API_NAMESPACE', 'kirki-woo/v1' );

require_once KIRKI_WOO_PATH . 'includes/autoload.php';

/*
 * Declare compatibility with WooCommerce High-Performance Order Storage and the
 * Cart and Checkout blocks. The plugin never reads orders from post tables and
 * renders both the classic shortcodes and the blocks.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', KIRKI_WOO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', KIRKI_WOO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', KIRKI_WOO_FILE, true );
		}
	}
);

register_activation_hook( __FILE__, array( '\KirkiWoo\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( '\KirkiWoo\Plugin', 'instance' ), 20 );
