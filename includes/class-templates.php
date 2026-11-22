<?php
/**
 * WooCommerce template conditions for Kirki designs.
 *
 * A rule says "on this WooCommerce page type, when these conditions hold,
 * render this Kirki design instead of the theme template". Designs are
 * ordinary Kirki templates (post type kirki_template) or Kirki pages, so they
 * are edited in the Kirki editor like anything else.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Template rules: storage, validation, matching and routing.
 */
final class Templates {

	const OPTION = 'kirki_woo_templates';

	/**
	 * Rule chosen for this request.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $applied = null;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'route' ), 10000 );
		add_action( 'wp_footer', array( __CLASS__, 'debug_comment' ), 999 );
		add_action( 'before_delete_post', array( __CLASS__, 'forget_design' ) );
	}

	/**
	 * Supported contexts.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function contexts() {
		$product_conditions = array( 'product_ids', 'exclude_product_ids', 'product_cat', 'product_tag', 'product_type', 'stock_status', 'on_sale', 'featured', 'user_state' );
		return array(
			'single_product'   => array(
				'label'      => __( 'Single product', 'kirki-woocommerce-bridge' ),
				'conditions' => $product_conditions,
			),
			'shop'             => array(
				'label'      => __( 'Shop page', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'user_state' ),
			),
			'product_archive'  => array(
				'label'      => __( 'Product category, tag and attribute archives', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'taxonomy', 'term_ids', 'user_state' ),
			),
			'product_search'   => array(
				'label'      => __( 'Product search results', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'user_state' ),
			),
			'cart'             => array(
				'label'      => __( 'Cart (with items)', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'user_state' ),
			),
			'cart_empty'       => array(
				'label'      => __( 'Empty cart', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'user_state' ),
			),
			'checkout'         => array(
				'label'      => __( 'Checkout', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'user_state' ),
			),
			'order_received'   => array(
				'label'      => __( 'Order received (thank you)', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'user_state' ),
			),
			'my_account'       => array(
				'label'      => __( 'My account (signed in)', 'kirki-woocommerce-bridge' ),
				'conditions' => array( 'account_endpoint' ),
			),
			'my_account_login' => array(
				'label'      => __( 'My account login / register (signed out)', 'kirki-woocommerce-bridge' ),
				'conditions' => array(),
			),
		);
	}

	/**
	 * Condition definitions: JSON-schema-ish type and description.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function condition_schema() {
		return array(
			'product_ids'         => array(
				'type'        => 'integer[]',
				'description' => __( 'Only these products.', 'kirki-woocommerce-bridge' ),
			),
			'exclude_product_ids' => array(
				'type'        => 'integer[]',
				'description' => __( 'Never these products.', 'kirki-woocommerce-bridge' ),
			),
			'product_cat'         => array(
				'type'        => 'integer[]',
				'description' => __( 'Products in any of these categories (child categories included).', 'kirki-woocommerce-bridge' ),
			),
			'product_tag'         => array(
				'type'        => 'integer[]',
				'description' => __( 'Products with any of these tags.', 'kirki-woocommerce-bridge' ),
			),
			'product_type'        => array(
				'type'        => 'string[]',
				'enum'        => array_keys( function_exists( 'wc_get_product_types' ) ? wc_get_product_types() : array() ),
				'description' => __( 'Product types such as simple or variable.', 'kirki-woocommerce-bridge' ),
			),
			'stock_status'        => array(
				'type'        => 'string[]',
				'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
				'description' => __( 'Stock statuses.', 'kirki-woocommerce-bridge' ),
			),
			'on_sale'             => array(
				'type'        => 'boolean',
				'description' => __( 'true = only on-sale products, false = only full-price products.', 'kirki-woocommerce-bridge' ),
			),
			'featured'            => array(
				'type'        => 'boolean',
				'description' => __( 'true = only featured products, false = only non-featured.', 'kirki-woocommerce-bridge' ),
			),
			'taxonomy'            => array(
				'type'        => 'string[]',
				'description' => __( 'Archive taxonomies, e.g. product_cat, product_tag, pa_color.', 'kirki-woocommerce-bridge' ),
			),
			'term_ids'            => array(
				'type'        => 'integer[]',
				'description' => __( 'Archive terms (child terms included).', 'kirki-woocommerce-bridge' ),
			),
			'account_endpoint'    => array(
				'type'        => 'string[]',
				'description' => __( 'My account endpoints: dashboard, orders, view-order, downloads, edit-address, edit-account, payment-methods, add-payment-method.', 'kirki-woocommerce-bridge' ),
			),
			'user_state'          => array(
				'type'        => 'string',
				'enum'        => array( 'any', 'logged_in', 'logged_out' ),
				'description' => __( 'Visitor sign-in state.', 'kirki-woocommerce-bridge' ),
			),
		);
	}

	/**
	 * Post types that can hold a design.
	 *
	 * @return string[]
	 */
	public static function design_post_types() {
		/**
		 * Filters the post types accepted as template designs.
		 *
		 * @since 1.0.0
		 */
		return (array) apply_filters( 'kirki_woo_design_post_types', array( 'kirki_template', 'page', 'kirki_page' ) );
	}

	/**
	 * All rules sorted by priority (desc), then specificity.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		$rules = get_option( self::OPTION, array() );
		$rules = is_array( $rules ) ? array_values( array_filter( $rules, 'is_array' ) ) : array();
		return self::sort( $rules );
	}

	/**
	 * Sort rules.
	 *
	 * @param array<int,array<string,mixed>> $rules Rules.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sort( array $rules ) {
		usort(
			$rules,
			static function ( $a, $b ) {
				$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 10;
				$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 10;
				if ( $pa !== $pb ) {
					return $pb <=> $pa;
				}
				$sa = isset( $a['conditions'] ) ? count( array_filter( (array) $a['conditions'], static fn( $v ) => null !== $v && array() !== $v && 'any' !== $v ) ) : 0;
				$sb = isset( $b['conditions'] ) ? count( array_filter( (array) $b['conditions'], static fn( $v ) => null !== $v && array() !== $v && 'any' !== $v ) ) : 0;
				if ( $sa !== $sb ) {
					return $sb <=> $sa;
				}
				return strcmp( (string) ( $a['id'] ?? '' ), (string) ( $b['id'] ?? '' ) );
			}
		);
		return $rules;
	}

	/**
	 * Get one rule.
	 *
	 * @param string $id Id.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $rule ) {
			if ( isset( $rule['id'] ) && (string) $rule['id'] === (string) $id ) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * Validate and normalise rule input.
	 *
	 * @param array<string,mixed>      $input    Input.
	 * @param array<string,mixed>|null $existing Existing rule when updating.
	 * @param bool                     $check_design Verify the design post (needs WordPress).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function validate( array $input, $existing = null, $check_design = true ) {
		$rule = $existing ? $existing : array(
			'id'         => '',
			'title'      => '',
			'context'    => '',
			'design_id'  => 0,
			'conditions' => array(),
			'priority'   => 10,
			'enabled'    => true,
		);
		foreach ( array( 'title', 'context', 'design_id', 'conditions', 'priority', 'enabled' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$rule[ $key ] = $input[ $key ];
			}
		}

		$contexts = self::contexts();
		$context  = is_string( $rule['context'] ) ? sanitize_key( $rule['context'] ) : '';
		if ( ! isset( $contexts[ $context ] ) ) {
			return new \WP_Error( 'kirki_woo_invalid_context', sprintf( /* translators: %s: list. */ __( 'context must be one of: %s', 'kirki-woocommerce-bridge' ), implode( ', ', array_keys( $contexts ) ) ), array( 'status' => 400 ) );
		}
		$rule['context'] = $context;

		$rule['design_id'] = absint( $rule['design_id'] );
		if ( ! $rule['design_id'] ) {
			return new \WP_Error( 'kirki_woo_invalid_design', __( 'design_id is required.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		if ( $check_design ) {
			$valid = self::validate_design( $rule['design_id'] );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$rule['title']    = sanitize_text_field( (string) $rule['title'] );
		$rule['priority'] = max( -1000, min( 1000, (int) $rule['priority'] ) );
		$rule['enabled']  = (bool) filter_var( $rule['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		$conditions = is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		$allowed    = $contexts[ $context ]['conditions'];
		$schema     = self::condition_schema();
		$clean      = array();
		foreach ( $conditions as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) ) {
				return new \WP_Error( 'kirki_woo_invalid_condition', sprintf( /* translators: %s: key. */ __( 'Unknown condition: %s', 'kirki-woocommerce-bridge' ), $key ), array( 'status' => 400 ) );
			}
			if ( ! in_array( $key, $allowed, true ) ) {
				return new \WP_Error( 'kirki_woo_invalid_condition', sprintf( /* translators: 1: condition, 2: context. */ __( 'Condition %1$s is not available for context %2$s.', 'kirki-woocommerce-bridge' ), $key, $context ), array( 'status' => 400 ) );
			}
			$type = $schema[ $key ]['type'];
			if ( 'integer[]' === $type ) {
				$clean[ $key ] = array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
			} elseif ( 'string[]' === $type ) {
				$list = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $value ), 'strlen' ) ) );
				if ( ! empty( $schema[ $key ]['enum'] ) ) {
					$bad = array_diff( $list, $schema[ $key ]['enum'] );
					if ( $bad ) {
						return new \WP_Error( 'kirki_woo_invalid_condition', sprintf( /* translators: 1: key, 2: values. */ __( 'Invalid value for %1$s: %2$s', 'kirki-woocommerce-bridge' ), $key, implode( ', ', $bad ) ), array( 'status' => 400 ) );
					}
				}
				$clean[ $key ] = $list;
			} elseif ( 'boolean' === $type ) {
				$clean[ $key ] = null === $value ? null : (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			} else {
				$value = sanitize_key( (string) $value );
				if ( ! empty( $schema[ $key ]['enum'] ) && ! in_array( $value, $schema[ $key ]['enum'], true ) ) {
					return new \WP_Error( 'kirki_woo_invalid_condition', sprintf( /* translators: %s: key. */ __( 'Invalid value for %s.', 'kirki-woocommerce-bridge' ), $key ), array( 'status' => 400 ) );
				}
				$clean[ $key ] = $value;
			}
		}
		$rule['conditions'] = $clean;
		if ( '' === $rule['title'] ) {
			$rule['title'] = $contexts[ $context ]['label'];
		}
		return $rule;
	}

	/**
	 * Check a design post.
	 *
	 * @param int $design_id Post id.
	 * @return true|\WP_Error
	 */
	public static function validate_design( $design_id ) {
		$post = get_post( $design_id );
		if ( ! $post || ! in_array( $post->post_type, self::design_post_types(), true ) ) {
			return new \WP_Error( 'kirki_woo_invalid_design', __( 'design_id must be a Kirki template or a Kirki page.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		if ( 'kirki' !== get_post_meta( $post->ID, 'kirki_editor_mode', true ) ) {
			return new \WP_Error( 'kirki_woo_invalid_design', __( 'The design has not been edited with Kirki yet.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		if ( function_exists( 'current_user_can' ) && is_user_logged_in() && ! self::can_edit_design( $post ) ) {
			return new \WP_Error( 'kirki_woo_forbidden', __( 'You cannot use this design.', 'kirki-woocommerce-bridge' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Persist rules.
	 *
	 * @param array<int,array<string,mixed>> $rules Rules.
	 * @return void
	 */
	private static function save( array $rules ) {
		update_option( self::OPTION, array_values( $rules ), false );
	}

	/**
	 * Create a rule.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function create( array $input ) {
		$rule = self::validate( $input );
		if ( is_wp_error( $rule ) ) {
			return $rule;
		}
		$rule['id']      = 'r' . strtolower( wp_generate_password( 10, false, false ) );
		$rule['created'] = gmdate( 'c' );
		$rule['updated'] = $rule['created'];
		$rules           = self::all();
		$rules[]         = $rule;
		self::save( $rules );
		return $rule;
	}

	/**
	 * Update a rule.
	 *
	 * @param string              $id    Id.
	 * @param array<string,mixed> $input Partial input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function update( $id, array $input ) {
		$rules = self::all();
		foreach ( $rules as $i => $rule ) {
			if ( (string) $rule['id'] === (string) $id ) {
				$next = self::validate( $input, $rule );
				if ( is_wp_error( $next ) ) {
					return $next;
				}
				$next['updated'] = gmdate( 'c' );
				$rules[ $i ]     = $next;
				self::save( $rules );
				return $next;
			}
		}
		return new \WP_Error( 'kirki_woo_not_found', __( 'Template rule not found.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
	}

	/**
	 * Delete a rule (the design post is kept).
	 *
	 * @param string $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		$rules = self::all();
		$next  = array_values( array_filter( $rules, static fn( $r ) => (string) $r['id'] !== (string) $id ) );
		if ( count( $next ) === count( $rules ) ) {
			return false;
		}
		self::save( $next );
		return true;
	}

	/**
	 * Remove rules pointing at a deleted design.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function forget_design( $post_id ) {
		$rules = self::all();
		$next  = array_values( array_filter( $rules, static fn( $r ) => (int) $r['design_id'] !== (int) $post_id ) );
		if ( count( $next ) !== count( $rules ) ) {
			self::save( $next );
		}
	}

	/**
	 * Does a rule match the facts of a request? Pure function.
	 *
	 * @param array<string,mixed> $rule  Rule.
	 * @param array<string,mixed> $facts Facts (see facts()).
	 * @return bool
	 */
	public static function rule_matches( array $rule, array $facts ) {
		if ( empty( $rule['enabled'] ) || ! isset( $rule['context'], $facts['context'] ) || $rule['context'] !== $facts['context'] ) {
			return false;
		}
		$c = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();

		$intersects = static function ( $wanted, $have ) {
			return (bool) array_intersect( array_map( 'strval', (array) $wanted ), array_map( 'strval', (array) $have ) );
		};

		if ( ! empty( $c['product_ids'] ) && ! in_array( (int) ( $facts['product_id'] ?? 0 ), array_map( 'intval', $c['product_ids'] ), true ) ) {
			return false;
		}
		if ( ! empty( $c['exclude_product_ids'] ) && in_array( (int) ( $facts['product_id'] ?? 0 ), array_map( 'intval', $c['exclude_product_ids'] ), true ) ) {
			return false;
		}
		if ( ! empty( $c['product_cat'] ) && ! $intersects( $c['product_cat'], $facts['product_cat'] ?? array() ) ) {
			return false;
		}
		if ( ! empty( $c['product_tag'] ) && ! $intersects( $c['product_tag'], $facts['product_tag'] ?? array() ) ) {
			return false;
		}
		if ( ! empty( $c['product_type'] ) && ! in_array( (string) ( $facts['product_type'] ?? '' ), (array) $c['product_type'], true ) ) {
			return false;
		}
		if ( ! empty( $c['stock_status'] ) && ! in_array( (string) ( $facts['stock_status'] ?? '' ), (array) $c['stock_status'], true ) ) {
			return false;
		}
		if ( isset( $c['on_sale'] ) && null !== $c['on_sale'] && (bool) ( $facts['on_sale'] ?? false ) !== (bool) $c['on_sale'] ) {
			return false;
		}
		if ( isset( $c['featured'] ) && null !== $c['featured'] && (bool) ( $facts['featured'] ?? false ) !== (bool) $c['featured'] ) {
			return false;
		}
		if ( ! empty( $c['taxonomy'] ) && ! in_array( (string) ( $facts['taxonomy'] ?? '' ), (array) $c['taxonomy'], true ) ) {
			return false;
		}
		if ( ! empty( $c['term_ids'] ) && ! $intersects( $c['term_ids'], $facts['term_ids'] ?? array() ) ) {
			return false;
		}
		if ( ! empty( $c['account_endpoint'] ) && ! in_array( (string) ( $facts['account_endpoint'] ?? 'dashboard' ), (array) $c['account_endpoint'], true ) ) {
			return false;
		}
		if ( ! empty( $c['user_state'] ) && 'any' !== $c['user_state'] ) {
			$logged_in = ! empty( $facts['logged_in'] );
			if ( ( 'logged_in' === $c['user_state'] ) !== $logged_in ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * First matching rule.
	 *
	 * @param array<string,mixed>                 $facts Facts.
	 * @param array<int,array<string,mixed>>|null $rules Rules (defaults to stored).
	 * @return array<string,mixed>|null
	 */
	public static function match( array $facts, $rules = null ) {
		$rules = null === $rules ? self::all() : self::sort( $rules );
		foreach ( $rules as $rule ) {
			if ( self::rule_matches( $rule, $facts ) ) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * Facts for a product.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $context Context.
	 * @return array<string,mixed>
	 */
	public static function product_facts( $product, $context = 'single_product' ) {
		$cats = array();
		foreach ( $product->get_category_ids() as $cat_id ) {
			$cats[] = (int) $cat_id;
			foreach ( get_ancestors( (int) $cat_id, 'product_cat', 'taxonomy' ) as $ancestor ) {
				$cats[] = (int) $ancestor;
			}
		}
		return array(
			'context'      => $context,
			'product_id'   => $product->get_id(),
			'product_cat'  => array_values( array_unique( $cats ) ),
			'product_tag'  => array_map( 'intval', $product->get_tag_ids() ),
			'product_type' => $product->get_type(),
			'stock_status' => $product->get_stock_status(),
			'on_sale'      => $product->is_on_sale(),
			'featured'     => $product->is_featured(),
			'logged_in'    => is_user_logged_in(),
		);
	}

	/**
	 * Facts for a term archive.
	 *
	 * @param \WP_Term $term Term.
	 * @return array<string,mixed>
	 */
	public static function term_facts( $term ) {
		$ids = array_merge( array( (int) $term->term_id ), array_map( 'intval', get_ancestors( (int) $term->term_id, $term->taxonomy, 'taxonomy' ) ) );
		return array(
			'context'   => 'product_archive',
			'taxonomy'  => $term->taxonomy,
			'term_ids'  => $ids,
			'logged_in' => is_user_logged_in(),
		);
	}

	/**
	 * Facts for the current request, or null when it is not a WooCommerce page.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function facts() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return null;
		}
		$logged_in = is_user_logged_in();
		if ( is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			return $product ? self::product_facts( $product ) : null;
		}
		if ( is_product_taxonomy() ) {
			$term = get_queried_object();
			return $term instanceof \WP_Term ? self::term_facts( $term ) : null;
		}
		if ( is_search() && 'product' === get_query_var( 'post_type' ) ) {
			return array(
				'context'   => 'product_search',
				'logged_in' => $logged_in,
			);
		}
		if ( is_shop() ) {
			return array(
				'context'   => 'shop',
				'logged_in' => $logged_in,
			);
		}
		if ( is_cart() ) {
			$empty = ! WC()->cart || WC()->cart->is_empty();
			return array(
				'context'   => $empty ? 'cart_empty' : 'cart',
				'logged_in' => $logged_in,
			);
		}
		if ( is_checkout() ) {
			if ( is_wc_endpoint_url( 'order-received' ) ) {
				return array(
					'context'   => 'order_received',
					'logged_in' => $logged_in,
				);
			}
			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				return null; // Payment pages stay on WooCommerce's template.
			}
			return array(
				'context'   => 'checkout',
				'logged_in' => $logged_in,
			);
		}
		if ( is_account_page() ) {
			if ( ! $logged_in ) {
				return is_lost_password_page() ? null : array(
					'context'   => 'my_account_login',
					'logged_in' => false,
				);
			}
			$endpoint = WC()->query->get_current_endpoint();
			return array(
				'context'          => 'my_account',
				'account_endpoint' => $endpoint ? $endpoint : 'dashboard',
				'logged_in'        => true,
			);
		}
		return null;
	}

	/**
	 * `template_include`: swap in the Kirki design for a matching rule.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public static function route( $template ) {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $template;
		}
		if ( Context::is_editor_request() ) {
			return $template;
		}
		if ( ! defined( 'KIRKI_FULL_CANVAS_TEMPLATE_PATH' ) || ! file_exists( KIRKI_FULL_CANVAS_TEMPLATE_PATH ) ) {
			return $template;
		}

		$facts = self::facts();
		if ( ! $facts ) {
			return $template;
		}

		// A native Kirki template already targets this product / archive.
		if ( Settings::get( 'respect_kirki_native' ) && in_array( $facts['context'], array( 'single_product', 'product_archive' ), true ) ) {
			$custom = get_query_var( 'kirki_custom_data' );
			if ( is_array( $custom ) && ! empty( $custom['kirki_template_id'] ) && 'kirki_template' === get_post_type( (int) $custom['kirki_template_id'] ) ) {
				return $template;
			}
		}

		$rule = self::match( $facts );
		/**
		 * Filters the rule chosen for a request.
		 *
		 * @since 1.0.0
		 *
		 * @param array|null $rule  Rule or null.
		 * @param array      $facts Request facts.
		 */
		$rule = apply_filters( 'kirki_woo_matched_template', $rule, $facts );
		if ( ! $rule ) {
			return $template;
		}
		$design = get_post( (int) $rule['design_id'] );
		if ( ! $design || ( 'publish' !== $design->post_status && ! self::can_edit_design( $design ) ) ) {
			return $template;
		}

		$html = apply_filters( 'kirki_html_generator', '', $design->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Kirki's own filter.
		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return $template;
		}

		self::$applied = $rule;
		set_query_var(
			'kirki_custom_data',
			array(
				'kirki_template_content' => $html,
				'kirki_template_id'      => $design->ID,
			)
		);
		add_filter( 'kirki_assets_should_load', '__return_true' );
		add_filter(
			'body_class',
			static function ( $classes ) use ( $rule ) {
				$classes[] = 'kirki-woo-template';
				$classes[] = 'kirki-woo-template--' . sanitize_html_class( $rule['context'] );
				return $classes;
			}
		);

		add_action( 'wp_body_open', array( __CLASS__, 'body_class_script' ), 0 );

		/**
		 * Fires when a Kirki design replaces a WooCommerce template.
		 *
		 * @since 1.0.0
		 */
		do_action( 'kirki_woo_template_applied', $rule, $facts );

		return KIRKI_FULL_CANVAS_TEMPLATE_PATH;
	}

	/**
	 * Kirki's full-canvas template prints a bare <body> on block themes, so the
	 * classes WooCommerce styles and scripts depend on (woocommerce,
	 * single-product, woocommerce-cart…) are missing. Add them right after the
	 * body tag opens.
	 *
	 * @return void
	 */
	public static function body_class_script() {
		$classes = array_values( array_filter( array_map( 'sanitize_html_class', get_body_class() ) ) );
		if ( ! $classes ) {
			return;
		}
		printf(
			'<script>document.body.classList.add.apply(document.body.classList,%s);</script>' . "\n",
			wp_json_encode( $classes )
		);
	}

	/**
	 * Rule applied to this request.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function applied() {
		return self::$applied;
	}

	/**
	 * Debug comment for administrators.
	 *
	 * @return void
	 */
	public static function debug_comment() {
		if ( ! self::$applied || ! Settings::get( 'template_debug' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		printf( "\n<!-- kirki-woo: rule %s (%s) design #%d -->\n", esc_html( self::$applied['id'] ), esc_html( self::$applied['context'] ), (int) self::$applied['design_id'] );
	}

	/**
	 * Candidate designs (Kirki templates and Kirki pages).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function designs() {
		$posts = get_posts(
			array(
				'post_type'      => self::design_post_types(),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Bounded admin list.
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_key'       => 'kirki_editor_mode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin list, bounded.
				'meta_value'     => 'kirki', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin list, bounded.
			)
		);
		$out   = array();
		foreach ( $posts as $post ) {
			if ( ! self::can_edit_design( $post ) ) {
				continue;
			}
			$out[] = self::describe_design( $post );
		}
		return $out;
	}

	/**
	 * Whether the current user may edit a design. Kirki stores templates in
	 * the unregistered kirki_template post type, for which WordPress maps
	 * edit_post to edit_others_posts; do the same without the notice.
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	public static function can_edit_design( $post ) {
		if ( post_type_exists( $post->post_type ) ) {
			return current_user_can( 'edit_post', $post->ID );
		}
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Describe a design post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	public static function describe_design( $post ) {
		return array(
			'id'         => $post->ID,
			'title'      => get_the_title( $post ),
			'post_type'  => $post->post_type,
			'status'     => $post->post_status,
			'editor_url' => self::editor_url( $post->ID ),
			'modified'   => mysql2date( 'c', $post->post_modified_gmt, false ),
		);
	}

	/**
	 * Kirki editor URL for a post.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function editor_url( $post_id ) {
		if ( class_exists( '\Kirki\HelperFunctions' ) && method_exists( '\Kirki\HelperFunctions', 'get_post_url_arr_from_post_id' ) ) {
			$urls = \Kirki\HelperFunctions::get_post_url_arr_from_post_id( $post_id, array( 'editor_url' => true ) );
			if ( is_array( $urls ) && ! empty( $urls['editor_url'] ) ) {
				return (string) $urls['editor_url'];
			}
		}
		return add_query_arg(
			array(
				'action'  => 'kirki',
				'post_id' => (int) $post_id,
			),
			get_permalink( $post_id )
		);
	}

	/**
	 * Create a Kirki template for a context, optionally with a starter layout,
	 * and a rule that applies it.
	 *
	 * @param string $context      Context.
	 * @param string $title        Title.
	 * @param bool   $with_starter Seed a starter layout.
	 * @param bool   $create_rule  Also create the rule.
	 * @return array{design:array<string,mixed>,rule:array<string,mixed>|null}|\WP_Error
	 */
	public static function create_design( $context, $title = '', $with_starter = true, $create_rule = true ) {
		$contexts = self::contexts();
		if ( ! isset( $contexts[ $context ] ) ) {
			return new \WP_Error( 'kirki_woo_invalid_context', __( 'Unknown context.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		if ( ! defined( 'KIRKI_VERSION' ) ) {
			return new \WP_Error( 'kirki_woo_no_kirki', __( 'Kirki templates are not available.', 'kirki-woocommerce-bridge' ), array( 'status' => 500 ) );
		}
		$title = '' !== $title ? sanitize_text_field( $title ) : sprintf( /* translators: %s: context label. */ __( 'WooCommerce – %s', 'kirki-woocommerce-bridge' ), $contexts[ $context ]['label'] );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'kirki_template',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$layout = $with_starter ? Starter_Layouts::build( $context ) : Starter_Layouts::blank();
		self::save_kirki_data( $post_id, $layout['blocks'], $layout['styles'] );
		update_post_meta( $post_id, 'kirki_template_conditions', self::inert_kirki_conditions() );

		$rule = null;
		if ( $create_rule ) {
			$rule = self::create(
				array(
					'context'   => $context,
					'design_id' => $post_id,
					'title'     => $title,
				)
			);
			if ( is_wp_error( $rule ) ) {
				return $rule;
			}
		}
		return array(
			'design' => self::describe_design( get_post( $post_id ) ),
			'rule'   => $rule,
		);
	}

	/**
	 * Kirki template conditions for designs created by this plugin.
	 *
	 * Kirki's editor needs at least one condition (it previews the template
	 * with the first matching post), and Kirki's own router must not apply the
	 * design by itself because this plugin's rules decide where it is used.
	 * "Hide on product #0" satisfies both: the editor previews products, and
	 * the condition never matches a real request.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function inert_kirki_conditions() {
		return array(
			array(
				'type'       => 'post',
				'from'       => 'post',
				'post_type'  => 'product',
				'where'      => 'single',
				'to'         => '0',
				'visibility' => 'hide',
			),
		);
	}

	/**
	 * Save Kirki data through Kirki's own storage API when present.
	 *
	 * @param int                 $post_id Post.
	 * @param array<string,mixed> $blocks  Blocks keyed by id.
	 * @param array<string,mixed> $styles  Style blocks keyed by id.
	 * @return void
	 */
	public static function save_kirki_data( $post_id, array $blocks, array $styles ) {
		$facade = '\Kirki\App\Supports\Facades\Page';
		if ( class_exists( $facade ) ) {
			try {
				$facade::save_blocks( (int) $post_id, array( 'blocks' => $blocks ), false );
				$facade::save_style_blocks( (int) $post_id, $styles, false );
				$facade::save_used_style_block_ids( (int) $post_id, array_keys( $styles ), false );
				$facade::save_used_global_style_block_ids( (int) $post_id, array(), false );
				$facade::save_editor_mode( (int) $post_id );
				return;
			} catch ( \Throwable $e ) {
				// Fall through to raw post meta below.
				unset( $e );
			}
		}
		update_post_meta( $post_id, 'kirki', wp_slash( array( 'blocks' => $blocks ) ) );
		update_post_meta( $post_id, 'kirki_global_style_block_random', wp_slash( $styles ) );
		update_post_meta( $post_id, 'kirki_used_style_block_ids_random', array_keys( $styles ) );
		update_post_meta( $post_id, 'kirki_used_style_block_ids', array() );
		update_post_meta( $post_id, 'kirki_editor_mode', 'kirki' );
	}
}
