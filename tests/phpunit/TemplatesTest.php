<?php
/**
 * Template rules.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo\Tests;

use KirkiWoo\Templates;

/**
 * Templates tests.
 */
class TemplatesTest extends Test_Case {

	private function rule( array $over = array() ) {
		return array_merge(
			array(
				'id'         => 'r1',
				'context'    => 'single_product',
				'design_id'  => 1,
				'conditions' => array(),
				'priority'   => 10,
				'enabled'    => true,
			),
			$over
		);
	}

	public function test_rule_matches_pure_logic() {
		$facts = array(
			'context'      => 'single_product',
			'product_id'   => 5,
			'product_cat'  => array( 3, 7 ),
			'product_type' => 'simple',
			'stock_status' => 'instock',
			'on_sale'      => true,
			'featured'     => false,
			'logged_in'    => false,
		);
		$this->assertTrue( Templates::rule_matches( $this->rule(), $facts ) );
		$this->assertFalse( Templates::rule_matches( $this->rule( array( 'enabled' => false ) ), $facts ) );
		$this->assertFalse( Templates::rule_matches( $this->rule( array( 'context' => 'shop' ) ), $facts ) );
		$this->assertTrue( Templates::rule_matches( $this->rule( array( 'conditions' => array( 'product_cat' => array( 7 ) ) ) ), $facts ) );
		$this->assertFalse( Templates::rule_matches( $this->rule( array( 'conditions' => array( 'product_cat' => array( 9 ) ) ) ), $facts ) );
		$this->assertFalse( Templates::rule_matches( $this->rule( array( 'conditions' => array( 'exclude_product_ids' => array( 5 ) ) ) ), $facts ) );
		$this->assertTrue( Templates::rule_matches( $this->rule( array( 'conditions' => array( 'on_sale' => true ) ) ), $facts ) );
		$this->assertFalse( Templates::rule_matches( $this->rule( array( 'conditions' => array( 'featured' => true ) ) ), $facts ) );
		$this->assertFalse( Templates::rule_matches( $this->rule( array( 'conditions' => array( 'user_state' => 'logged_in' ) ) ), $facts ) );
		$this->assertTrue( Templates::rule_matches( $this->rule( array( 'conditions' => array( 'product_type' => array( 'simple', 'variable' ) ) ) ), $facts ) );
	}

	public function test_priority_then_specificity() {
		$facts   = array( 'context' => 'single_product', 'product_id' => 5, 'product_cat' => array( 3 ) );
		$generic = $this->rule( array( 'id' => 'generic' ) );
		$cat     = $this->rule( array( 'id' => 'cat', 'conditions' => array( 'product_cat' => array( 3 ) ) ) );
		$high    = $this->rule( array( 'id' => 'high', 'priority' => 50 ) );
		$this->assertSame( 'cat', Templates::match( $facts, array( $generic, $cat ) )['id'] );
		$this->assertSame( 'high', Templates::match( $facts, array( $generic, $cat, $high ) )['id'] );
	}

	public function test_validation_rejects_bad_input() {
		$design = $this->design();
		$this->assertInstanceOf( \WP_Error::class, Templates::validate( array( 'context' => 'nowhere', 'design_id' => $design ) ) );
		$this->assertInstanceOf( \WP_Error::class, Templates::validate( array( 'context' => 'shop' ) ) );
		$this->assertInstanceOf( \WP_Error::class, Templates::validate( array( 'context' => 'shop', 'design_id' => $design, 'conditions' => array( 'product_ids' => array( 1 ) ) ) ) );
		$this->assertInstanceOf( \WP_Error::class, Templates::validate( array( 'context' => 'single_product', 'design_id' => $design, 'conditions' => array( 'stock_status' => array( 'sold' ) ) ) ) );
		$this->assertInstanceOf( \WP_Error::class, Templates::validate( array( 'context' => 'shop', 'design_id' => 999999 ) ) );
		$ok = Templates::validate( array( 'context' => 'single_product', 'design_id' => $design, 'conditions' => array( 'product_ids' => array( '4', 'x', 4 ) ), 'priority' => 5000 ) );
		$this->assertIsArray( $ok );
		$this->assertSame( array( 4 ), $ok['conditions']['product_ids'] );
		$this->assertSame( 1000, $ok['priority'] );
	}

	public function test_crud_and_design_cleanup() {
		$design = $this->design();
		$rule   = Templates::create( array( 'context' => 'single_product', 'design_id' => $design, 'title' => 'T' ) );
		$this->assertIsArray( $rule );
		$this->assertMatchesRegularExpression( '/^r[a-z0-9]{10}$/', $rule['id'] );
		$this->assertSame( $rule, Templates::get( $rule['id'] ) );
		$updated = Templates::update( $rule['id'], array( 'priority' => 20 ) );
		$this->assertSame( 20, $updated['priority'] );
		$this->assertTrue( Templates::delete( $rule['id'] ) );
		$this->assertNull( Templates::get( $rule['id'] ) );
		$this->assertFalse( Templates::delete( $rule['id'] ) );
	}

	public function test_create_design_builds_kirki_data() {
		$r = Templates::create_design( 'shop', 'Shop test', true, true );
		$this->assertIsArray( $r );
		$id              = (int) $r['design']['id'];
		$this->created[] = $id;
		$this->assertSame( 'kirki_template', get_post_type( $id ) );
		$this->assertSame( 'kirki', get_post_meta( $id, 'kirki_editor_mode', true ) );
		$data  = get_post_meta( $id, 'kirki', true );
		$names = wp_list_pluck( $data['blocks'], 'name' );
		$this->assertContains( 'kirki-woo-products', $names );
		$this->assertContains( 'kirki-woo-shop-filter', $names );
		$conditions = get_post_meta( $id, 'kirki_template_conditions', true );
		$this->assertSame( 'hide', $conditions[0]['visibility'], 'Kirki must not route the design by itself' );
		$this->assertSame( 'shop', $r['rule']['context'] );
		$html = apply_filters( 'kirki_html_generator', '', $id );
		$this->assertStringContainsString( 'data-kwi-element="products"', $html );
		Templates::delete( $r['rule']['id'] );
	}
}
