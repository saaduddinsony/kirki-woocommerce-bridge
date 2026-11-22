<?php
/**
 * Base test case: snapshots plugin options and removes created fixtures.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo\Tests;

use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Base class.
 */
abstract class Test_Case extends PolyfillTestCase {

	/**
	 * Post ids to delete.
	 *
	 * @var int[]
	 */
	protected $created = array();

	/**
	 * Option snapshot.
	 *
	 * @var array<string,mixed>
	 */
	private $options = array();

	/**
	 * Set up.
	 */
	protected function set_up() {
		parent::set_up();
		foreach ( array( 'kirki_woo_settings', 'kirki_woo_templates' ) as $opt ) {
			$this->options[ $opt ] = get_option( $opt, null );
		}
		\KirkiWoo\Elements::flush();
		wp_set_current_user( 1 );
	}

	/**
	 * Tear down.
	 */
	protected function tear_down() {
		foreach ( $this->options as $opt => $value ) {
			if ( null === $value ) {
				delete_option( $opt );
			} else {
				update_option( $opt, $value, false );
			}
		}
		foreach ( array_reverse( $this->created ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true );
			} else {
				wp_delete_post( $id, true );
			}
		}
		$this->created = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Create a simple product.
	 *
	 * @param array<string,mixed> $props Props.
	 * @return \WC_Product_Simple
	 */
	protected function product( array $props = array() ) {
		$p = new \WC_Product_Simple();
		$p->set_name( $props['name'] ?? 'Test product' );
		$p->set_status( 'publish' );
		$p->set_regular_price( (string) ( $props['regular'] ?? '50' ) );
		if ( isset( $props['sale'] ) ) {
			$p->set_sale_price( (string) $props['sale'] );
		}
		if ( isset( $props['sku'] ) ) {
			$p->set_sku( $props['sku'] );
		}
		if ( isset( $props['cats'] ) ) {
			$p->set_category_ids( $props['cats'] );
		}
		if ( isset( $props['featured'] ) ) {
			$p->set_featured( $props['featured'] );
		}
		$this->created[] = $p->save();
		return $p;
	}

	/**
	 * Create a Kirki design post.
	 *
	 * @return int
	 */
	protected function design() {
		$r = \KirkiWoo\Templates::create_design( 'single_product', 'PHPUnit design', true, false );
		$this->assertIsArray( $r );
		$this->created[] = (int) $r['design']['id'];
		return (int) $r['design']['id'];
	}

	/**
	 * Dispatch a REST request.
	 *
	 * @param string     $method Method.
	 * @param string     $route  Route under kirki-woo/v1.
	 * @param array|null $body   JSON body.
	 * @return \WP_REST_Response
	 */
	protected function rest( $method, $route, $body = null ) {
		$req = new \WP_REST_Request( $method, '/kirki-woo/v1' . $route );
		if ( null !== $body ) {
			$req->set_header( 'content-type', 'application/json' );
			$req->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $req );
	}
}
