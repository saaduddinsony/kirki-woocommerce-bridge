<?php
/**
 * Thin public wrapper over WooCommerce's product shortcode query.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Reuses WC_Shortcode_Products so every WooCommerce / extension filter on
 * shortcode queries (visibility, ordering, caching) applies to our grids.
 */
final class Shortcode_Products extends \WC_Shortcode_Products {

	/**
	 * Query results.
	 *
	 * @return object{ids:int[],total:int,total_pages:int,per_page:int,current_page:int}
	 */
	public function results() {
		return $this->get_query_results();
	}
}
