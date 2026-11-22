<?php
/**
 * Rendering, dynamic data, settings.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo\Tests;

use KirkiWoo\Context;
use KirkiWoo\Dynamic_Data;
use KirkiWoo\Renderer;
use KirkiWoo\Settings;

/**
 * Render / data tests.
 */
class RenderAndDataTest extends Test_Case {

	public function test_product_elements_render_for_product() {
		$p = $this->product( array( 'name' => 'Render Me', 'regular' => '40', 'sale' => '30', 'sku' => 'KWI-UNIT-1' ) );
		Context::push( $p );
		try {
			$title = Renderer::render( 'product-title', array( 'html_tag' => 'h2' ) );
			$price = Renderer::render( 'product-price' );
			$badge = Renderer::render( 'product-sale-badge', array( 'style' => 'percentage' ) );
			$atc   = Renderer::render( 'product-add-to-cart' );
		} finally {
			Context::pop();
		}
		$this->assertStringContainsString( '<h2', $title );
		$this->assertStringContainsString( 'Render Me', $title );
		$this->assertStringContainsString( 'data-kwi-element="product-title"', $title );
		$this->assertStringContainsString( '30.00', $price );
		$this->assertStringContainsString( 'onsale', $badge );
		$this->assertStringContainsString( 'add-to-cart', $atc );
	}

	public function test_title_is_escaped() {
		$p = $this->product( array( 'name' => 'Bad <script>alert(1)</script>' ) );
		Context::push( $p );
		try {
			$html = Renderer::render( 'product-title' );
		} finally {
			Context::pop();
		}
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_products_grid_ids_source() {
		$a    = $this->product( array( 'name' => 'Grid A' ) );
		$b    = $this->product( array( 'name' => 'Grid B' ) );
		$html = Renderer::render( 'products', array( 'source' => 'ids', 'ids' => $a->get_id() . ',' . $b->get_id(), 'columns' => 2 ) );
		$this->assertSame( 2, substr_count( $html, 'kwi-product-card ' ) );
		$this->assertStringContainsString( '--kwi-cols:2', $html );
	}

	public function test_dynamic_fields() {
		$p = $this->product( array( 'regular' => '80', 'sale' => '60', 'sku' => 'KWI-UNIT-2' ) );
		$this->assertSame( 'KWI-UNIT-2', Dynamic_Data::product_value( 'sku', $p ) );
		$this->assertSame( '25', (string) Dynamic_Data::product_value( 'discount_percentage', $p ) );
		$this->assertStringContainsString( '60.00', Dynamic_Data::product_value( 'price_html', $p ) );
		$this->assertStringContainsString( 'KWI-UNIT-2', do_shortcode( '[kirki_woo field="sku" product_id="' . $p->get_id() . '"]' ) );
		$this->assertSame( '0', Dynamic_Data::store_value( 'cart_count' ) );
		$filtered = apply_filters(
			'kirki_dynamic_content',
			false,
			array(
				'dynamicContent' => array( 'type' => Dynamic_Data::TYPE_PRODUCT, 'value' => 'sku' ),
				'collectionItem' => array( 'ID' => $p->get_id() ),
			)
		);
		$this->assertSame( 'KWI-UNIT-2', $filtered );
	}

	public function test_settings_roundtrip_and_rejection() {
		$this->assertInstanceOf( \WP_Error::class, Settings::update( array( 'nope' => 1 ) ) );
		$out = Settings::update( array( 'preview_product_id' => '12', 'load_styles' => false ) );
		$this->assertSame( 12, $out['preview_product_id'] );
		$this->assertFalse( $out['load_styles'] );
		$this->assertSame( 12, Settings::get( 'preview_product_id' ) );
	}
}
