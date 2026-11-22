<?php
/**
 * Transport-neutral operations shared by the REST API and the Abilities API.
 *
 * Every public method takes a plain array of input and returns a plain array
 * (or WP_Error), so REST routes and abilities are thin wrappers and MCP tools
 * can map onto either one.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Operations.
 */
final class Api {

	/**
	 * Capability for reading schemas and previews.
	 */
	const CAP_READ = 'edit_posts';

	/**
	 * Capability for changing settings and template rules.
	 */
	const CAP_MANAGE = 'manage_woocommerce';

	/**
	 * Status and versions.
	 *
	 * @return array<string,mixed>
	 */
	public static function status() {
		$deps  = Dependencies::check();
		$ready = $deps['woocommerce'] && $deps['kirki'];
		$out   = array(
			'plugin'         => 'kirki-woocommerce-bridge',
			'version'        => KIRKI_WOO_VERSION,
			'schema_version' => Elements::SCHEMA_VERSION,
			'api_namespace'  => KIRKI_WOO_API_NAMESPACE,
			'element_source' => KIRKI_WOO_SOURCE,
			'element_prefix' => Elements::PREFIX,
			'ready'          => $ready,
			'dependencies'   => array(
				'woocommerce'     => $deps['woocommerce'],
				'kirki'           => $deps['kirki'],
				'kirki_pro'       => $deps['kirki_pro'],
				'kirki_ecommerce' => $deps['kirki_ecommerce'],
			),
			'versions'       => $deps['versions'],
			'features'       => array(
				'hpos'                 => false,
				'cart_checkout_blocks' => true,
				'abilities_api'        => function_exists( 'wp_register_ability' ),
			),
			'counts'         => null,
			'compat'         => Compat::report(),
		);
		if ( $ready ) {
			$out['features']['hpos'] = self::hpos_enabled();
			$out['counts']           = array(
				'elements'       => count( Elements::all() ),
				'product_fields' => count( Dynamic_Data::product_fields() ),
				'store_fields'   => count( Dynamic_Data::store_fields() ),
				'template_rules' => count( Templates::all() ),
			);
		}
		return $out;
	}

	/**
	 * Whether HPOS is active.
	 *
	 * @return bool
	 */
	private static function hpos_enabled() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
			return (bool) \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}

	/**
	 * List elements.
	 *
	 * @param array<string,mixed> $input Optional group filter.
	 * @return array<string,mixed>
	 */
	public static function list_elements( array $input = array() ) {
		$group = isset( $input['group'] ) ? sanitize_key( $input['group'] ) : '';
		$out   = array();
		foreach ( Elements::all() as $def ) {
			if ( '' !== $group && $def['group'] !== $group ) {
				continue;
			}
			$out[] = Elements::describe( $def );
		}
		return array(
			'schema_version' => Elements::SCHEMA_VERSION,
			'groups'         => Elements::groups(),
			'elements'       => $out,
		);
	}

	/**
	 * One element.
	 *
	 * @param array<string,mixed> $input name.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_element( array $input ) {
		$def = Elements::get( isset( $input['name'] ) ? (string) $input['name'] : '' );
		if ( ! $def ) {
			return new \WP_Error( 'kirki_woo_unknown_element', __( 'Unknown element.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		return Elements::describe( $def );
	}

	/**
	 * Render an element to HTML.
	 *
	 * @param array<string,mixed> $input name, settings, product_id.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function render( array $input ) {
		$name     = isset( $input['name'] ) ? (string) $input['name'] : '';
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$def      = Elements::get( $name );
		if ( ! $def ) {
			return new \WP_Error( 'kirki_woo_unknown_element', __( 'Unknown element.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		$strict = ! empty( $input['strict'] );
		if ( $strict ) {
			$valid = Elements::validate_settings( $name, $settings );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}
		self::ensure_cart();

		$product_id = isset( $input['product_id'] ) ? absint( $input['product_id'] ) : 0;
		$product    = null;
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! Context::visible( $product ) ) {
				return new \WP_Error( 'kirki_woo_invalid_product', __( 'Product not found.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
			}
		}
		if ( $product ) {
			Context::push( $product );
		}
		try {
			$html = Renderer::render( $def['slug'], $settings );
		} finally {
			if ( $product ) {
				Context::pop();
			}
		}
		$preview = Context::product( 0, array(), true );
		return array(
			'name'       => $def['name'],
			'settings'   => Elements::sanitize_settings( $def['slug'], $settings ),
			'html'       => $html,
			'product_id' => $product ? $product->get_id() : ( $preview ? $preview->get_id() : 0 ),
			'assets'     => Assets::needed(),
		);
	}

	/**
	 * Load the session cart for REST previews.
	 *
	 * @return void
	 */
	private static function ensure_cart() {
		if ( function_exists( 'wc_load_cart' ) && function_exists( 'WC' ) && null === WC()->cart ) {
			wc_load_cart();
		}
	}

	/**
	 * Build a Kirki block for an element.
	 *
	 * @param array<string,mixed> $input name, settings, parent_id, id.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function build_block( array $input ) {
		$name     = isset( $input['name'] ) ? (string) $input['name'] : '';
		$settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$parent   = isset( $input['parent_id'] ) ? sanitize_text_field( (string) $input['parent_id'] ) : 'body';
		$id       = isset( $input['id'] ) ? sanitize_key( (string) $input['id'] ) : null;
		$block    = Elements::build_block( $name, $settings, $parent, $id );
		if ( is_wp_error( $block ) ) {
			return $block;
		}
		return array( 'block' => $block );
	}

	/**
	 * Dynamic fields.
	 *
	 * @return array<string,mixed>
	 */
	public static function dynamic_fields() {
		$fmt = static function ( array $fields ) {
			$out = array();
			foreach ( $fields as $key => $def ) {
				$out[] = array_merge( array( 'key' => $key ), $def );
			}
			return $out;
		};
		return array(
			'kirki_types' => array(
				'product' => Dynamic_Data::TYPE_PRODUCT,
				'store'   => Dynamic_Data::TYPE_STORE,
			),
			'product'     => $fmt( Dynamic_Data::product_fields() ),
			'store'       => $fmt( Dynamic_Data::store_fields() ),
			'shortcode'   => '[kirki_woo field="price_html" product_id="0" meta=""]',
			'kirki_usage' => array(
				'dynamicContent' => array(
					'type'  => Dynamic_Data::TYPE_PRODUCT,
					'value' => 'price_html',
					'meta'  => '',
				),
			),
		);
	}

	/**
	 * Resolve a dynamic field.
	 *
	 * @param array<string,mixed> $input field, product_id, meta, scope.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function resolve_dynamic( array $input ) {
		$field = isset( $input['field'] ) ? sanitize_key( $input['field'] ) : '';
		$scope = isset( $input['scope'] ) && 'store' === $input['scope'] ? 'store' : 'product';
		$meta  = isset( $input['meta'] ) ? sanitize_text_field( (string) $input['meta'] ) : '';
		self::ensure_cart();
		if ( 'store' === $scope ) {
			if ( ! isset( Dynamic_Data::store_fields()[ $field ] ) ) {
				return new \WP_Error( 'kirki_woo_unknown_field', __( 'Unknown store field.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
			}
			return array(
				'scope' => 'store',
				'field' => $field,
				'value' => Dynamic_Data::store_value( $field ),
			);
		}
		if ( ! isset( Dynamic_Data::product_fields()[ $field ] ) ) {
			return new \WP_Error( 'kirki_woo_unknown_field', __( 'Unknown product field.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		$product = Context::product( isset( $input['product_id'] ) ? absint( $input['product_id'] ) : 0, array(), true );
		if ( ! $product ) {
			return new \WP_Error( 'kirki_woo_invalid_product', __( 'Product not found.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		return array(
			'scope'      => 'product',
			'field'      => $field,
			'product_id' => $product->get_id(),
			'value'      => Dynamic_Data::product_value( $field, $product, $meta ),
		);
	}

	/**
	 * Settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		return array(
			'settings' => Settings::all(),
			'schema'   => Settings::schema(),
		);
	}

	/**
	 * Update settings.
	 *
	 * @param array<string,mixed> $input settings.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function update_settings( array $input ) {
		$changes = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$result  = Settings::update( $changes );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'settings' => $result );
	}

	/**
	 * Template contexts and condition schema.
	 *
	 * @return array<string,mixed>
	 */
	public static function template_contexts() {
		return array(
			'contexts'   => Templates::contexts(),
			'conditions' => Templates::condition_schema(),
		);
	}

	/**
	 * List rules.
	 *
	 * @param array<string,mixed> $input Optional context filter.
	 * @return array<string,mixed>
	 */
	public static function list_templates( array $input = array() ) {
		$context = isset( $input['context'] ) ? sanitize_key( $input['context'] ) : '';
		$rules   = Templates::all();
		if ( '' !== $context ) {
			$rules = array_values( array_filter( $rules, static fn( $r ) => $r['context'] === $context ) );
		}
		return array( 'templates' => array_map( array( __CLASS__, 'describe_rule' ), $rules ) );
	}

	/**
	 * Rule with design info.
	 *
	 * @param array<string,mixed> $rule Rule.
	 * @return array<string,mixed>
	 */
	public static function describe_rule( array $rule ) {
		$post           = get_post( (int) $rule['design_id'] );
		$rule['design'] = $post ? Templates::describe_design( $post ) : null;
		return $rule;
	}

	/**
	 * One rule.
	 *
	 * @param array<string,mixed> $input id.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_template( array $input ) {
		$rule = Templates::get( isset( $input['id'] ) ? (string) $input['id'] : '' );
		if ( ! $rule ) {
			return new \WP_Error( 'kirki_woo_not_found', __( 'Template rule not found.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		return self::describe_rule( $rule );
	}

	/**
	 * Create a rule.
	 *
	 * @param array<string,mixed> $input Rule fields.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_template( array $input ) {
		$rule = Templates::create( $input );
		return is_wp_error( $rule ) ? $rule : self::describe_rule( $rule );
	}

	/**
	 * Update a rule.
	 *
	 * @param array<string,mixed> $input id + fields.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function update_template( array $input ) {
		$id = isset( $input['id'] ) ? (string) $input['id'] : '';
		unset( $input['id'] );
		$rule = Templates::update( $id, $input );
		return is_wp_error( $rule ) ? $rule : self::describe_rule( $rule );
	}

	/**
	 * Delete a rule.
	 *
	 * @param array<string,mixed> $input id.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function delete_template( array $input ) {
		$id = isset( $input['id'] ) ? (string) $input['id'] : '';
		if ( ! Templates::delete( $id ) ) {
			return new \WP_Error( 'kirki_woo_not_found', __( 'Template rule not found.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		return array(
			'deleted' => true,
			'id'      => $id,
		);
	}

	/**
	 * Which rule would apply for a context/object.
	 *
	 * @param array<string,mixed> $input context, object_id, account_endpoint, logged_in.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function resolve_template( array $input ) {
		$context  = isset( $input['context'] ) ? sanitize_key( $input['context'] ) : '';
		$contexts = Templates::contexts();
		if ( ! isset( $contexts[ $context ] ) ) {
			return new \WP_Error( 'kirki_woo_invalid_context', __( 'Unknown context.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		$object_id = isset( $input['object_id'] ) ? absint( $input['object_id'] ) : 0;
		$logged_in = ! empty( $input['logged_in'] );
		if ( 'single_product' === $context ) {
			$product = $object_id ? wc_get_product( $object_id ) : null;
			if ( ! $product ) {
				return new \WP_Error( 'kirki_woo_invalid_product', __( 'object_id must be a product id.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
			}
			$facts = Templates::product_facts( $product );
		} elseif ( 'product_archive' === $context ) {
			$term = $object_id ? get_term( $object_id ) : null;
			if ( ! $term instanceof \WP_Term ) {
				return new \WP_Error( 'kirki_woo_invalid_term', __( 'object_id must be a term id.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
			}
			$facts = Templates::term_facts( $term );
		} else {
			$facts = array( 'context' => $context );
			if ( 'my_account' === $context ) {
				$facts['account_endpoint'] = isset( $input['account_endpoint'] ) ? sanitize_key( $input['account_endpoint'] ) : 'dashboard';
				$logged_in                 = true;
			}
		}
		$facts['logged_in'] = $logged_in;
		$rule               = Templates::match( $facts );
		return array(
			'facts' => $facts,
			'rule'  => $rule ? self::describe_rule( $rule ) : null,
		);
	}

	/**
	 * Candidate designs.
	 *
	 * @return array<string,mixed>
	 */
	public static function list_designs() {
		return array( 'designs' => Templates::designs() );
	}

	/**
	 * Create a design (+ rule).
	 *
	 * @param array<string,mixed> $input context, title, starter, create_rule.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create_design( array $input ) {
		return Templates::create_design(
			isset( $input['context'] ) ? sanitize_key( $input['context'] ) : '',
			isset( $input['title'] ) ? (string) $input['title'] : '',
			! isset( $input['starter'] ) || (bool) $input['starter'],
			! isset( $input['create_rule'] ) || (bool) $input['create_rule']
		);
	}

	/**
	 * Trash or delete a kirki_template design and the rules that use it.
	 *
	 * @param array<string,mixed> $input id, force.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function delete_design( array $input ) {
		$id   = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'kirki_template' !== $post->post_type ) {
			return new \WP_Error( 'kirki_woo_not_found', __( 'Kirki design not found. Only kirki_template designs can be deleted here.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		$can = post_type_exists( $post->post_type ) ? current_user_can( 'delete_post', $id ) : current_user_can( 'delete_others_posts' );
		if ( ! $can ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to delete this design.', 'kirki-woocommerce-bridge' ), array( 'status' => rest_authorization_required_code() ) );
		}
		$force = ! empty( $input['force'] );
		$rules = array_values(
			array_filter(
				Templates::all(),
				static function ( $rule ) use ( $id ) {
					return (int) $rule['design_id'] === $id;
				}
			)
		);
		foreach ( $rules as $rule ) {
			Templates::delete( $rule['id'] );
		}
		$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $result ) {
			return new \WP_Error( 'kirki_woo_delete_failed', __( 'The design could not be deleted.', 'kirki-woocommerce-bridge' ), array( 'status' => 500 ) );
		}
		return array(
			'deleted'       => true,
			'id'            => $id,
			'trashed'       => ! $force,
			'removed_rules' => wp_list_pluck( $rules, 'id' ),
		);
	}

	/**
	 * Load a design post accepted by this plugin.
	 *
	 * @param int $id Post id.
	 * @return \WP_Post|\WP_Error
	 */
	private static function design_post( $id ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! in_array( $post->post_type, Templates::design_post_types(), true ) ) {
			return new \WP_Error( 'kirki_woo_not_found', __( 'Kirki design not found.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		if ( ! Templates::can_edit_design( $post ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to edit this design.', 'kirki-woocommerce-bridge' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $post;
	}

	/**
	 * Published Kirki blocks of a design (keyed by block id).
	 *
	 * @param int $id Post id.
	 * @return array<string,array<string,mixed>>
	 */
	private static function design_blocks( $id ) {
		$data = get_post_meta( $id, 'kirki', true );
		return is_array( $data ) && isset( $data['blocks'] ) && is_array( $data['blocks'] ) ? $data['blocks'] : array();
	}

	/**
	 * Whether Kirki holds editor changes newer than the published version.
	 *
	 * @param int $id Post id.
	 * @return bool
	 */
	private static function has_unpublished_changes( $id ) {
		$facade = '\Kirki\App\Supports\Facades\Page';
		if ( ! class_exists( $facade ) ) {
			return false;
		}
		try {
			return (bool) $facade::get_most_recent_unpublished_stage_version( (int) $id );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Describe a design and its published element tree.
	 *
	 * @param array<string,mixed> $input id.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_design( array $input ) {
		$post = self::design_post( isset( $input['id'] ) ? absint( $input['id'] ) : 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$blocks = array();
		foreach ( self::design_blocks( $post->ID ) as $id => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$item = array(
				'id'       => (string) $id,
				'name'     => isset( $block['name'] ) ? (string) $block['name'] : '',
				'title'    => isset( $block['title'] ) ? (string) $block['title'] : '',
				'source'   => isset( $block['source'] ) ? (string) $block['source'] : '',
				'parentId' => isset( $block['parentId'] ) ? (string) $block['parentId'] : '',
				'children' => isset( $block['children'] ) && is_array( $block['children'] ) ? array_values( array_map( 'strval', $block['children'] ) ) : array(),
			);
			if ( KIRKI_WOO_SOURCE === $item['source'] && isset( $block['properties']['settings'] ) ) {
				$item['settings'] = $block['properties']['settings'];
			}
			$blocks[] = $item;
		}
		$rules = array_values(
			array_filter(
				Templates::all(),
				static function ( $rule ) use ( $post ) {
					return (int) $rule['design_id'] === (int) $post->ID;
				}
			)
		);
		return array(
			'design'                  => Templates::describe_design( $post ),
			'blocks'                  => $blocks,
			'rules'                   => $rules,
			'has_unpublished_changes' => self::has_unpublished_changes( $post->ID ),
		);
	}

	/**
	 * Append WooCommerce elements to a design's published version.
	 *
	 * @param array<string,mixed> $input id, elements[{name,settings}], parent_id, position.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function add_design_elements( array $input ) {
		$post = self::design_post( isset( $input['id'] ) ? absint( $input['id'] ) : 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$blocks = self::design_blocks( $post->ID );
		$parent = isset( $input['parent_id'] ) && '' !== $input['parent_id'] ? sanitize_text_field( (string) $input['parent_id'] ) : 'body';
		if ( ! isset( $blocks[ $parent ] ) || ! is_array( $blocks[ $parent ] ) ) {
			return new \WP_Error( 'kirki_woo_invalid_parent', __( 'parent_id is not an element of this design. Use get-design to list element ids.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		$elements = isset( $input['elements'] ) && is_array( $input['elements'] ) ? array_values( $input['elements'] ) : array();
		if ( ! $elements ) {
			return new \WP_Error( 'kirki_woo_invalid_elements', __( 'elements must contain at least one element.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		$new = array();
		foreach ( $elements as $i => $element ) {
			$name     = is_array( $element ) && isset( $element['name'] ) ? (string) $element['name'] : '';
			$settings = is_array( $element ) && isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			$block    = Elements::build_block( $name, $settings, $parent );
			if ( is_wp_error( $block ) ) {
				/* translators: 1: element index, 2: error message. */
				return new \WP_Error( $block->get_error_code(), sprintf( __( 'elements[%1$d]: %2$s', 'kirki-woocommerce-bridge' ), $i, $block->get_error_message() ), $block->get_error_data() );
			}
			while ( isset( $blocks[ $block['id'] ] ) ) {
				$block['id'] = 'kwi' . strtolower( wp_generate_password( 8, false, false ) );
			}
			$blocks[ $block['id'] ] = $block;
			$new[]                  = $block['id'];
		}
		$children = isset( $blocks[ $parent ]['children'] ) && is_array( $blocks[ $parent ]['children'] ) ? array_values( $blocks[ $parent ]['children'] ) : array();
		$position = isset( $input['position'] ) ? (int) $input['position'] : -1;
		if ( $position < 0 || $position > count( $children ) ) {
			$position = count( $children );
		}
		array_splice( $children, $position, 0, $new );
		$blocks[ $parent ]['children'] = $children;

		$facade = '\Kirki\App\Supports\Facades\Page';
		$saved  = false;
		if ( class_exists( $facade ) ) {
			try {
				$facade::save_blocks( (int) $post->ID, array( 'blocks' => $blocks ), false );
				$saved = true;
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}
		if ( ! $saved ) {
			update_post_meta( $post->ID, 'kirki', wp_slash( array( 'blocks' => $blocks ) ) );
		}
		clean_post_cache( $post->ID );
		return array(
			'design'                  => Templates::describe_design( $post ),
			'added'                   => $new,
			'parent_id'               => $parent,
			'position'                => $position,
			'has_unpublished_changes' => self::has_unpublished_changes( $post->ID ),
		);
	}
}
