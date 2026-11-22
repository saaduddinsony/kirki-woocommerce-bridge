<?php
/**
 * Coexistence with Kirki eCommerce, shortcodes and namespacing.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo\Tests;

use KirkiWoo\Compat;
use KirkiWoo\Elements;

/**
 * Compat tests. Run the suite with and without Kirki eCommerce active.
 */
class CompatTest extends Test_Case {

	public function test_everything_is_namespaced() {
		foreach ( Elements::all() as $def ) {
			$this->assertStringStartsWith( 'kirki-woo-', $def['name'] );
		}
		$ours = array_filter( array_keys( rest_get_server()->get_routes() ), static fn( $r ) => false !== strpos( $r, 'kirki-woo' ) );
		foreach ( $ours as $route ) {
			$this->assertStringStartsWith( '/kirki-woo/v1', $route );
		}
		foreach ( wp_get_abilities() as $ability ) {
			if ( false !== strpos( $ability->get_name(), 'kirki-woo' ) ) {
				$this->assertStringStartsWith( 'kirki-woo/', $ability->get_name() );
			}
		}
		global $shortcode_tags;
		$tags = array_keys( $shortcode_tags );
		foreach ( array( 'kirki_woo', 'kirki_woo_element', 'kirki_woo_mini_cart' ) as $tag ) {
			$this->assertContains( $tag, $tags );
		}
	}

	public function test_kirki_ecommerce_shortcodes_are_never_hijacked() {
		global $shortcode_tags;
		if ( Compat::kirki_ecommerce_active() ) {
			$this->assertSame( array(), Compat::report()['kecom_shortcode_aliases'] );
			$this->assertNotSame( array( Compat::class, 'kecom_mini_cart' ), $shortcode_tags['kecom_mini_cart'] ?? null, 'Kirki eCommerce keeps its own shortcode' );
			$this->assertSame( array(), Compat::page_conflicts() );
		} else {
			$this->assertSame( array( Compat::class, 'kecom_mini_cart' ), $shortcode_tags['kecom_mini_cart'] );
			$this->assertStringContainsString( 'kecom-mini-cart', do_shortcode( '[kecom_mini_cart class="x"]' ) );
		}
	}

	public function test_element_shortcode() {
		$p    = $this->product( array( 'name' => 'Shortcode Tee', 'regular_price' => '31' ) );
		$html = do_shortcode( '[kirki_woo_element name="product-title" product_id="' . $p->get_id() . '" html_tag="h3" bogus="1"]' );
		$this->assertStringContainsString( '<h3', $html );
		$this->assertStringContainsString( 'Shortcode Tee', $html );
		$this->assertSame( '', do_shortcode( '[kirki_woo_element name="nope"]' ) );
		$this->assertStringContainsString( 'data-kwi-element="mini-cart"', do_shortcode( '[kirki_woo_mini_cart behavior="cart"]' ) );
	}

	public function test_status_reports_compat() {
		$status = \KirkiWoo\Api::status();
		$this->assertSame( Compat::kirki_ecommerce_active(), $status['compat']['kirki_ecommerce_active'] );
		$this->assertSame( Compat::kirki_ecommerce_active(), $status['dependencies']['kirki_ecommerce'] );
	}
}
