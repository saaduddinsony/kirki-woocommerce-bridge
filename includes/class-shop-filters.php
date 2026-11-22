<?php
/**
 * Shop filters: request parsing, query modification and filter markup.
 *
 * Uses WooCommerce's native query vars where they exist (min_price, max_price,
 * filter_{attribute}, query_type_{attribute}, rating_filter, orderby) and adds
 * three of its own: kwi_cat, kwi_stock and kwi_sale.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Filters.
 */
final class Shop_Filters {

	/**
	 * Hook into WooCommerce.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_product_query', array( __CLASS__, 'modify_main_query' ), 20 );
	}

	/**
	 * Read a request var.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function req( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only catalog filters.
		if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only catalog filters.
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Parse the active filters from the request.
	 *
	 * @return array<string,mixed>
	 */
	public static function active() {
		$out = array(
			'min_price'  => null,
			'max_price'  => null,
			'categories' => array(),
			'stock'      => array(),
			'on_sale'    => false,
			'rating'     => array(),
			'attributes' => array(),
		);
		if ( '' !== self::req( 'min_price' ) ) {
			$out['min_price'] = (float) wc_clean( self::req( 'min_price' ) );
		}
		if ( '' !== self::req( 'max_price' ) ) {
			$out['max_price'] = (float) wc_clean( self::req( 'max_price' ) );
		}
		$out['categories'] = self::list_param( 'kwi_cat', 'sanitize_title' );
		$out['stock']      = array_values( array_intersect( self::list_param( 'kwi_stock', 'sanitize_key' ), array_keys( wc_get_product_stock_status_options() ) ) );
		$out['on_sale']    = '1' === self::req( 'kwi_sale' );
		$out['rating']     = array_values( array_filter( array_map( 'absint', self::list_param( 'rating_filter', 'absint' ) ), static fn( $r ) => $r >= 1 && $r <= 5 ) );
		foreach ( wc_get_attribute_taxonomy_names() as $taxonomy ) {
			$name  = wc_attribute_taxonomy_slug( $taxonomy );
			$terms = self::list_param( 'filter_' . $name, 'sanitize_title' );
			if ( $terms ) {
				$type                           = self::req( 'query_type_' . $name );
				$out['attributes'][ $taxonomy ] = array(
					'terms'      => $terms,
					'query_type' => 'or' === $type ? 'or' : 'and',
				);
			}
		}
		return $out;
	}

	/**
	 * Comma separated list param.
	 *
	 * @param string   $key       Key.
	 * @param callable $sanitizer Sanitizer.
	 * @return array<int,mixed>
	 */
	private static function list_param( $key, $sanitizer ) {
		$raw = self::req( $key );
		if ( '' === $raw ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( $sanitizer, explode( ',', $raw ) ) ) ) );
	}

	/**
	 * Whether any filter is active.
	 *
	 * @param array<string,mixed>|null $active Active filters.
	 * @return bool
	 */
	public static function has_any( $active = null ) {
		$active = null === $active ? self::active() : $active;
		return null !== $active['min_price'] || null !== $active['max_price'] || $active['categories'] || $active['stock'] || $active['on_sale'] || $active['rating'] || $active['attributes'];
	}

	/**
	 * Apply our extra filters to the main product query (WooCommerce already
	 * applies price, attribute and rating filters there).
	 *
	 * @param \WP_Query $q Query.
	 * @return void
	 */
	public static function modify_main_query( $q ) {
		if ( ! Settings::get( 'enable_shop_filters' ) ) {
			return;
		}
		$active = self::active();

		$tax_query = (array) $q->get( 'tax_query' );
		if ( $active['categories'] ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $active['categories'],
				'operator' => 'IN',
			);
		}
		$q->set( 'tax_query', $tax_query );

		if ( $active['stock'] ) {
			$meta_query   = (array) $q->get( 'meta_query' );
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => $active['stock'],
				'compare' => 'IN',
			);
			$q->set( 'meta_query', $meta_query );
		}

		if ( $active['on_sale'] ) {
			$sale = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
			$in   = (array) $q->get( 'post__in' );
			$q->set( 'post__in', $in ? array_values( array_intersect( $in, $sale ) ) : $sale );
		}
	}

	/**
	 * For grids outside the main query: the list of product ids matching the
	 * request filters, or null when no filter is active.
	 *
	 * @return int[]|null
	 */
	public static function matching_ids_from_request() {
		$active = self::active();
		if ( ! self::has_any( $active ) ) {
			return null;
		}
		return self::matching_ids( $active );
	}

	/**
	 * Product ids matching a filter set.
	 *
	 * @param array<string,mixed> $active Filters.
	 * @return int[]
	 */
	public static function matching_ids( array $active ) {
		global $wpdb;

		$tax_query = array( 'relation' => 'AND' );
		if ( $active['categories'] ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $active['categories'],
			);
		}
		foreach ( $active['attributes'] as $taxonomy => $data ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $data['terms'],
				'operator' => 'and' === $data['query_type'] ? 'AND' : 'IN',
			);
		}
		if ( $active['rating'] ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array_map( static fn( $r ) => 'rated-' . $r, $active['rating'] ),
				'operator' => 'IN',
			);
		}

		$args = array(
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'fields'           => 'ids',
			'posts_per_page'   => -1,
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'tax_query'        => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filters require a taxonomy query.
		);
		if ( $active['stock'] ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Stock filter.
				array(
					'key'     => '_stock_status',
					'value'   => $active['stock'],
					'compare' => 'IN',
				),
			);
		}
		if ( $active['on_sale'] ) {
			$args['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
		}
		$ids = array_map( 'intval', get_posts( $args ) );

		if ( $ids && ( null !== $active['min_price'] || null !== $active['max_price'] ) ) {
			$min = null === $active['min_price'] ? 0 : (float) $active['min_price'];
			$max = null === $active['max_price'] ? PHP_INT_MAX : (float) $active['max_price'];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce lookup table, request-scoped.
			$priced = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT product_id FROM {$wpdb->wc_product_meta_lookup} WHERE max_price >= %f AND min_price <= %f",
					$min,
					$max
				)
			);
			$ids    = array_values( array_intersect( $ids, array_map( 'intval', $priced ) ) );
		}
		return $ids;
	}

	/**
	 * Ordering requested through the sorting dropdown.
	 *
	 * @return array{orderby:string,order:string}|null
	 */
	public static function requested_orderby() {
		$value = self::req( 'orderby' );
		if ( '' === $value ) {
			return null;
		}
		$map = array(
			'menu_order' => array( 'menu_order', 'ASC' ),
			'popularity' => array( 'popularity', 'DESC' ),
			'rating'     => array( 'rating', 'DESC' ),
			'date'       => array( 'date', 'DESC' ),
			'price'      => array( 'price', 'ASC' ),
			'price-desc' => array( 'price', 'DESC' ),
		);
		if ( ! isset( $map[ $value ] ) ) {
			return null;
		}
		return array(
			'orderby' => $map[ $value ][0],
			'order'   => $map[ $value ][1],
		);
	}

	/**
	 * The current URL without pagination.
	 *
	 * @return string
	 */
	public static function base_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$uri = preg_replace( '#/page/\d+/?#', '/', $uri );
		$url = home_url( $uri );
		// home_url() adds the subdirectory twice for sites in a subfolder.
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $home_path && '/' !== $home_path && 0 === strpos( $uri, $home_path ) ) {
			$url = home_url( substr( $uri, strlen( rtrim( $home_path, '/' ) ) ) );
		}
		return remove_query_arg( array( 'paged', 'product-page', 'add-to-cart' ), $url );
	}

	/**
	 * URL that toggles a value in a comma list param.
	 *
	 * @param string $key   Param.
	 * @param string $value Value.
	 * @param array  $values Current list.
	 * @return string
	 */
	private static function toggle_url( $key, $value, array $values ) {
		$value  = (string) $value;
		$values = array_map( 'strval', $values );
		if ( in_array( $value, $values, true ) ) {
			$values = array_values( array_diff( $values, array( $value ) ) );
		} else {
			$values[] = $value;
		}
		$url = self::base_url();
		return $values ? add_query_arg( $key, implode( ',', $values ), $url ) : remove_query_arg( $key, $url );
	}

	/**
	 * Render a filter element.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	public static function render( array $settings ) {
		$active = self::active();
		$type   = $settings['filter_type'];
		$titles = array(
			'price'     => __( 'Price', 'kirki-woocommerce-bridge' ),
			'category'  => __( 'Categories', 'kirki-woocommerce-bridge' ),
			'attribute' => __( 'Filter by', 'kirki-woocommerce-bridge' ),
			'rating'    => __( 'Rating', 'kirki-woocommerce-bridge' ),
			'stock'     => __( 'Availability', 'kirki-woocommerce-bridge' ),
			'on_sale'   => __( 'Deals', 'kirki-woocommerce-bridge' ),
			'active'    => __( 'Active filters', 'kirki-woocommerce-bridge' ),
		);
		$title  = '' !== $settings['title'] ? $settings['title'] : $titles[ $type ];

		ob_start();
		switch ( $type ) {
			case 'price':
				self::render_price( $active, $settings );
				break;
			case 'category':
				self::render_categories( $active, $settings );
				break;
			case 'attribute':
				self::render_attribute( $active, $settings );
				break;
			case 'rating':
				self::render_rating( $active );
				break;
			case 'stock':
				self::render_stock( $active );
				break;
			case 'on_sale':
				self::render_sale( $active );
				break;
			case 'active':
				self::render_active( $active );
				break;
		}
		$body = (string) ob_get_clean();
		if ( '' === trim( $body ) ) {
			return;
		}
		printf(
			'<div class="kwi-filter kwi-filter--%1$s"><p class="kwi-filter__title">%2$s</p>%3$s</div>',
			esc_attr( $type ),
			esc_html( $title ),
			$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts below.
		);
	}

	/**
	 * Price form.
	 *
	 * @param array<string,mixed> $active   Active.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function render_price( array $active, array $settings ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce lookup table.
		$range = $wpdb->get_row( "SELECT MIN(min_price) AS lo, MAX(max_price) AS hi FROM {$wpdb->wc_product_meta_lookup}" );
		$lo    = $range && null !== $range->lo ? floor( (float) $range->lo ) : 0;
		$hi    = $range && null !== $range->hi ? ceil( (float) $range->hi ) : 0;

		$hidden = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve public query args.
		foreach ( wp_unslash( $_GET ) as $key => $value ) {
			if ( in_array( $key, array( 'min_price', 'max_price', 'paged', 'product-page' ), true ) || is_array( $value ) ) {
				continue;
			}
			$hidden .= sprintf( '<input type="hidden" name="%s" value="%s" />', esc_attr( sanitize_key( $key ) ), esc_attr( sanitize_text_field( $value ) ) );
		}
		printf(
			'<form method="get" action="%1$s" class="kwi-filter__price%9$s">' .
			'<label><span>%2$s</span><input type="number" name="min_price" min="%3$s" max="%4$s" step="1" value="%5$s" placeholder="%3$s" inputmode="decimal" /></label>' .
			'<label><span>%6$s</span><input type="number" name="max_price" min="%3$s" max="%4$s" step="1" value="%7$s" placeholder="%4$s" inputmode="decimal" /></label>' .
			'%10$s<button type="submit" class="button">%8$s</button></form>',
			esc_url( strtok( self::base_url(), '?' ) ),
			esc_html__( 'Min', 'kirki-woocommerce-bridge' ),
			esc_attr( $lo ),
			esc_attr( $hi ),
			esc_attr( null === $active['min_price'] ? '' : $active['min_price'] ),
			esc_html__( 'Max', 'kirki-woocommerce-bridge' ),
			esc_attr( null === $active['max_price'] ? '' : $active['max_price'] ),
			esc_html__( 'Filter', 'kirki-woocommerce-bridge' ),
			$settings['auto_submit'] ? ' kwi-auto-submit' : '',
			$hidden // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);
	}

	/**
	 * A list of toggle links.
	 *
	 * @param array<int,array{label:string,url:string,active:bool,count:int|null}> $items Items.
	 * @return void
	 */
	private static function render_list( array $items ) {
		if ( ! $items ) {
			return;
		}
		echo '<ul class="kwi-filter__list">';
		foreach ( $items as $item ) {
			printf(
				'<li class="%1$s"><a href="%2$s" rel="nofollow"%3$s><span class="kwi-filter__check" aria-hidden="true"></span>%4$s%5$s</a></li>',
				$item['active'] ? 'is-active chosen' : '',
				esc_url( $item['url'] ),
				$item['active'] ? ' aria-current="true"' : '',
				wp_kses_post( $item['label'] ),
				null === $item['count'] ? '' : ' <span class="count">(' . esc_html( number_format_i18n( $item['count'] ) ) . ')</span>'
			);
		}
		echo '</ul>';
	}

	/**
	 * Categories.
	 *
	 * @param array<string,mixed> $active   Active.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function render_categories( array $active, array $settings ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}
		$items = array();
		foreach ( $terms as $term ) {
			$items[] = array(
				'label'  => esc_html( $term->name ),
				'url'    => self::toggle_url( 'kwi_cat', $term->slug, $active['categories'] ),
				'active' => in_array( $term->slug, $active['categories'], true ),
				'count'  => $settings['show_count'] ? (int) $term->count : null,
			);
		}
		self::render_list( $items );
	}

	/**
	 * Attribute.
	 *
	 * @param array<string,mixed> $active   Active.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function render_attribute( array $active, array $settings ) {
		$taxonomy = wc_sanitize_taxonomy_name( $settings['attribute'] );
		if ( '' !== $taxonomy && 0 !== strpos( $taxonomy, 'pa_' ) ) {
			$taxonomy = 'pa_' . $taxonomy;
		}
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			if ( Context::is_editor_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				printf( '<p class="kwi-filter__hint">%s</p>', esc_html__( 'Set the attribute slug (for example pa_color) in the element settings.', 'kirki-woocommerce-bridge' ) );
			}
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}
		$name    = wc_attribute_taxonomy_slug( $taxonomy );
		$current = isset( $active['attributes'][ $taxonomy ] ) ? $active['attributes'][ $taxonomy ]['terms'] : array();
		$items   = array();
		foreach ( $terms as $term ) {
			$url     = self::toggle_url( 'filter_' . $name, $term->slug, $current );
			$url     = add_query_arg( 'query_type_' . $name, 'or', $url );
			$items[] = array(
				'label'  => esc_html( $term->name ),
				'url'    => $url,
				'active' => in_array( $term->slug, $current, true ),
				'count'  => $settings['show_count'] ? (int) $term->count : null,
			);
		}
		self::render_list( $items );
	}

	/**
	 * Rating.
	 *
	 * @param array<string,mixed> $active Active.
	 * @return void
	 */
	private static function render_rating( array $active ) {
		$items = array();
		for ( $rating = 5; $rating >= 1; $rating-- ) {
			$items[] = array(
				'label'  => wc_get_rating_html( $rating ) . '<span class="screen-reader-text">' . esc_html(
					sprintf(
						/* translators: %d: rating. */
						_n( 'Rated %d star', 'Rated %d stars', $rating, 'kirki-woocommerce-bridge' ),
						$rating
					)
				) . '</span>',
				'url'    => self::toggle_url( 'rating_filter', (string) $rating, $active['rating'] ),
				'active' => in_array( $rating, $active['rating'], true ),
				'count'  => null,
			);
		}
		self::render_list( $items );
	}

	/**
	 * Stock.
	 *
	 * @param array<string,mixed> $active Active.
	 * @return void
	 */
	private static function render_stock( array $active ) {
		$items = array();
		foreach ( wc_get_product_stock_status_options() as $status => $label ) {
			$items[] = array(
				'label'  => esc_html( $label ),
				'url'    => self::toggle_url( 'kwi_stock', $status, $active['stock'] ),
				'active' => in_array( $status, $active['stock'], true ),
				'count'  => null,
			);
		}
		self::render_list( $items );
	}

	/**
	 * On sale.
	 *
	 * @param array<string,mixed> $active Active.
	 * @return void
	 */
	private static function render_sale( array $active ) {
		$url = $active['on_sale'] ? remove_query_arg( 'kwi_sale', self::base_url() ) : add_query_arg( 'kwi_sale', '1', self::base_url() );
		self::render_list(
			array(
				array(
					'label'  => esc_html__( 'On sale', 'kirki-woocommerce-bridge' ),
					'url'    => $url,
					'active' => $active['on_sale'],
					'count'  => null,
				),
			)
		);
	}

	/**
	 * Active filter chips.
	 *
	 * @param array<string,mixed> $active Active.
	 * @return void
	 */
	private static function render_active( array $active ) {
		if ( ! self::has_any( $active ) ) {
			return;
		}
		$base  = self::base_url();
		$chips = array();
		if ( null !== $active['min_price'] ) {
			/* translators: %s: price. */
			$chips[] = array( sprintf( __( 'Min %s', 'kirki-woocommerce-bridge' ), wp_strip_all_tags( wc_price( $active['min_price'] ) ) ), remove_query_arg( 'min_price', $base ) );
		}
		if ( null !== $active['max_price'] ) {
			/* translators: %s: price. */
			$chips[] = array( sprintf( __( 'Max %s', 'kirki-woocommerce-bridge' ), wp_strip_all_tags( wc_price( $active['max_price'] ) ) ), remove_query_arg( 'max_price', $base ) );
		}
		foreach ( $active['categories'] as $slug ) {
			$term    = get_term_by( 'slug', $slug, 'product_cat' );
			$chips[] = array( $term ? $term->name : $slug, self::toggle_url( 'kwi_cat', $slug, $active['categories'] ) );
		}
		foreach ( $active['attributes'] as $taxonomy => $data ) {
			$name = wc_attribute_taxonomy_slug( $taxonomy );
			foreach ( $data['terms'] as $slug ) {
				$term    = get_term_by( 'slug', $slug, $taxonomy );
				$chips[] = array( ( $term ? $term->name : $slug ), self::toggle_url( 'filter_' . $name, $slug, $data['terms'] ) );
			}
		}
		foreach ( $active['rating'] as $rating ) {
			/* translators: %d: rating. */
			$chips[] = array( sprintf( __( 'Rated %d+', 'kirki-woocommerce-bridge' ), $rating ), self::toggle_url( 'rating_filter', (string) $rating, $active['rating'] ) );
		}
		$stock_labels = wc_get_product_stock_status_options();
		foreach ( $active['stock'] as $status ) {
			$chips[] = array( $stock_labels[ $status ], self::toggle_url( 'kwi_stock', $status, $active['stock'] ) );
		}
		if ( $active['on_sale'] ) {
			$chips[] = array( __( 'On sale', 'kirki-woocommerce-bridge' ), remove_query_arg( 'kwi_sale', $base ) );
		}
		echo '<ul class="kwi-filter__chips">';
		foreach ( $chips as $chip ) {
			printf(
				'<li><a href="%1$s" rel="nofollow" aria-label="%2$s">%3$s <span aria-hidden="true">&times;</span></a></li>',
				esc_url( $chip[1] ),
				/* translators: %s: filter label. */
				esc_attr( sprintf( __( 'Remove filter: %s', 'kirki-woocommerce-bridge' ), $chip[0] ) ),
				esc_html( $chip[0] )
			);
		}
		$keys = array( 'min_price', 'max_price', 'kwi_cat', 'kwi_stock', 'kwi_sale', 'rating_filter' );
		foreach ( wc_get_attribute_taxonomy_names() as $taxonomy ) {
			$keys[] = 'filter_' . wc_attribute_taxonomy_slug( $taxonomy );
			$keys[] = 'query_type_' . wc_attribute_taxonomy_slug( $taxonomy );
		}
		printf( '<li class="kwi-filter__reset"><a href="%s" rel="nofollow">%s</a></li>', esc_url( remove_query_arg( $keys, $base ) ), esc_html__( 'Clear all', 'kirki-woocommerce-bridge' ) );
		echo '</ul>';
	}
}
