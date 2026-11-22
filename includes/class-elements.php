<?php
/**
 * Element registry: the single source of truth for every Kirki element this
 * plugin adds. The editor script, the renderer, the REST API and the Abilities
 * all read this schema, so it is versioned and stable.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Element definitions and settings sanitisation.
 */
final class Elements {

	/**
	 * Schema version. Bump on breaking changes to element names or settings.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Prefix for element names inside Kirki data.
	 */
	const PREFIX = 'kirki-woo-';

	/**
	 * Cached definitions.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private static $cache = null;

	/**
	 * Groups shown as sub categories in the Kirki element panel.
	 *
	 * @return array<string,string>
	 */
	public static function groups() {
		return array(
			'product' => __( 'Single product', 'kirki-woocommerce-bridge' ),
			'shop'    => __( 'Shop & archives', 'kirki-woocommerce-bridge' ),
			'cart'    => __( 'Cart & checkout', 'kirki-woocommerce-bridge' ),
			'account' => __( 'Account', 'kirki-woocommerce-bridge' ),
			'general' => __( 'General', 'kirki-woocommerce-bridge' ),
		);
	}

	/**
	 * Select field.
	 *
	 * @param string               $label   Label.
	 * @param array<string,string> $options value => label.
	 * @param string               $default_value Default.
	 * @return array<string,mixed>
	 */
	private static function select( $label, array $options, $default_value ) {
		return array(
			'type'    => 'select',
			'label'   => $label,
			'options' => $options,
			'default' => $default_value,
		);
	}

	/**
	 * Toggle field.
	 *
	 * @param string $label Label.
	 * @param bool   $default_value Default.
	 * @return array<string,mixed>
	 */
	private static function toggle( $label, $default_value ) {
		return array(
			'type'    => 'toggle',
			'label'   => $label,
			'default' => (bool) $default_value,
		);
	}

	/**
	 * Text field.
	 *
	 * @param string $label Label.
	 * @param string $default_value Default.
	 * @return array<string,mixed>
	 */
	private static function text( $label, $default_value = '' ) {
		return array(
			'type'    => 'text',
			'label'   => $label,
			'default' => (string) $default_value,
		);
	}

	/**
	 * Number field.
	 *
	 * @param string $label Label.
	 * @param int    $default_value Default.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maximum.
	 * @return array<string,mixed>
	 */
	private static function number( $label, $default_value, $min, $max ) {
		return array(
			'type'    => 'number',
			'label'   => $label,
			'default' => (int) $default_value,
			'min'     => (int) $min,
			'max'     => (int) $max,
		);
	}

	/**
	 * Product picker field (0 means "the current product").
	 *
	 * @return array<string,mixed>
	 */
	private static function product_field() {
		return array(
			'type'    => 'product',
			'label'   => __( 'Product ID (0 = current product)', 'kirki-woocommerce-bridge' ),
			'default' => 0,
			'min'     => 0,
			'max'     => PHP_INT_MAX,
		);
	}

	/**
	 * Every element definition keyed by slug (without prefix).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$heading_tags = array(
			'h1'   => 'H1',
			'h2'   => 'H2',
			'h3'   => 'H3',
			'h4'   => 'H4',
			'h5'   => 'H5',
			'h6'   => 'H6',
			'p'    => 'P',
			'span' => 'Span',
			'div'  => 'Div',
		);
		$image_sizes  = array(
			'woocommerce_single'    => __( 'WooCommerce single', 'kirki-woocommerce-bridge' ),
			'woocommerce_thumbnail' => __( 'WooCommerce thumbnail', 'kirki-woocommerce-bridge' ),
			'large'                 => __( 'Large', 'kirki-woocommerce-bridge' ),
			'medium'                => __( 'Medium', 'kirki-woocommerce-bridge' ),
			'full'                  => __( 'Full', 'kirki-woocommerce-bridge' ),
		);
		$orderby      = array(
			'date'       => __( 'Newest', 'kirki-woocommerce-bridge' ),
			'title'      => __( 'Title', 'kirki-woocommerce-bridge' ),
			'price'      => __( 'Price', 'kirki-woocommerce-bridge' ),
			'popularity' => __( 'Popularity (sales)', 'kirki-woocommerce-bridge' ),
			'rating'     => __( 'Average rating', 'kirki-woocommerce-bridge' ),
			'menu_order' => __( 'Menu order', 'kirki-woocommerce-bridge' ),
			'rand'       => __( 'Random', 'kirki-woocommerce-bridge' ),
			'id'         => __( 'ID', 'kirki-woocommerce-bridge' ),
		);
		$order        = array(
			'DESC' => __( 'Descending', 'kirki-woocommerce-bridge' ),
			'ASC'  => __( 'Ascending', 'kirki-woocommerce-bridge' ),
		);
		$mode         = array(
			'classic' => __( 'Classic (shortcode, full hook support)', 'kirki-woocommerce-bridge' ),
			'block'   => __( 'WooCommerce block', 'kirki-woocommerce-bridge' ),
		);

		$defs = array(
			// Single product.
			'product-title'             => array(
				'title'       => __( 'Product Title', 'kirki-woocommerce-bridge' ),
				'description' => __( 'The product name.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
					'html_tag'   => self::select( __( 'HTML tag', 'kirki-woocommerce-bridge' ), $heading_tags, 'h1' ),
					'link'       => self::toggle( __( 'Link to product', 'kirki-woocommerce-bridge' ), false ),
				),
			),
			'product-price'             => array(
				'title'       => __( 'Product Price', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Price HTML including sale and variation ranges.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
				),
			),
			'product-image'             => array(
				'title'       => __( 'Product Image', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Featured image of the product.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
					'size'       => self::select( __( 'Image size', 'kirki-woocommerce-bridge' ), $image_sizes, 'woocommerce_single' ),
					'link'       => self::select(
						__( 'Link', 'kirki-woocommerce-bridge' ),
						array(
							'none'    => __( 'None', 'kirki-woocommerce-bridge' ),
							'product' => __( 'Product page', 'kirki-woocommerce-bridge' ),
							'file'    => __( 'Image file', 'kirki-woocommerce-bridge' ),
						),
						'none'
					),
					'sale_badge' => self::toggle( __( 'Show sale badge', 'kirki-woocommerce-bridge' ), false ),
				),
			),
			'product-gallery'           => array(
				'title'       => __( 'Product Gallery', 'kirki-woocommerce-bridge' ),
				'description' => __( 'WooCommerce gallery with zoom, lightbox and slider, switching with variations.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'assets'      => array( 'gallery' ),
				'settings'    => array(
					'product_id' => self::product_field(),
					'sale_badge' => self::toggle( __( 'Show sale badge', 'kirki-woocommerce-bridge' ), true ),
				),
			),
			'product-add-to-cart'       => array(
				'title'       => __( 'Add to Cart', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Add to cart form for simple, variable, grouped and external products.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'assets'      => array( 'add_to_cart' ),
				'settings'    => array(
					'product_id'    => self::product_field(),
					'style'         => self::select(
						__( 'Style', 'kirki-woocommerce-bridge' ),
						array(
							'form'   => __( 'Full form (variations, quantity)', 'kirki-woocommerce-bridge' ),
							'button' => __( 'Button only (archive style, AJAX)', 'kirki-woocommerce-bridge' ),
						),
						'form'
					),
					'show_quantity' => self::toggle( __( 'Show quantity field', 'kirki-woocommerce-bridge' ), true ),
					'button_text'   => self::text( __( 'Button text (blank = WooCommerce default)', 'kirki-woocommerce-bridge' ) ),
				),
			),
			'product-stock'             => array(
				'title'       => __( 'Stock Status', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Availability text such as "In stock" or "Out of stock".', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id'    => self::product_field(),
					'show_in_stock' => self::toggle( __( 'Show when in stock', 'kirki-woocommerce-bridge' ), true ),
				),
			),
			'product-rating'            => array(
				'title'       => __( 'Product Rating', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Star rating and review count.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id'    => self::product_field(),
					'show_count'    => self::toggle( __( 'Show review count', 'kirki-woocommerce-bridge' ), true ),
					'hide_if_empty' => self::toggle( __( 'Hide when there are no reviews', 'kirki-woocommerce-bridge' ), true ),
				),
			),
			'product-short-description' => array(
				'title'       => __( 'Short Description', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Product short description (excerpt).', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
				),
			),
			'product-description'       => array(
				'title'       => __( 'Description', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Full product description.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
				),
			),
			'product-meta'              => array(
				'title'       => __( 'Product Meta', 'kirki-woocommerce-bridge' ),
				'description' => __( 'SKU, categories and tags.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id'      => self::product_field(),
					'show_sku'        => self::toggle( __( 'Show SKU', 'kirki-woocommerce-bridge' ), true ),
					'show_categories' => self::toggle( __( 'Show categories', 'kirki-woocommerce-bridge' ), true ),
					'show_tags'       => self::toggle( __( 'Show tags', 'kirki-woocommerce-bridge' ), true ),
				),
			),
			'product-sku'               => array(
				'title'       => __( 'SKU', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Product SKU.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
					'label'      => self::text( __( 'Label', 'kirki-woocommerce-bridge' ), 'SKU:' ),
				),
			),
			'product-terms'             => array(
				'title'       => __( 'Product Categories / Tags', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Linked list of the product categories, tags or brands.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
					'taxonomy'   => self::select(
						__( 'Taxonomy', 'kirki-woocommerce-bridge' ),
						array(
							'product_cat'   => __( 'Categories', 'kirki-woocommerce-bridge' ),
							'product_tag'   => __( 'Tags', 'kirki-woocommerce-bridge' ),
							'product_brand' => __( 'Brands', 'kirki-woocommerce-bridge' ),
						),
						'product_cat'
					),
					'separator'  => self::text( __( 'Separator', 'kirki-woocommerce-bridge' ), ', ' ),
					'link'       => self::toggle( __( 'Link terms', 'kirki-woocommerce-bridge' ), true ),
				),
			),
			'product-tabs'              => array(
				'title'       => __( 'Product Tabs', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Description, additional information and reviews tabs.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'assets'      => array( 'single' ),
				'settings'    => array(
					'product_id' => self::product_field(),
				),
			),
			'product-attributes'        => array(
				'title'       => __( 'Additional Information', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Attributes, weight and dimensions table.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
				),
			),
			'product-reviews'           => array(
				'title'       => __( 'Reviews', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Review list and review form.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'assets'      => array( 'single' ),
				'settings'    => array(
					'product_id' => self::product_field(),
				),
			),
			'product-related'           => array(
				'title'       => __( 'Related Products', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Products related by category and tag.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
					'limit'      => self::number( __( 'Products', 'kirki-woocommerce-bridge' ), 4, 1, 24 ),
					'columns'    => self::number( __( 'Columns', 'kirki-woocommerce-bridge' ), 4, 1, 6 ),
					'heading'    => self::text( __( 'Heading (blank = default)', 'kirki-woocommerce-bridge' ) ),
				),
			),
			'product-upsells'           => array(
				'title'       => __( 'Upsells', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Upsell products linked to this product.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id' => self::product_field(),
					'limit'      => self::number( __( 'Products', 'kirki-woocommerce-bridge' ), 4, 1, 24 ),
					'columns'    => self::number( __( 'Columns', 'kirki-woocommerce-bridge' ), 4, 1, 6 ),
					'heading'    => self::text( __( 'Heading (blank = default)', 'kirki-woocommerce-bridge' ) ),
				),
			),
			'product-sale-badge'        => array(
				'title'       => __( 'Sale Badge', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Shown only when the product is on sale.', 'kirki-woocommerce-bridge' ),
				'group'       => 'product',
				'context'     => 'product',
				'tag'         => 'div',
				'settings'    => array(
					'product_id'      => self::product_field(),
					'text'            => self::text( __( 'Text (blank = "Sale!")', 'kirki-woocommerce-bridge' ) ),
					'show_percentage' => self::toggle( __( 'Show discount percentage', 'kirki-woocommerce-bridge' ), false ),
				),
			),

			// Shop and archives.
			'products'                  => array(
				'title'       => __( 'Products Grid', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Product loop for shops, archives and landing pages.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'none',
				'tag'         => 'div',
				'assets'      => array( 'loop' ),
				'settings'    => array(
					'source'          => self::select(
						__( 'Source', 'kirki-woocommerce-bridge' ),
						array(
							'current_query' => __( 'Current query (shop / archive / search)', 'kirki-woocommerce-bridge' ),
							'recent'        => __( 'Recent products', 'kirki-woocommerce-bridge' ),
							'featured'      => __( 'Featured products', 'kirki-woocommerce-bridge' ),
							'on_sale'       => __( 'On sale', 'kirki-woocommerce-bridge' ),
							'best_selling'  => __( 'Best selling', 'kirki-woocommerce-bridge' ),
							'top_rated'     => __( 'Top rated', 'kirki-woocommerce-bridge' ),
							'ids'           => __( 'Specific products', 'kirki-woocommerce-bridge' ),
							'related'       => __( 'Related to current product', 'kirki-woocommerce-bridge' ),
							'upsells'       => __( 'Upsells of current product', 'kirki-woocommerce-bridge' ),
							'cross_sells'   => __( 'Cross-sells of the cart', 'kirki-woocommerce-bridge' ),
						),
						'recent'
					),
					'limit'           => self::number( __( 'Products per page', 'kirki-woocommerce-bridge' ), 8, 1, 100 ),
					'columns'         => self::number( __( 'Columns (desktop)', 'kirki-woocommerce-bridge' ), 4, 1, 6 ),
					'columns_tablet'  => self::number( __( 'Columns (tablet)', 'kirki-woocommerce-bridge' ), 2, 1, 6 ),
					'columns_mobile'  => self::number( __( 'Columns (mobile)', 'kirki-woocommerce-bridge' ), 1, 1, 4 ),
					'orderby'         => self::select( __( 'Order by', 'kirki-woocommerce-bridge' ), $orderby, 'date' ),
					'order'           => self::select( __( 'Order', 'kirki-woocommerce-bridge' ), $order, 'DESC' ),
					'category'        => self::text( __( 'Category slugs (comma separated)', 'kirki-woocommerce-bridge' ) ),
					'tag'             => self::text( __( 'Tag slugs (comma separated)', 'kirki-woocommerce-bridge' ) ),
					'ids'             => self::text( __( 'Product IDs (comma separated)', 'kirki-woocommerce-bridge' ) ),
					'paginate'        => self::toggle( __( 'Paginate', 'kirki-woocommerce-bridge' ), false ),
					'layout'          => self::select(
						__( 'Card layout', 'kirki-woocommerce-bridge' ),
						array(
							'card'        => __( 'Kirki card (clean markup)', 'kirki-woocommerce-bridge' ),
							'woocommerce' => __( 'WooCommerce loop template (theme & extension hooks)', 'kirki-woocommerce-bridge' ),
						),
						'card'
					),
					'show_image'      => self::toggle( __( 'Image', 'kirki-woocommerce-bridge' ), true ),
					'show_title'      => self::toggle( __( 'Title', 'kirki-woocommerce-bridge' ), true ),
					'show_rating'     => self::toggle( __( 'Rating', 'kirki-woocommerce-bridge' ), true ),
					'show_price'      => self::toggle( __( 'Price', 'kirki-woocommerce-bridge' ), true ),
					'show_button'     => self::toggle( __( 'Add to cart button', 'kirki-woocommerce-bridge' ), true ),
					'show_sale_badge' => self::toggle( __( 'Sale badge', 'kirki-woocommerce-bridge' ), true ),
					'title_tag'       => self::select( __( 'Card title tag', 'kirki-woocommerce-bridge' ), $heading_tags, 'h3' ),
					'no_results'      => self::text( __( 'Text when nothing is found', 'kirki-woocommerce-bridge' ) ),
				),
			),
			'shop-result-count'         => array(
				'title'       => __( 'Result Count', 'kirki-woocommerce-bridge' ),
				'description' => __( '"Showing 1–12 of 40 results".', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'archive',
				'tag'         => 'div',
				'settings'    => array(),
			),
			'shop-ordering'             => array(
				'title'       => __( 'Sorting', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Catalog ordering dropdown.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'archive',
				'tag'         => 'div',
				'settings'    => array(),
			),
			'shop-pagination'           => array(
				'title'       => __( 'Pagination', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Pagination for the current shop query.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'archive',
				'tag'         => 'div',
				'settings'    => array(),
			),
			'shop-filter'               => array(
				'title'       => __( 'Product Filter', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Filter the shop by price, category, attribute, rating, stock or sale.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'archive',
				'tag'         => 'div',
				'assets'      => array( 'filters' ),
				'settings'    => array(
					'filter_type' => self::select(
						__( 'Filter', 'kirki-woocommerce-bridge' ),
						array(
							'price'     => __( 'Price range', 'kirki-woocommerce-bridge' ),
							'category'  => __( 'Categories', 'kirki-woocommerce-bridge' ),
							'attribute' => __( 'Attribute', 'kirki-woocommerce-bridge' ),
							'rating'    => __( 'Rating', 'kirki-woocommerce-bridge' ),
							'stock'     => __( 'Stock status', 'kirki-woocommerce-bridge' ),
							'on_sale'   => __( 'On sale', 'kirki-woocommerce-bridge' ),
							'active'    => __( 'Active filters & reset', 'kirki-woocommerce-bridge' ),
						),
						'price'
					),
					'attribute'   => self::text( __( 'Attribute slug, e.g. pa_color', 'kirki-woocommerce-bridge' ) ),
					'title'       => self::text( __( 'Title', 'kirki-woocommerce-bridge' ) ),
					'show_count'  => self::toggle( __( 'Show counts', 'kirki-woocommerce-bridge' ), true ),
					'auto_submit' => self::toggle( __( 'Apply on change', 'kirki-woocommerce-bridge' ), true ),
				),
			),
			'product-search'            => array(
				'title'       => __( 'Product Search', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Search form limited to products.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'none',
				'tag'         => 'div',
				'settings'    => array(
					'placeholder' => self::text( __( 'Placeholder', 'kirki-woocommerce-bridge' ), __( 'Search products…', 'kirki-woocommerce-bridge' ) ),
					'button_text' => self::text( __( 'Button text', 'kirki-woocommerce-bridge' ), __( 'Search', 'kirki-woocommerce-bridge' ) ),
				),
			),
			'product-categories'        => array(
				'title'       => __( 'Category Grid', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Grid of product categories with images.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'none',
				'tag'         => 'div',
				'settings'    => array(
					'parent'     => self::number( __( 'Parent category ID (0 = top level, -1 = all)', 'kirki-woocommerce-bridge' ), 0, -1, PHP_INT_MAX ),
					'limit'      => self::number( __( 'Categories', 'kirki-woocommerce-bridge' ), 6, 1, 100 ),
					'columns'    => self::number( __( 'Columns', 'kirki-woocommerce-bridge' ), 3, 1, 6 ),
					'hide_empty' => self::toggle( __( 'Hide empty', 'kirki-woocommerce-bridge' ), true ),
					'show_count' => self::toggle( __( 'Show product count', 'kirki-woocommerce-bridge' ), true ),
					'show_image' => self::toggle( __( 'Show image', 'kirki-woocommerce-bridge' ), true ),
				),
			),
			'archive-title'             => array(
				'title'       => __( 'Archive Title', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Shop, category, tag or search title.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'archive',
				'tag'         => 'div',
				'settings'    => array(
					'html_tag' => self::select( __( 'HTML tag', 'kirki-woocommerce-bridge' ), $heading_tags, 'h1' ),
				),
			),
			'archive-description'       => array(
				'title'       => __( 'Archive Description', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Shop page content or term description.', 'kirki-woocommerce-bridge' ),
				'group'       => 'shop',
				'context'     => 'archive',
				'tag'         => 'div',
				'settings'    => array(),
			),
			'breadcrumb'                => array(
				'title'       => __( 'Store Breadcrumb', 'kirki-woocommerce-bridge' ),
				'description' => __( 'WooCommerce breadcrumb trail.', 'kirki-woocommerce-bridge' ),
				'group'       => 'general',
				'context'     => 'none',
				'tag'         => 'div',
				'settings'    => array(
					'delimiter' => self::text( __( 'Delimiter', 'kirki-woocommerce-bridge' ), ' / ' ),
					'home'      => self::text( __( 'Home label', 'kirki-woocommerce-bridge' ), __( 'Home', 'kirki-woocommerce-bridge' ) ),
				),
			),
			'store-notices'             => array(
				'title'       => __( 'Store Notices', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Success, error and info notices such as "Added to cart".', 'kirki-woocommerce-bridge' ),
				'group'       => 'general',
				'context'     => 'none',
				'tag'         => 'div',
				'settings'    => array(),
			),

			// Cart and checkout.
			'mini-cart'                 => array(
				'title'       => __( 'Mini Cart', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Cart icon with live count, total and a drawer.', 'kirki-woocommerce-bridge' ),
				'group'       => 'cart',
				'context'     => 'none',
				'tag'         => 'div',
				'assets'      => array( 'mini_cart' ),
				'settings'    => array(
					'show_icon'  => self::toggle( __( 'Icon', 'kirki-woocommerce-bridge' ), true ),
					'show_count' => self::toggle( __( 'Item count', 'kirki-woocommerce-bridge' ), true ),
					'show_total' => self::toggle( __( 'Total', 'kirki-woocommerce-bridge' ), true ),
					'behavior'   => self::select(
						__( 'On click', 'kirki-woocommerce-bridge' ),
						array(
							'drawer' => __( 'Open drawer', 'kirki-woocommerce-bridge' ),
							'cart'   => __( 'Go to cart', 'kirki-woocommerce-bridge' ),
						),
						'drawer'
					),
				),
			),
			'cart'                      => array(
				'title'       => __( 'Cart', 'kirki-woocommerce-bridge' ),
				'description' => __( 'The full cart: items, coupons, totals and proceed to checkout.', 'kirki-woocommerce-bridge' ),
				'group'       => 'cart',
				'context'     => 'cart',
				'tag'         => 'div',
				'assets'      => array( 'cart' ),
				'settings'    => array(
					'mode' => self::select( __( 'Render as', 'kirki-woocommerce-bridge' ), $mode, 'classic' ),
				),
			),
			'cart-totals'               => array(
				'title'       => __( 'Cart Totals', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Subtotal, shipping, taxes and total with the checkout button.', 'kirki-woocommerce-bridge' ),
				'group'       => 'cart',
				'context'     => 'cart',
				'tag'         => 'div',
				'assets'      => array( 'cart' ),
				'settings'    => array(),
			),
			'cart-cross-sells'          => array(
				'title'       => __( 'Cross-sells', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Cross-sell products for items in the cart.', 'kirki-woocommerce-bridge' ),
				'group'       => 'cart',
				'context'     => 'cart',
				'tag'         => 'div',
				'settings'    => array(
					'limit'   => self::number( __( 'Products', 'kirki-woocommerce-bridge' ), 2, 1, 12 ),
					'columns' => self::number( __( 'Columns', 'kirki-woocommerce-bridge' ), 2, 1, 6 ),
				),
			),
			'checkout'                  => array(
				'title'       => __( 'Checkout', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Checkout form, payment methods and the order-received page.', 'kirki-woocommerce-bridge' ),
				'group'       => 'cart',
				'context'     => 'checkout',
				'tag'         => 'div',
				'assets'      => array( 'checkout' ),
				'settings'    => array(
					'mode' => self::select( __( 'Render as', 'kirki-woocommerce-bridge' ), $mode, 'classic' ),
				),
			),
			'my-account'                => array(
				'title'       => __( 'My Account', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Login / register, dashboard, orders, addresses and account details.', 'kirki-woocommerce-bridge' ),
				'group'       => 'account',
				'context'     => 'account',
				'tag'         => 'div',
				'settings'    => array(),
			),
			'order-tracking'            => array(
				'title'       => __( 'Order Tracking', 'kirki-woocommerce-bridge' ),
				'description' => __( 'Order tracking form.', 'kirki-woocommerce-bridge' ),
				'group'       => 'account',
				'context'     => 'none',
				'tag'         => 'div',
				'settings'    => array(),
			),
		);

		/**
		 * Filters the element definitions. Third parties may add elements; they
		 * must also hook `kirki_woo_render_element_{slug}` to render them.
		 *
		 * @since 1.0.0
		 *
		 * @param array $defs Definitions keyed by slug.
		 */
		$defs = apply_filters( 'kirki_woo_elements', $defs );

		foreach ( $defs as $slug => &$def ) {
			$def['slug']     = $slug;
			$def['name']     = self::PREFIX . $slug;
			$def['settings'] = isset( $def['settings'] ) && is_array( $def['settings'] ) ? $def['settings'] : array();
			$def['assets']   = isset( $def['assets'] ) ? (array) $def['assets'] : array();
			$def['context']  = isset( $def['context'] ) ? $def['context'] : 'none';
			$def['tag']      = isset( $def['tag'] ) ? $def['tag'] : 'div';
			$def['group']    = isset( $def['group'] ) ? $def['group'] : 'general';
		}
		unset( $def );

		self::$cache = $defs;
		return $defs;
	}

	/**
	 * Clear the cache (tests, locale switches).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Look up one element by slug or full Kirki name.
	 *
	 * @param string $name Slug ("product-title") or name ("kirki-woo-product-title").
	 * @return array<string,mixed>|null
	 */
	public static function get( $name ) {
		$slug = self::slug( $name );
		$all  = self::all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * Strip the prefix from a name.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public static function slug( $name ) {
		$name = (string) $name;
		if ( 0 === strpos( $name, self::PREFIX ) ) {
			return substr( $name, strlen( self::PREFIX ) );
		}
		return $name;
	}

	/**
	 * Default settings for an element.
	 *
	 * @param string $name Element.
	 * @return array<string,mixed>
	 */
	public static function defaults( $name ) {
		$def = self::get( $name );
		$out = array();
		if ( ! $def ) {
			return $out;
		}
		foreach ( $def['settings'] as $key => $field ) {
			$out[ $key ] = $field['default'];
		}
		return $out;
	}

	/**
	 * Sanitise raw settings against the element schema. Unknown keys are dropped,
	 * missing keys get defaults, values are coerced and clamped.
	 *
	 * @param string $name Element.
	 * @param mixed  $raw  Raw settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $name, $raw ) {
		$def = self::get( $name );
		if ( ! $def ) {
			return array();
		}
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( $def['settings'] as $key => $field ) {
			$value = array_key_exists( $key, $raw ) ? $raw[ $key ] : $field['default'];
			switch ( $field['type'] ) {
				case 'toggle':
					if ( is_string( $value ) ) {
						$value = in_array( strtolower( $value ), array( '1', 'true', 'yes', 'on' ), true );
					}
					$value = (bool) $value;
					break;
				case 'number':
				case 'product':
					$value = is_numeric( $value ) ? (int) $value : (int) $field['default'];
					$value = max( (int) $field['min'], min( (int) $field['max'], $value ) );
					break;
				case 'select':
					$value = (string) $value;
					if ( ! array_key_exists( $value, $field['options'] ) ) {
						$value = (string) $field['default'];
					}
					break;
				default:
					$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : (string) $field['default'];
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Validate raw settings strictly (used by the API so callers get errors
	 * instead of silent coercion).
	 *
	 * @param string $name Element.
	 * @param mixed  $raw  Raw settings.
	 * @return true|\WP_Error
	 */
	public static function validate_settings( $name, $raw ) {
		$def = self::get( $name );
		if ( ! $def ) {
			return new \WP_Error( 'kirki_woo_unknown_element', __( 'Unknown element.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		if ( null === $raw ) {
			return true;
		}
		if ( ! is_array( $raw ) ) {
			return new \WP_Error( 'kirki_woo_invalid_settings', __( 'Settings must be an object.', 'kirki-woocommerce-bridge' ), array( 'status' => 400 ) );
		}
		$errors = array();
		foreach ( $raw as $key => $value ) {
			if ( ! isset( $def['settings'][ $key ] ) ) {
				$errors[] = sprintf( '%s: unknown setting', $key );
				continue;
			}
			$field = $def['settings'][ $key ];
			switch ( $field['type'] ) {
				case 'toggle':
					if ( ! is_bool( $value ) && ! in_array( $value, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) {
						$errors[] = sprintf( '%s: expected boolean', $key );
					}
					break;
				case 'number':
				case 'product':
					if ( ! is_numeric( $value ) || (int) $value < $field['min'] || (int) $value > $field['max'] ) {
						$errors[] = sprintf( '%s: expected integer between %d and %d', $key, $field['min'], $field['max'] );
					}
					break;
				case 'select':
					if ( ! is_scalar( $value ) || ! array_key_exists( (string) $value, $field['options'] ) ) {
						$errors[] = sprintf( '%s: expected one of %s', $key, implode( ', ', array_keys( $field['options'] ) ) );
					}
					break;
				default:
					if ( ! is_scalar( $value ) ) {
						$errors[] = sprintf( '%s: expected string', $key );
					}
			}
		}
		if ( $errors ) {
			return new \WP_Error( 'kirki_woo_invalid_settings', implode( '; ', $errors ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * JSON Schema for one element's settings (for REST and Abilities).
	 *
	 * @param string $name Element.
	 * @return array<string,mixed>
	 */
	public static function json_schema( $name ) {
		$def   = self::get( $name );
		$props = array();
		if ( $def ) {
			foreach ( $def['settings'] as $key => $field ) {
				switch ( $field['type'] ) {
					case 'toggle':
						$prop = array( 'type' => 'boolean' );
						break;
					case 'number':
					case 'product':
						$prop = array(
							'type'    => 'integer',
							'minimum' => $field['min'],
							'maximum' => $field['max'],
						);
						break;
					case 'select':
						$prop = array(
							'type' => 'string',
							'enum' => array_map( 'strval', array_keys( $field['options'] ) ),
						);
						break;
					default:
						$prop = array( 'type' => 'string' );
				}
				$prop['default']     = $field['default'];
				$prop['description'] = $field['label'];
				$props[ $key ]       = $prop;
			}
		}
		return array(
			'type'                 => 'object',
			'properties'           => $props,
			'additionalProperties' => false,
		);
	}

	/**
	 * Public description of an element for the API.
	 *
	 * @param array<string,mixed> $def Definition.
	 * @return array<string,mixed>
	 */
	public static function describe( array $def ) {
		$settings = array();
		foreach ( $def['settings'] as $key => $field ) {
			$settings[] = array_merge( array( 'key' => $key ), $field );
		}
		return array(
			'name'        => $def['name'],
			'slug'        => $def['slug'],
			'title'       => $def['title'],
			'description' => $def['description'],
			'group'       => $def['group'],
			'context'     => $def['context'],
			'tag'         => $def['tag'],
			'source'      => KIRKI_WOO_SOURCE,
			'settings'    => $settings,
			'schema'      => self::json_schema( $def['slug'] ),
		);
	}

	/**
	 * Build a Kirki block (element node) for insertion into Kirki page data.
	 * Returned shape matches what the Kirki editor stores in the `kirki` meta.
	 *
	 * @param string              $name      Element.
	 * @param array<string,mixed> $settings  Settings.
	 * @param string              $parent_id Parent element id.
	 * @param string|null         $id        Element id (random when null).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function build_block( $name, array $settings = array(), $parent_id = 'body', $id = null ) {
		$def = self::get( $name );
		if ( ! $def ) {
			return new \WP_Error( 'kirki_woo_unknown_element', __( 'Unknown element.', 'kirki-woocommerce-bridge' ), array( 'status' => 404 ) );
		}
		$valid = self::validate_settings( $name, $settings );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( null === $id || '' === $id ) {
			$id = 'kwi' . strtolower( wp_generate_password( 8, false, false ) );
		}
		return array(
			'id'         => (string) $id,
			'name'       => $def['name'],
			'title'      => $def['title'],
			'source'     => KIRKI_WOO_SOURCE,
			'parentId'   => (string) $parent_id,
			'children'   => array(),
			'styleIds'   => array(),
			'visibility' => true,
			'collapse'   => false,
			'properties' => array(
				'tag'        => $def['tag'],
				'attributes' => array(),
				'settings'   => self::sanitize_settings( $name, $settings ),
			),
		);
	}
}
