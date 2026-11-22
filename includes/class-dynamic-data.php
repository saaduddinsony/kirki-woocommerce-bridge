<?php
/**
 * Product and store dynamic data for Kirki's dynamic content picker, a
 * shortcode and the REST API.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic data tags.
 */
final class Dynamic_Data {

	/**
	 * Kirki dynamic content "type" for product fields.
	 */
	const TYPE_PRODUCT = 'kirki_woo_product';

	/**
	 * Kirki dynamic content "type" for store-wide fields.
	 */
	const TYPE_STORE = 'kirki_woo_store';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'kirki_dynamic_content', array( __CLASS__, 'kirki_dynamic_content' ), 10, 2 );
		add_filter( 'kirki_dynamic_content_fields', array( __CLASS__, 'kirki_dynamic_content_fields' ), 10, 2 );
		add_shortcode( 'kirki_woo', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Product field definitions.
	 *
	 * Each: label, format (text|html|url|number), anchor (usable as a link).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function product_fields() {
		$fields = array(
			'id'                  => array( __( 'ID', 'kirki-woocommerce-bridge' ), 'number' ),
			'title'               => array( __( 'Name', 'kirki-woocommerce-bridge' ), 'text' ),
			'permalink'           => array( __( 'Product link', 'kirki-woocommerce-bridge' ), 'url', true ),
			'price_html'          => array( __( 'Price (formatted, with sale)', 'kirki-woocommerce-bridge' ), 'html' ),
			'price'               => array( __( 'Active price', 'kirki-woocommerce-bridge' ), 'text' ),
			'regular_price'       => array( __( 'Regular price', 'kirki-woocommerce-bridge' ), 'text' ),
			'sale_price'          => array( __( 'Sale price', 'kirki-woocommerce-bridge' ), 'text' ),
			'price_raw'           => array( __( 'Active price (number)', 'kirki-woocommerce-bridge' ), 'number' ),
			'discount_percentage' => array( __( 'Discount %', 'kirki-woocommerce-bridge' ), 'number' ),
			'sale_ends'           => array( __( 'Sale end date', 'kirki-woocommerce-bridge' ), 'text' ),
			'sku'                 => array( __( 'SKU', 'kirki-woocommerce-bridge' ), 'text' ),
			'stock_status'        => array( __( 'Stock status', 'kirki-woocommerce-bridge' ), 'text' ),
			'stock_quantity'      => array( __( 'Stock quantity', 'kirki-woocommerce-bridge' ), 'number' ),
			'availability'        => array( __( 'Availability text', 'kirki-woocommerce-bridge' ), 'text' ),
			'average_rating'      => array( __( 'Average rating', 'kirki-woocommerce-bridge' ), 'number' ),
			'rating_count'        => array( __( 'Rating count', 'kirki-woocommerce-bridge' ), 'number' ),
			'review_count'        => array( __( 'Review count', 'kirki-woocommerce-bridge' ), 'number' ),
			'rating_html'         => array( __( 'Rating stars', 'kirki-woocommerce-bridge' ), 'html' ),
			'short_description'   => array( __( 'Short description', 'kirki-woocommerce-bridge' ), 'html' ),
			'description'         => array( __( 'Description', 'kirki-woocommerce-bridge' ), 'html' ),
			'type'                => array( __( 'Product type', 'kirki-woocommerce-bridge' ), 'text' ),
			'categories'          => array( __( 'Categories', 'kirki-woocommerce-bridge' ), 'text' ),
			'tags'                => array( __( 'Tags', 'kirki-woocommerce-bridge' ), 'text' ),
			'attribute'           => array( __( 'Attribute (set slug in Meta)', 'kirki-woocommerce-bridge' ), 'text' ),
			'weight'              => array( __( 'Weight', 'kirki-woocommerce-bridge' ), 'text' ),
			'dimensions'          => array( __( 'Dimensions', 'kirki-woocommerce-bridge' ), 'text' ),
			'total_sales'         => array( __( 'Total sales', 'kirki-woocommerce-bridge' ), 'number' ),
			'on_sale'             => array( __( 'On sale label', 'kirki-woocommerce-bridge' ), 'text' ),
			'featured'            => array( __( 'Featured (yes/no)', 'kirki-woocommerce-bridge' ), 'text' ),
			'image_url'           => array( __( 'Image URL', 'kirki-woocommerce-bridge' ), 'url', true ),
			'add_to_cart_url'     => array( __( 'Add to cart URL', 'kirki-woocommerce-bridge' ), 'url', true ),
			'add_to_cart_text'    => array( __( 'Add to cart text', 'kirki-woocommerce-bridge' ), 'text' ),
			'meta'                => array( __( 'Custom field (set key in Meta)', 'kirki-woocommerce-bridge' ), 'text' ),
		);
		$out    = array();
		foreach ( $fields as $key => $def ) {
			$out[ $key ] = array(
				'label'  => $def[0],
				'format' => $def[1],
				'anchor' => ! empty( $def[2] ),
			);
		}
		/**
		 * Filters product dynamic fields. Add resolvers with `kirki_woo_dynamic_product_{key}`.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'kirki_woo_dynamic_product_fields', $out );
	}

	/**
	 * Store field definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function store_fields() {
		$fields = array(
			'cart_count'              => array( __( 'Cart item count', 'kirki-woocommerce-bridge' ), 'number' ),
			'cart_subtotal'           => array( __( 'Cart subtotal', 'kirki-woocommerce-bridge' ), 'html' ),
			'cart_total'              => array( __( 'Cart total', 'kirki-woocommerce-bridge' ), 'html' ),
			'cart_url'                => array( __( 'Cart URL', 'kirki-woocommerce-bridge' ), 'url', true ),
			'checkout_url'            => array( __( 'Checkout URL', 'kirki-woocommerce-bridge' ), 'url', true ),
			'shop_url'                => array( __( 'Shop URL', 'kirki-woocommerce-bridge' ), 'url', true ),
			'account_url'             => array( __( 'My account URL', 'kirki-woocommerce-bridge' ), 'url', true ),
			'currency'                => array( __( 'Currency code', 'kirki-woocommerce-bridge' ), 'text' ),
			'currency_symbol'         => array( __( 'Currency symbol', 'kirki-woocommerce-bridge' ), 'text' ),
			'customer_name'           => array( __( 'Customer first name', 'kirki-woocommerce-bridge' ), 'text' ),
			'free_shipping_remaining' => array( __( 'Amount left for free shipping', 'kirki-woocommerce-bridge' ), 'html' ),
		);
		$out    = array();
		foreach ( $fields as $key => $def ) {
			$out[ $key ] = array(
				'label'  => $def[0],
				'format' => $def[1],
				'anchor' => ! empty( $def[2] ),
			);
		}
		/**
		 * Filters store dynamic fields. Add resolvers with `kirki_woo_dynamic_store_{key}`.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'kirki_woo_dynamic_store_fields', $out );
	}

	/**
	 * Discount percentage for a product (max across variations).
	 *
	 * @param \WC_Product $product Product.
	 * @return int
	 */
	public static function discount_percentage( $product ) {
		if ( ! $product->is_on_sale() ) {
			return 0;
		}
		$best = 0;
		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			foreach ( $prices['regular_price'] as $id => $regular ) {
				$sale = isset( $prices['sale_price'][ $id ] ) ? (float) $prices['sale_price'][ $id ] : (float) $regular;
				if ( (float) $regular > 0 && $sale < (float) $regular ) {
					$best = max( $best, (int) round( ( 1 - $sale / (float) $regular ) * 100 ) );
				}
			}
			return $best;
		}
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular > 0 && $sale < $regular ) {
			$best = (int) round( ( 1 - $sale / $regular ) * 100 );
		}
		return $best;
	}

	/**
	 * Resolve a product field.
	 *
	 * @param string      $field   Field key.
	 * @param \WC_Product $product Product.
	 * @param string      $meta    Extra arg (attribute slug or meta key).
	 * @return string
	 */
	public static function product_value( $field, $product, $meta = '' ) {
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}
		switch ( $field ) {
			case 'id':
				return (string) $product->get_id();
			case 'title':
				return esc_html( $product->get_name() );
			case 'permalink':
				return esc_url( $product->get_permalink() );
			case 'price_html':
				return wp_kses_post( $product->get_price_html() );
			case 'price':
				return '' === $product->get_price() ? '' : wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) );
			case 'regular_price':
				$regular = $product->is_type( 'variable' ) ? $product->get_variation_regular_price( 'min', true ) : wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
				return '' === (string) $regular ? '' : wp_strip_all_tags( wc_price( $regular ) );
			case 'sale_price':
				if ( ! $product->is_on_sale() ) {
					return '';
				}
				$sale = $product->is_type( 'variable' ) ? $product->get_variation_sale_price( 'min', true ) : wc_get_price_to_display( $product, array( 'price' => $product->get_sale_price() ) );
				return wp_strip_all_tags( wc_price( $sale ) );
			case 'price_raw':
				return (string) wc_get_price_to_display( $product );
			case 'discount_percentage':
				return (string) self::discount_percentage( $product );
			case 'sale_ends':
				$to = $product->get_date_on_sale_to();
				return $to ? esc_html( wc_format_datetime( $to ) ) : '';
			case 'sku':
				return esc_html( $product->get_sku() );
			case 'stock_status':
				$options = wc_get_product_stock_status_options();
				$status  = $product->get_stock_status();
				return esc_html( isset( $options[ $status ] ) ? $options[ $status ] : $status );
			case 'stock_quantity':
				return null === $product->get_stock_quantity() ? '' : (string) (int) $product->get_stock_quantity();
			case 'availability':
				$availability = $product->get_availability();
				return esc_html( $availability['availability'] );
			case 'average_rating':
				return (string) $product->get_average_rating();
			case 'rating_count':
				return (string) $product->get_rating_count();
			case 'review_count':
				return (string) $product->get_review_count();
			case 'rating_html':
				return wc_get_rating_html( $product->get_average_rating(), $product->get_rating_count() );
			case 'short_description':
				return wp_kses_post( wpautop( $product->get_short_description() ) );
			case 'description':
				return wp_kses_post( wpautop( $product->get_description() ) );
			case 'type':
				$types = wc_get_product_types();
				$type  = $product->get_type();
				return esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type );
			case 'categories':
				return esc_html( wp_strip_all_tags( wc_get_product_category_list( $product->get_id(), ', ' ) ) );
			case 'tags':
				return esc_html( wp_strip_all_tags( wc_get_product_tag_list( $product->get_id(), ', ' ) ) );
			case 'attribute':
				if ( '' === $meta ) {
					return '';
				}
				$attr = $product->get_attribute( $meta );
				if ( '' === $attr && 0 !== strpos( $meta, 'pa_' ) ) {
					$attr = $product->get_attribute( 'pa_' . $meta );
				}
				return esc_html( $attr );
			case 'weight':
				return $product->has_weight() ? esc_html( wc_format_weight( $product->get_weight() ) ) : '';
			case 'dimensions':
				return $product->has_dimensions() ? esc_html( wc_format_dimensions( $product->get_dimensions( false ) ) ) : '';
			case 'total_sales':
				return (string) (int) $product->get_total_sales();
			case 'on_sale':
				return $product->is_on_sale() ? esc_html__( 'Sale!', 'kirki-woocommerce-bridge' ) : '';
			case 'featured':
				return $product->is_featured() ? esc_html__( 'Yes', 'kirki-woocommerce-bridge' ) : esc_html__( 'No', 'kirki-woocommerce-bridge' );
			case 'image_url':
				$id = $product->get_image_id();
				return $id ? esc_url( (string) wp_get_attachment_image_url( $id, 'woocommerce_single' ) ) : esc_url( wc_placeholder_img_src( 'woocommerce_single' ) );
			case 'add_to_cart_url':
				return esc_url( $product->add_to_cart_url() );
			case 'add_to_cart_text':
				return esc_html( $product->add_to_cart_text() );
			case 'meta':
				if ( '' === $meta || is_protected_meta( $meta, 'post' ) ) {
					return '';
				}
				$value = $product->get_meta( $meta, true );
				return is_scalar( $value ) ? esc_html( (string) $value ) : '';
		}
		/**
		 * Resolve a custom product field.
		 *
		 * @since 1.0.0
		 */
		return (string) apply_filters( 'kirki_woo_dynamic_product_' . $field, '', $product, $meta );
	}

	/**
	 * Resolve a store field.
	 *
	 * @param string $field Field.
	 * @return string
	 */
	public static function store_value( $field ) {
		$cart = function_exists( 'WC' ) && WC()->cart ? WC()->cart : null;
		switch ( $field ) {
			case 'cart_count':
				return (string) ( $cart ? $cart->get_cart_contents_count() : 0 );
			case 'cart_subtotal':
				return $cart ? wp_kses_post( $cart->get_cart_subtotal() ) : '';
			case 'cart_total':
				return $cart ? wp_kses_post( $cart->get_total() ) : '';
			case 'cart_url':
				return esc_url( wc_get_cart_url() );
			case 'checkout_url':
				return esc_url( wc_get_checkout_url() );
			case 'shop_url':
				return esc_url( wc_get_page_permalink( 'shop' ) );
			case 'account_url':
				return esc_url( wc_get_page_permalink( 'myaccount' ) );
			case 'currency':
				return esc_html( get_woocommerce_currency() );
			case 'currency_symbol':
				return esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) );
			case 'customer_name':
				$user = wp_get_current_user();
				return $user->exists() ? esc_html( $user->first_name ? $user->first_name : $user->display_name ) : '';
			case 'free_shipping_remaining':
				return self::free_shipping_remaining();
		}
		/**
		 * Resolve a custom store field.
		 *
		 * @since 1.0.0
		 */
		return (string) apply_filters( 'kirki_woo_dynamic_store_' . $field, '' );
	}

	/**
	 * Amount left until a free shipping method with a minimum amount applies.
	 *
	 * @return string
	 */
	private static function free_shipping_remaining() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		$min = null;
		foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( 'free_shipping' === $method->id && 'yes' === $method->enabled && in_array( $method->requires, array( 'min_amount', 'either' ), true ) && (float) $method->min_amount > 0 ) {
					$min = null === $min ? (float) $method->min_amount : min( $min, (float) $method->min_amount );
				}
			}
		}
		if ( null === $min ) {
			return '';
		}
		$left = $min - (float) WC()->cart->get_displayed_subtotal();
		return $left > 0 ? wp_kses_post( wc_price( $left ) ) : '';
	}

	/**
	 * Kirki `kirki_dynamic_content` filter.
	 *
	 * @param mixed               $value Current value (false when unresolved).
	 * @param array<string,mixed> $info  Content info from Kirki.
	 * @return mixed
	 */
	public static function kirki_dynamic_content( $value, $info ) {
		if ( false !== $value || ! is_array( $info ) || empty( $info['dynamicContent']['type'] ) ) {
			return $value;
		}
		$dc    = $info['dynamicContent'];
		$type  = $dc['type'];
		$field = isset( $dc['value'] ) ? sanitize_key( $dc['value'] ) : '';
		$meta  = isset( $dc['meta'] ) ? sanitize_text_field( $dc['meta'] ) : '';

		if ( self::TYPE_STORE === $type ) {
			return self::store_value( $field );
		}
		if ( self::TYPE_PRODUCT !== $type ) {
			return $value;
		}

		$options = isset( $info['options'] ) && is_array( $info['options'] ) ? $info['options'] : array();
		$product = null;
		if ( isset( $info['collectionItem']['ID'] ) ) {
			$product = wc_get_product( (int) $info['collectionItem']['ID'] );
		}
		if ( ! $product && isset( $info['post_id'] ) ) {
			$maybe = wc_get_product( (int) $info['post_id'] );
			if ( $maybe ) {
				$product = $maybe;
			}
		}
		if ( ! $product ) {
			$product = Context::product( 0, $options );
		}
		if ( ! $product || ! Context::visible( $product ) ) {
			return '';
		}
		$out = self::product_value( $field, $product, $meta );
		// Kirki treats a falsy return as "not handled"; keep an empty string visible as such.
		return '' === $out ? ' ' : $out;
	}

	/**
	 * Add our types to the Kirki dynamic content picker.
	 *
	 * @param array<string,mixed> $fields          Fields.
	 * @param mixed               $collection_data Collection context from the editor.
	 * @return array<string,mixed>
	 */
	public static function kirki_dynamic_content_fields( $fields, $collection_data = null ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}
		$product_content = array();
		$product_anchor  = array();
		foreach ( self::product_fields() as $key => $def ) {
			$product_content[] = array(
				'value' => $key,
				'title' => $def['label'],
			);
			if ( $def['anchor'] ) {
				$product_anchor[] = array(
					'value' => $key,
					'title' => $def['label'],
				);
			}
		}
		$store_content = array();
		$store_anchor  = array();
		foreach ( self::store_fields() as $key => $def ) {
			$store_content[] = array(
				'value' => $key,
				'title' => $def['label'],
			);
			if ( $def['anchor'] ) {
				$store_anchor[] = array(
					'value' => $key,
					'title' => $def['label'],
				);
			}
		}

		$groups = array(
			'content' => array( $product_content, $store_content ),
			'anchor'  => array( $product_anchor, $store_anchor ),
		);
		foreach ( $groups as $element_type => $lists ) {
			if ( ! isset( $fields['typeValues'][ $element_type ] ) || ! is_array( $fields['typeValues'][ $element_type ] ) ) {
				continue;
			}
			$fields['typeValues'][ $element_type ][]                         = array(
				'value' => self::TYPE_PRODUCT,
				'title' => __( 'Product (WooCommerce)', 'kirki-woocommerce-bridge' ),
			);
			$fields['typeValues'][ $element_type ][]                         = array(
				'value' => self::TYPE_STORE,
				'title' => __( 'Store (WooCommerce)', 'kirki-woocommerce-bridge' ),
			);
			$fields['typeValuesAttr'][ $element_type ][ self::TYPE_PRODUCT ] = $lists[0];
			$fields['typeValuesAttr'][ $element_type ][ self::TYPE_STORE ]   = $lists[1];
		}
		return $fields;
	}

	/**
	 * [kirki_woo field="price_html" product_id="0" meta=""] shortcode.
	 *
	 * Fields from either list are accepted; store fields take precedence.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts  = shortcode_atts(
			array(
				'field'      => 'title',
				'product_id' => 0,
				'meta'       => '',
			),
			$atts,
			'kirki_woo'
		);
		$field = sanitize_key( $atts['field'] );
		if ( isset( self::store_fields()[ $field ] ) ) {
			return self::store_value( $field );
		}
		if ( ! isset( self::product_fields()[ $field ] ) ) {
			return '';
		}
		$product = Context::product( absint( $atts['product_id'] ) );
		return $product ? self::product_value( $field, $product, sanitize_text_field( $atts['meta'] ) ) : '';
	}
}
