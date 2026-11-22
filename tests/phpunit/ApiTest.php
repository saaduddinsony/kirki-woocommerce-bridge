<?php
/**
 * REST API and Abilities API.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo\Tests;

use KirkiWoo\Operations;

/**
 * API tests.
 */
class ApiTest extends Test_Case {

	public function test_every_operation_has_a_route() {
		$routes = rest_get_server()->get_routes( 'kirki-woo/v1' );
		foreach ( Operations::all() as $op ) {
			$this->assertArrayHasKey( '/kirki-woo/v1' . $op['route'], $routes, $op['ability'] );
		}
	}

	public function test_permissions() {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', '/elements' )->get_status() );
		$this->assertSame( 401, $this->rest( 'GET', '/templates' )->get_status() );

		$uid = wp_insert_user( array( 'user_login' => 'kwi_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
		wp_set_current_user( $uid );
		$this->assertSame( 403, $this->rest( 'GET', '/elements' )->get_status() );

		$editor = wp_insert_user( array( 'user_login' => 'kwi_ed_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$this->assertSame( 200, $this->rest( 'GET', '/elements' )->get_status() );
		$this->assertSame( 403, $this->rest( 'GET', '/templates' )->get_status(), 'templates need manage_woocommerce' );
		$this->assertSame( 403, $this->rest( 'PUT', '/settings', array( 'settings' => array( 'load_styles' => true ) ) )->get_status() );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
		wp_delete_user( $editor );
	}

	public function test_render_and_validation_errors() {
		$p   = $this->product( array( 'name' => 'Via REST' ) );
		$res = $this->rest( 'POST', '/render', array( 'name' => 'product-title', 'product_id' => $p->get_id() ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertStringContainsString( 'Via REST', $res->get_data()['html'] );
		$this->assertSame( 404, $this->rest( 'POST', '/render', array( 'name' => 'nope' ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'POST', '/render', array( 'name' => 'products', 'strict' => true, 'settings' => array( 'columns' => 50 ) ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'POST', '/blocks', array( 'name' => 'products', 'settings' => array( 'x' => 1 ) ) )->get_status() );
	}

	public function test_template_lifecycle_over_rest() {
		$res = $this->rest( 'POST', '/designs', array( 'context' => 'cart', 'title' => 'REST cart', 'create_rule' => true ) );
		$this->assertSame( 201, $res->get_status() );
		$data            = $res->get_data();
		$this->created[] = (int) $data['design']['id'];
		$id              = $data['rule']['id'];

		$this->assertSame( 200, $this->rest( 'GET', '/templates/' . $id )->get_status() );
		$upd = $this->rest( 'PUT', '/templates/' . $id, array( 'priority' => 77, 'conditions' => array( 'user_state' => 'logged_in' ) ) );
		$this->assertSame( 77, $upd->get_data()['priority'] );
		$resolved = $this->rest( 'POST', '/templates/resolve', array( 'context' => 'cart', 'logged_in' => true ) )->get_data();
		$this->assertSame( $id, $resolved['rule']['id'] );
		$this->assertSame( 400, $this->rest( 'PUT', '/templates/' . $id, array( 'conditions' => array( 'product_ids' => array( 1 ) ) ) )->get_status() );
		$this->assertSame( 200, $this->rest( 'DELETE', '/templates/' . $id )->get_status() );
		$this->assertSame( 404, $this->rest( 'GET', '/templates/' . $id )->get_status() );
	}

	public function test_delete_design_removes_its_rules() {
		$res  = $this->rest( 'POST', '/designs', array( 'context' => 'checkout', 'title' => 'REST delete me' ) );
		$data = $res->get_data();
		$id   = (int) $data['design']['id'];
		$rule = $data['rule']['id'];

		$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Not a design', 'post_status' => 'draft' ) );
		$this->assertSame( 404, $this->rest( 'DELETE', '/designs/' . $page )->get_status(), 'pages are never deleted' );
		wp_delete_post( $page, true );

		$del = $this->rest( 'DELETE', '/designs/' . $id );
		$this->assertSame( 200, $del->get_status() );
		$this->assertSame( array( $rule ), $del->get_data()['removed_rules'] );
		$this->assertSame( 'trash', get_post_status( $id ) );
		$this->assertSame( 404, $this->rest( 'GET', '/templates/' . $rule )->get_status() );
		$this->assertSame( 200, $this->rest( 'DELETE', '/designs/' . $id, array( 'force' => true ) )->get_status() );
		$this->assertNull( get_post( $id ) );
	}

	public function test_add_elements_to_a_design() {
		$id  = $this->design();
		$p   = $this->product( array( 'name' => 'Inserted Tee' ) );
		$res = $this->rest(
			'POST',
			'/designs/' . $id . '/elements',
			array(
				'elements' => array(
					array( 'name' => 'product-title', 'settings' => array( 'html_tag' => 'h2', 'product_id' => $p->get_id() ) ),
					array( 'name' => 'kirki-woo-product-price' ),
				),
				'position' => 0,
			)
		);
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$added = $res->get_data()['added'];
		$this->assertCount( 2, $added );

		$design = $this->rest( 'GET', '/designs/' . $id )->get_data();
		$body   = wp_list_filter( $design['blocks'], array( 'id' => 'body' ) );
		$body   = reset( $body );
		$this->assertSame( $added, array_slice( $body['children'], 0, 2 ), 'inserted at the requested position' );
		$title = wp_list_filter( $design['blocks'], array( 'id' => $added[0] ) );
		$title = reset( $title );
		$this->assertSame( 'kirki-woo-product-title', $title['name'] );
		$this->assertSame( 'h2', $title['settings']['html_tag'] );

		$this->assertSame( 400, $this->rest( 'POST', '/designs/' . $id . '/elements', array( 'elements' => array( array( 'name' => 'products', 'settings' => array( 'columns' => 99 ) ) ) ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'POST', '/designs/' . $id . '/elements', array( 'elements' => array( array( 'name' => 'product-sku' ) ), 'parent_id' => 'nope' ) )->get_status() );
		$this->assertSame( 404, $this->rest( 'POST', '/designs/' . $id . '/elements', array( 'elements' => array( array( 'name' => 'nope' ) ) ) )->get_status() );
		$this->assertSame( 404, $this->rest( 'GET', '/designs/999999' )->get_status() );

		// Rendered on the storefront through Kirki.
		if ( class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			$this->assertNotFalse( strpos( wp_json_encode( get_post_meta( $id, 'kirki', true ) ), 'kirki-woo-product-title' ) );
		}
	}

	public function test_abilities_mirror_operations() {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not available.' );
		}
		foreach ( Operations::all() as $op ) {
			$ability = wp_get_ability( 'kirki-woo/' . $op['ability'] );
			$this->assertNotNull( $ability, $op['ability'] );
			$meta = $ability->get_meta();
			$this->assertSame( $op['readonly'], $meta['annotations']['readonly'] );
			$this->assertSame( $op['destructive'], $meta['annotations']['destructive'] );
		}
		$status = wp_get_ability( 'kirki-woo/get-status' )->execute();
		$this->assertTrue( $status['ready'] );
		$el = wp_get_ability( 'kirki-woo/get-element' )->execute( array( 'name' => 'products' ) );
		$this->assertSame( 'kirki-woo-products', $el['name'] );
		wp_set_current_user( 0 );
		$denied = wp_get_ability( 'kirki-woo/list-templates' )->execute();
		$this->assertInstanceOf( \WP_Error::class, $denied );
	}
}
