<?php
/**
 * REST API: kirki-woo/v1.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers one REST route per operation in Operations::all().
 */
final class Rest_Controller {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register every operation.
	 *
	 * @return void
	 */
	public static function register() {
		$by_route = array();
		foreach ( Operations::all() as $op ) {
			$by_route[ $op['route'] ][] = $op;
		}
		foreach ( $by_route as $route => $ops ) {
			$endpoints = array();
			foreach ( $ops as $op ) {
				$endpoints[] = array(
					'methods'             => $op['method'],
					'callback'            => static function ( \WP_REST_Request $request ) use ( $op ) {
						return self::dispatch( $op, $request );
					},
					'permission_callback' => static function () use ( $op ) {
						return self::permission( $op );
					},
					'args'                => self::args( $op ),
				);
			}
			register_rest_route( KIRKI_WOO_API_NAMESPACE, $route, $endpoints );
		}
	}

	/**
	 * Minimal route set when a dependency is missing.
	 *
	 * @return void
	 */
	public static function register_status_only() {
		register_rest_route(
			KIRKI_WOO_API_NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return rest_ensure_response( Api::status() );
				},
				'permission_callback' => static function () {
					return current_user_can( Api::CAP_READ ) ? true : self::forbidden();
				},
			)
		);
	}

	/**
	 * Permission.
	 *
	 * @param array<string,mixed> $op Operation.
	 * @return true|\WP_Error
	 */
	public static function permission( array $op ) {
		return current_user_can( $op['cap'] ) ? true : self::forbidden();
	}

	/**
	 * Forbidden error.
	 *
	 * @return \WP_Error
	 */
	private static function forbidden() {
		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to do that.', 'kirki-woocommerce-bridge' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * REST args from the input schema.
	 *
	 * @param array<string,mixed> $op Operation.
	 * @return array<string,array<string,mixed>>
	 */
	private static function args( array $op ) {
		$args = array();
		foreach ( $op['input'] as $key => $schema ) {
			$arg = $schema;
			if ( in_array( $key, $op['required'], true ) ) {
				$arg['required'] = true;
			}
			$args[ $key ] = $arg;
		}
		return $args;
	}

	/**
	 * Run an operation for a request.
	 *
	 * @param array<string,mixed> $op      Operation.
	 * @param \WP_REST_Request    $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function dispatch( array $op, \WP_REST_Request $request ) {
		$input = array();
		foreach ( array_keys( $op['input'] ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$input[ $key ] = $request->get_param( $key );
			}
		}
		$result = Operations::run( $op, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$status = 200;
		if ( 'POST' === $op['method'] && in_array( $op['callback'], array( 'create_template', 'create_design' ), true ) ) {
			$status = 201;
		}
		return new \WP_REST_Response( $result, $status );
	}
}
