<?php
/**
 * Product queries for the Products Grid element.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Builds product id lists for each grid source using WooCommerce's own query
 * classes so catalog visibility, "hide out of stock" and sorting rules apply.
 */
final class Product_Query {

	/**
	 * Run the query for a grid.
	 *
	 * @param array<string,mixed> $settings Sanitised element settings.
	 * @param array<string,mixed> $options  Kirki options.
	 * @return array{ids:int[],total:int,total_pages:int,current_page:int,page_var:string}
	 */
	public static function run( array $settings, array $options = array() ) {
		$source = $settings['source'];
		$limit  = max( 1, (int) $settings['limit'] );
		$empty  = array(
			'ids'          => array(),
			'total'        => 0,
			'total_pages'  => 0,
			'current_page' => 1,
			'page_var'     => 'product-page',
		);

		// Main query on shop / product archives / product search.
		if ( 'current_query' === $source && self::is_main_product_query() ) {
			global $wp_query;
			$ids = array_map( 'intval', wp_list_pluck( $wp_query->posts, 'ID' ) );
			return array(
				'ids'          => $ids,
				'total'        => (int) $wp_query->found_posts,
				'total_pages'  => (int) $wp_query->max_num_pages,
				'current_page' => max( 1, (int) get_query_var( 'paged' ) ),
				'page_var'     => 'paged',
			);
		}

		if ( in_array( $source, array( 'related', 'upsells' ), true ) ) {
			$product = Context::product( 0, $options );
			if ( ! $product ) {
				return $empty;
			}
			$ids = 'related' === $source
				? wc_get_related_products( $product->get_id(), $limit, $product->get_upsell_ids() )
				: array_slice( array_map( 'absint', $product->get_upsell_ids() ), 0, $limit );
			return self::static_result( $ids );
		}

		if ( 'cross_sells' === $source ) {
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				return $empty;
			}
			return self::static_result( array_slice( WC()->cart->get_cross_sells(), 0, $limit ) );
		}

		$type = 'products';
		$atts = array(
			'limit'    => $limit,
			'columns'  => (int) $settings['columns'],
			'orderby'  => $settings['orderby'],
			'order'    => $settings['order'],
			'paginate' => $settings['paginate'] ? 'true' : 'false',
			'cache'    => 'false',
		);
		switch ( $source ) {
			case 'featured':
				$atts['visibility'] = 'featured';
				break;
			case 'on_sale':
				$type = 'sale_products';
				break;
			case 'best_selling':
				$type            = 'best_selling_products';
				$atts['orderby'] = 'popularity';
				break;
			case 'top_rated':
				$type            = 'top_rated_products';
				$atts['orderby'] = 'rating';
				break;
			case 'ids':
				$ids = self::csv_ints( $settings['ids'] );
				if ( ! $ids ) {
					return $empty;
				}
				$atts['ids'] = implode( ',', $ids );
				if ( 'date' === $settings['orderby'] ) {
					$atts['orderby'] = 'post__in';
				}
				break;
		}
		if ( '' !== $settings['category'] ) {
			$atts['category']     = self::csv_slugs( $settings['category'] );
			$atts['cat_operator'] = 'IN';
		}
		if ( '' !== $settings['tag'] ) {
			$atts['tag']          = self::csv_slugs( $settings['tag'] );
			$atts['tag_operator'] = 'IN';
		}

		// Filters from the request apply to "current query" grids on regular pages.
		$filter_ids = null;
		if ( 'current_query' === $source && Settings::get( 'enable_shop_filters' ) ) {
			$filter_ids = Shop_Filters::matching_ids_from_request();
			if ( is_array( $filter_ids ) ) {
				if ( ! $filter_ids ) {
					return $empty;
				}
				$atts['ids'] = implode( ',', $filter_ids );
			}
			$ordering = Shop_Filters::requested_orderby();
			if ( $ordering ) {
				$atts['orderby'] = $ordering['orderby'];
				$atts['order']   = $ordering['order'];
			}
		}

		$shortcode = new Shortcode_Products( $atts, $type );
		$results   = $shortcode->results();

		return array(
			'ids'          => array_map( 'intval', (array) $results->ids ),
			'total'        => (int) $results->total,
			'total_pages'  => (int) $results->total_pages,
			'current_page' => max( 1, (int) $results->current_page ),
			'page_var'     => 'product-page',
		);
	}

	/**
	 * Result for a fixed list.
	 *
	 * @param int[] $ids Ids.
	 * @return array{ids:int[],total:int,total_pages:int,current_page:int,page_var:string}
	 */
	private static function static_result( array $ids ) {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		return array(
			'ids'          => $ids,
			'total'        => count( $ids ),
			'total_pages'  => $ids ? 1 : 0,
			'current_page' => 1,
			'page_var'     => 'product-page',
		);
	}

	/**
	 * Whether the main query is a product listing.
	 *
	 * @return bool
	 */
	public static function is_main_product_query() {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}
		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query ) {
			return false;
		}
		return is_shop() || is_product_taxonomy() || ( is_search() && in_array( 'product', (array) $wp_query->get( 'post_type' ), true ) );
	}

	/**
	 * Parse "1, 2,3" to ints.
	 *
	 * @param string $csv CSV.
	 * @return int[]
	 */
	public static function csv_ints( $csv ) {
		return array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $csv ) ) ) );
	}

	/**
	 * Parse slugs CSV.
	 *
	 * @param string $csv CSV.
	 * @return string
	 */
	public static function csv_slugs( $csv ) {
		$slugs = array_filter( array_map( 'sanitize_title', preg_split( '/\s*,\s*/', (string) $csv ) ) );
		return implode( ',', $slugs );
	}
}
