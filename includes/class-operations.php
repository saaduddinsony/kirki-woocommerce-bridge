<?php
/**
 * The single list of public operations.
 *
 * Both the REST API (namespace kirki-woo/v1) and the WordPress Abilities API
 * (abilities named kirki-woo/<operation>) are generated from this table, so
 * the two surfaces can never drift apart. See docs/API.md.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Operation registry.
 */
final class Operations {

	/**
	 * Operations.
	 *
	 * Keys: ability (name without namespace), label, description, method, route,
	 * cap, callback (Api method), input (JSON schema properties), required,
	 * readonly, destructive, idempotent.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		$id_str     = array(
			'type'        => 'string',
			'description' => __( 'Template rule id.', 'kirki-woocommerce-bridge' ),
			'pattern'     => '^[a-z0-9]+$',
		);
		$element    = array(
			'type'        => 'string',
			'description' => __( 'Element name (kirki-woo-product-title) or slug (product-title).', 'kirki-woocommerce-bridge' ),
		);
		$settings   = array(
			'type'        => 'object',
			'description' => __( 'Element settings; see the element schema.', 'kirki-woocommerce-bridge' ),
		);
		$context    = array(
			'type'        => 'string',
			'enum'        => array_keys( Templates::contexts() ),
			'description' => __( 'WooCommerce page context.', 'kirki-woocommerce-bridge' ),
		);
		$rule_props = array(
			'title'      => array( 'type' => 'string' ),
			'context'    => $context,
			'design_id'  => array(
				'type'        => 'integer',
				'description' => __( 'kirki_template / page post id that holds the Kirki design.', 'kirki-woocommerce-bridge' ),
			),
			'conditions' => array(
				'type'        => 'object',
				'description' => __( 'Conditions allowed for the context; see template contexts.', 'kirki-woocommerce-bridge' ),
			),
			'priority'   => array(
				'type'    => 'integer',
				'minimum' => -1000,
				'maximum' => 1000,
			),
			'enabled'    => array( 'type' => 'boolean' ),
		);

		$ops = array(
			array(
				'ability'     => 'get-status',
				'label'       => __( 'Get Kirki WooCommerce status', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Plugin, WooCommerce and Kirki versions, readiness, features and counts.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/status',
				'cap'         => Api::CAP_READ,
				'callback'    => 'status',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'list-elements',
				'label'       => __( 'List WooCommerce elements', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Every WooCommerce element available in the Kirki builder, with settings and JSON schema.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/elements',
				'cap'         => Api::CAP_READ,
				'callback'    => 'list_elements',
				'input'       => array(
					'group' => array(
						'type' => 'string',
						'enum' => array_keys( Elements::groups() ),
					),
				),
				'readonly'    => true,
			),
			array(
				'ability'     => 'get-element',
				'label'       => __( 'Get a WooCommerce element', 'kirki-woocommerce-bridge' ),
				'description' => __( 'One element definition with its settings schema.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/elements/(?P<name>[a-z0-9-]+)',
				'cap'         => Api::CAP_READ,
				'callback'    => 'get_element',
				'input'       => array( 'name' => $element ),
				'required'    => array( 'name' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'render-element',
				'label'       => __( 'Render a WooCommerce element', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Server-render an element to HTML (the same output the frontend uses). Used for editor previews.', 'kirki-woocommerce-bridge' ),
				'method'      => 'POST',
				'route'       => '/render',
				'cap'         => Api::CAP_READ,
				'callback'    => 'render',
				'input'       => array(
					'name'       => $element,
					'settings'   => $settings,
					'product_id' => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Product to render for; 0 uses the preview product.', 'kirki-woocommerce-bridge' ),
					),
					'strict'     => array(
						'type'        => 'boolean',
						'description' => __( 'Reject unknown or invalid settings instead of coercing them.', 'kirki-woocommerce-bridge' ),
					),
				),
				'required'    => array( 'name' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'build-block',
				'label'       => __( 'Build a Kirki block for an element', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Return a validated Kirki element node ready to insert into Kirki page data (does not save anything).', 'kirki-woocommerce-bridge' ),
				'method'      => 'POST',
				'route'       => '/blocks',
				'cap'         => Api::CAP_READ,
				'callback'    => 'build_block',
				'input'       => array(
					'name'      => $element,
					'settings'  => $settings,
					'parent_id' => array(
						'type'    => 'string',
						'default' => 'body',
					),
					'id'        => array(
						'type'    => 'string',
						'pattern' => '^[a-z0-9_]*$',
					),
				),
				'required'    => array( 'name' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'list-dynamic-fields',
				'label'       => __( 'List WooCommerce dynamic data fields', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Product and store fields usable as Kirki dynamic content and in the [kirki_woo] shortcode.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/dynamic-data/fields',
				'cap'         => Api::CAP_READ,
				'callback'    => 'dynamic_fields',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'resolve-dynamic-field',
				'label'       => __( 'Resolve a WooCommerce dynamic field', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Value of a product or store field for a product (or the preview product).', 'kirki-woocommerce-bridge' ),
				'method'      => 'POST',
				'route'       => '/dynamic-data/resolve',
				'cap'         => Api::CAP_READ,
				'callback'    => 'resolve_dynamic',
				'input'       => array(
					'field'      => array( 'type' => 'string' ),
					'scope'      => array(
						'type'    => 'string',
						'enum'    => array( 'product', 'store' ),
						'default' => 'product',
					),
					'product_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'meta'       => array(
						'type'        => 'string',
						'description' => __( 'Attribute taxonomy / meta key for the attribute and meta fields.', 'kirki-woocommerce-bridge' ),
					),
				),
				'required'    => array( 'field' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'get-settings',
				'label'       => __( 'Get Kirki WooCommerce settings', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Current settings and their schema.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/settings',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'get_settings',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'update-settings',
				'label'       => __( 'Update Kirki WooCommerce settings', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Change one or more settings. Unknown keys are rejected.', 'kirki-woocommerce-bridge' ),
				'method'      => 'PUT',
				'route'       => '/settings',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'update_settings',
				'input'       => array(
					'settings' => array(
						'type'        => 'object',
						'description' => __( 'Partial settings object.', 'kirki-woocommerce-bridge' ),
					),
				),
				'required'    => array( 'settings' ),
				'idempotent'  => true,
			),
			array(
				'ability'     => 'list-template-contexts',
				'label'       => __( 'List template contexts and conditions', 'kirki-woocommerce-bridge' ),
				'description' => __( 'WooCommerce page contexts a Kirki design can be assigned to, and the conditions each supports.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/templates/contexts',
				'cap'         => Api::CAP_READ,
				'callback'    => 'template_contexts',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'list-templates',
				'label'       => __( 'List WooCommerce template rules', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Template rules in match order (priority, then specificity).', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/templates',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'list_templates',
				'input'       => array( 'context' => $context ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'create-template',
				'label'       => __( 'Create a WooCommerce template rule', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Assign an existing Kirki design to a WooCommerce context with optional conditions.', 'kirki-woocommerce-bridge' ),
				'method'      => 'POST',
				'route'       => '/templates',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'create_template',
				'input'       => $rule_props,
				'required'    => array( 'context', 'design_id' ),
			),
			array(
				'ability'     => 'resolve-template',
				'label'       => __( 'Resolve which template applies', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Dry-run the matcher for a context and product / term id.', 'kirki-woocommerce-bridge' ),
				'method'      => 'POST',
				'route'       => '/templates/resolve',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'resolve_template',
				'input'       => array(
					'context'          => $context,
					'object_id'        => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Product id (single_product) or term id (product_archive).', 'kirki-woocommerce-bridge' ),
					),
					'account_endpoint' => array( 'type' => 'string' ),
					'logged_in'        => array( 'type' => 'boolean' ),
				),
				'required'    => array( 'context' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'get-template',
				'label'       => __( 'Get a WooCommerce template rule', 'kirki-woocommerce-bridge' ),
				'description' => __( 'One template rule with its design.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/templates/(?P<id>[a-z0-9]+)',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'get_template',
				'input'       => array( 'id' => $id_str ),
				'required'    => array( 'id' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'update-template',
				'label'       => __( 'Update a WooCommerce template rule', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Partially update a rule. conditions replaces the whole conditions object.', 'kirki-woocommerce-bridge' ),
				'method'      => 'PUT',
				'route'       => '/templates/(?P<id>[a-z0-9]+)',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'update_template',
				'input'       => array_merge( array( 'id' => $id_str ), $rule_props ),
				'required'    => array( 'id' ),
				'idempotent'  => true,
			),
			array(
				'ability'     => 'delete-template',
				'label'       => __( 'Delete a WooCommerce template rule', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Delete a rule. The Kirki design post is kept.', 'kirki-woocommerce-bridge' ),
				'method'      => 'DELETE',
				'route'       => '/templates/(?P<id>[a-z0-9]+)',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'delete_template',
				'input'       => array( 'id' => $id_str ),
				'required'    => array( 'id' ),
				'destructive' => true,
				'idempotent'  => true,
			),
			array(
				'ability'     => 'list-designs',
				'label'       => __( 'List Kirki designs', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Kirki templates and pages that can be assigned to WooCommerce contexts.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/designs',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'list_designs',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'create-design',
				'label'       => __( 'Create a Kirki design for a WooCommerce context', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Create a kirki_template post (optionally seeded with a starter layout) and, by default, a rule assigning it.', 'kirki-woocommerce-bridge' ),
				'method'      => 'POST',
				'route'       => '/designs',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'create_design',
				'input'       => array(
					'context'     => $context,
					'title'       => array( 'type' => 'string' ),
					'starter'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'create_rule' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'required'    => array( 'context' ),
			),
			array(
				'ability'     => 'get-design',
				'label'       => __( 'Get a Kirki design', 'kirki-woocommerce-bridge' ),
				'description' => __( 'A design with its published element tree (ids, names, parents, children, settings of WooCommerce elements) and the rules that use it.', 'kirki-woocommerce-bridge' ),
				'method'      => 'GET',
				'route'       => '/designs/(?P<id>[0-9]+)',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'get_design',
				'input'       => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'required'    => array( 'id' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'add-design-elements',
				'label'       => __( 'Add WooCommerce elements to a Kirki design', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Insert one or more elements (validated against their schemas) into the published version of a design, under parent_id at position (append by default). Existing elements are kept.', 'kirki-woocommerce-bridge' ),
				'method'      => 'POST',
				'route'       => '/designs/(?P<id>[0-9]+)/elements',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'add_design_elements',
				'input'       => array(
					'id'        => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'elements'  => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 50,
						'items'    => array(
							'type'                 => 'object',
							'properties'           => array(
								'name'     => array(
									'type'        => 'string',
									'description' => __( 'Element name or slug.', 'kirki-woocommerce-bridge' ),
								),
								'settings' => array( 'type' => 'object' ),
							),
							'required'             => array( 'name' ),
							'additionalProperties' => false,
						),
					),
					'parent_id' => array(
						'type'        => 'string',
						'default'     => 'body',
						'description' => __( 'Element id to insert into (see get-design).', 'kirki-woocommerce-bridge' ),
					),
					'position'  => array(
						'type'        => 'integer',
						'default'     => -1,
						'description' => __( 'Index among the parent\'s children; -1 appends.', 'kirki-woocommerce-bridge' ),
					),
				),
				'required'    => array( 'id', 'elements' ),
			),
			array(
				'ability'     => 'delete-design',
				'label'       => __( 'Delete a Kirki WooCommerce design', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Move a kirki_template design to the trash (or delete it with force) and remove the rules that use it. Pages are never deleted.', 'kirki-woocommerce-bridge' ),
				'method'      => 'DELETE',
				'route'       => '/designs/(?P<id>[0-9]+)',
				'cap'         => Api::CAP_MANAGE,
				'callback'    => 'delete_design',
				'input'       => array(
					'id'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Delete permanently instead of moving to the trash.', 'kirki-woocommerce-bridge' ),
					),
				),
				'required'    => array( 'id' ),
				'destructive' => true,
			),
		);

		foreach ( $ops as &$op ) {
			$op = array_merge(
				array(
					'required'    => array(),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
				$op
			);
			if ( $op['readonly'] ) {
				$op['idempotent'] = true;
			}
		}
		unset( $op );

		/**
		 * Filter the public operations (REST routes + abilities).
		 *
		 * @since 1.0.0
		 * @param array $ops Operations.
		 */
		return apply_filters( 'kirki_woo_operations', $ops );
	}

	/**
	 * JSON schema object for an operation's input.
	 *
	 * @param array<string,mixed> $op Operation.
	 * @return array<string,mixed>
	 */
	public static function input_schema( array $op ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => empty( $op['input'] ) ? new \stdClass() : $op['input'],
			'additionalProperties' => false,
		);
		if ( ! empty( $op['required'] ) ) {
			$schema['required'] = array_values( $op['required'] );
		}
		return $schema;
	}

	/**
	 * Run an operation with a capability check.
	 *
	 * @param array<string,mixed> $op    Operation.
	 * @param array<string,mixed> $input Input.
	 * @return mixed
	 */
	public static function run( array $op, array $input ) {
		$callback = array( '\KirkiWoo\Api', $op['callback'] );
		if ( ! is_callable( $callback ) ) {
			return new \WP_Error( 'kirki_woo_bad_operation', __( 'Operation not available.', 'kirki-woocommerce-bridge' ), array( 'status' => 500 ) );
		}
		return call_user_func( $callback, $input );
	}
}
