<?php
/**
 * WordPress Abilities API registrations (WordPress 6.9+).
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers one ability per operation: kirki-woo/<operation>.
 */
final class Abilities {

	/**
	 * Ability category slug.
	 */
	const CATEGORY = 'kirki-woo';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::CATEGORY,
				array(
					'label'       => __( 'Kirki WooCommerce', 'kirki-woocommerce-bridge' ),
					'description' => __( 'WooCommerce elements, dynamic data, template rules and settings for the Kirki builder.', 'kirki-woocommerce-bridge' ),
				)
			);
		}
	}

	/**
	 * Abilities.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( Operations::all() as $op ) {
			$args = array(
				'label'               => $op['label'],
				'description'         => $op['description'],
				'category'            => self::CATEGORY,
				'execute_callback'    => static function ( $input = null ) use ( $op ) {
					return Operations::run( $op, is_array( $input ) ? $input : array() );
				},
				'permission_callback' => static function () use ( $op ) {
					return current_user_can( $op['cap'] );
				},
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => (bool) $op['readonly'],
						'destructive' => (bool) $op['destructive'],
						'idempotent'  => (bool) $op['idempotent'],
					),
					'show_in_rest' => true,
					'rest'         => array(
						'method' => $op['method'],
						'route'  => '/' . KIRKI_WOO_API_NAMESPACE . $op['route'],
					),
				),
			);
			if ( ! empty( $op['input'] ) ) {
				$args['input_schema'] = Operations::input_schema( $op );
			}
			wp_register_ability( self::CATEGORY . '/' . $op['ability'], $args );
		}
	}
}
