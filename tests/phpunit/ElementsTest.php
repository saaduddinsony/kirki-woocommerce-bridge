<?php
/**
 * Element registry.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo\Tests;

use KirkiWoo\Elements;

/**
 * Elements tests.
 */
class ElementsTest extends Test_Case {

	public function test_registry_is_complete_and_well_formed() {
		$all = Elements::all();
		$this->assertGreaterThanOrEqual( 36, count( $all ) );
		$groups = array_keys( Elements::groups() );
		$names  = array();
		foreach ( $all as $slug => $def ) {
			$this->assertSame( Elements::PREFIX . $slug, $def['name'] );
			$this->assertContains( $def['group'], $groups, $slug );
			$this->assertNotEmpty( $def['title'] );
			foreach ( $def['settings'] as $key => $field ) {
				$this->assertContains( $field['type'], array( 'select', 'toggle', 'text', 'number', 'product' ), "$slug.$key" );
				$this->assertArrayHasKey( 'default', $field );
				if ( 'select' === $field['type'] ) {
					$this->assertArrayHasKey( (string) $field['default'], $field['options'], "$slug.$key default must be an option" );
				}
			}
			$names[] = $def['name'];
		}
		$this->assertSame( count( $names ), count( array_unique( $names ) ) );
		foreach ( array( 'products', 'product-add-to-cart', 'shop-filter', 'mini-cart', 'cart', 'checkout', 'my-account', 'store-notices', 'product-gallery' ) as $required ) {
			$this->assertNotNull( Elements::get( $required ), $required );
		}
	}

	public function test_get_accepts_name_or_slug() {
		$this->assertSame( Elements::get( 'product-title' ), Elements::get( 'kirki-woo-product-title' ) );
		$this->assertNull( Elements::get( 'nope' ) );
	}

	public function test_sanitize_coerces_and_clamps() {
		$s = Elements::sanitize_settings(
			'products',
			array(
				'columns' => '99',
				'limit'   => '-5',
				'source'  => 'evil',
				'paginate' => 'true',
				'unknown' => 'x',
			)
		);
		$this->assertSame( 6, $s['columns'] );
		$this->assertSame( 1, $s['limit'] );
		$this->assertSame( 'recent', $s['source'] );
		$this->assertTrue( $s['paginate'] );
		$this->assertArrayNotHasKey( 'unknown', $s );
	}

	public function test_validate_is_strict() {
		$this->assertTrue( Elements::validate_settings( 'products', array( 'columns' => 4 ) ) );
		$err = Elements::validate_settings( 'products', array( 'columns' => 9, 'bogus' => 1 ) );
		$this->assertWPError( $err );
		$this->assertStringContainsString( 'columns', $err->get_error_message() );
		$this->assertStringContainsString( 'bogus', $err->get_error_message() );
	}

	public function test_json_schema_matches_settings() {
		$schema = Elements::json_schema( 'shop-filter' );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertArrayHasKey( 'filter_type', $schema['properties'] );
		$this->assertContains( 'price', $schema['properties']['filter_type']['enum'] );
	}

	public function test_build_block_shape() {
		$block = Elements::build_block( 'product-price', array( 'product_id' => 0 ), 'body', 'kwitest1' );
		$this->assertSame( 'kwitest1', $block['id'] );
		$this->assertSame( 'kirki-woo-product-price', $block['name'] );
		$this->assertSame( KIRKI_WOO_SOURCE, $block['source'] );
		$this->assertSame( 'body', $block['parentId'] );
		$this->assertIsArray( $block['properties']['settings'] );
		$this->assertWPError( Elements::build_block( 'missing' ) );
	}

	private function assertWPError( $thing ) {
		$this->assertInstanceOf( \WP_Error::class, $thing );
	}
}
