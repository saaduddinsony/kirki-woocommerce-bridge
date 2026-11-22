<?php
/**
 * Server-side rendering for every element.
 *
 * All markup comes from WooCommerce template functions wherever WooCommerce
 * has one, so third-party WooCommerce extensions that hook those templates
 * keep working inside Kirki designs.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Element renderer.
 */
final class Renderer {

	/**
	 * Render an element with its wrapper.
	 *
	 * @param string              $name       Element slug or name.
	 * @param array<string,mixed> $settings   Raw settings (sanitised here).
	 * @param array<string,mixed> $options    Kirki render options (collection item etc.).
	 * @param string              $attributes Pre-built attribute string from Kirki (may be empty).
	 * @return string
	 */
	public static function render( $name, $settings = array(), array $options = array(), $attributes = '' ) {
		$def = Elements::get( $name );
		if ( ! $def ) {
			return '';
		}
		$slug     = $def['slug'];
		$settings = Elements::sanitize_settings( $slug, $settings );

		foreach ( $def['assets'] as $asset ) {
			Assets::need( $asset );
		}
		Assets::need( 'base' );

		$inner = self::inner( $slug, $settings, $options );

		/**
		 * Filters the inner markup of an element.
		 *
		 * @since 1.0.0
		 *
		 * @param string $inner    Markup.
		 * @param string $slug     Element slug.
		 * @param array  $settings Sanitised settings.
		 * @param array  $options  Kirki render options.
		 */
		$inner = apply_filters( 'kirki_woo_element_html', $inner, $slug, $settings, $options );

		if ( '' === trim( (string) $inner ) ) {
			if ( Context::is_editor_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				$inner = self::placeholder( $def );
			} else {
				return '';
			}
		}

		$tag = in_array( $def['tag'], array( 'div', 'section', 'aside', 'nav', 'span' ), true ) ? $def['tag'] : 'div';

		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag,
			self::merge_attributes( (string) $attributes, 'kwi kwi-' . $slug, $slug ),
			$inner
		);
	}

	/**
	 * Placeholder shown in the editor when an element has nothing to show
	 * (no product yet, empty cart, no upsells...).
	 *
	 * @param array<string,mixed> $def Definition.
	 * @return string
	 */
	public static function placeholder( array $def ) {
		return sprintf(
			'<div class="kwi-placeholder"><strong>%1$s</strong><span>%2$s</span></div>',
			esc_html( $def['title'] ),
			esc_html__( 'Nothing to show for the current preview context.', 'kirki-woocommerce-bridge' )
		);
	}

	/**
	 * Merge our class and data attribute into the attribute string Kirki built.
	 *
	 * @param string $attributes Attribute string (already escaped by Kirki).
	 * @param string $classes    Classes to add.
	 * @param string $slug       Element slug.
	 * @return string
	 */
	public static function merge_attributes( $attributes, $classes, $slug ) {
		$attributes = trim( $attributes );
		$classes    = esc_attr( $classes );
		if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/s', $attributes ) ) {
			$attributes = preg_replace( '/\bclass\s*=\s*(["\'])(.*?)\1/s', 'class=$1$2 ' . $classes . '$1', $attributes, 1 );
		} else {
			$attributes .= ' class="' . $classes . '"';
		}
		return trim( $attributes . ' data-kwi-element="' . esc_attr( $slug ) . '"' );
	}

	/**
	 * Inner markup for an element.
	 *
	 * @param string              $slug     Slug.
	 * @param array<string,mixed> $settings Sanitised settings.
	 * @param array<string,mixed> $options  Options.
	 * @return string
	 */
	public static function inner( $slug, array $settings, array $options ) {
		$def = Elements::get( $slug );
		if ( ! $def ) {
			return '';
		}

		if ( has_filter( 'kirki_woo_render_element_' . $slug ) ) {
			/**
			 * Render a custom element registered through `kirki_woo_elements`.
			 *
			 * @since 1.0.0
			 */
			return (string) apply_filters( 'kirki_woo_render_element_' . $slug, '', $settings, $options );
		}

		if ( 'product' === $def['context'] ) {
			$product = Context::product( isset( $settings['product_id'] ) ? (int) $settings['product_id'] : 0, $options );
			if ( ! $product ) {
				return '';
			}
			$method = 'product_' . str_replace( '-', '_', substr( $slug, strlen( 'product-' ) ) );
			if ( ! method_exists( __CLASS__, $method ) ) {
				return '';
			}
			return Context::with_product(
				$product,
				static function ( $product ) use ( $method, $settings ) {
					call_user_func( array( __CLASS__, $method ), $product, $settings );
				}
			);
		}

		$method = 'el_' . str_replace( '-', '_', $slug );
		if ( ! method_exists( __CLASS__, $method ) ) {
			return '';
		}
		return Context::capture(
			static function () use ( $method, $settings, $options ) {
				call_user_func( array( __CLASS__, $method ), $settings, $options );
			}
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Single product parts. Each echoes markup; $product is also global.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Title.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_title( $product, array $settings ) {
		$tag   = tag_escape( $settings['html_tag'] );
		$title = esc_html( $product->get_name() );
		if ( $settings['link'] ) {
			$title = sprintf( '<a href="%s">%s</a>', esc_url( $product->get_permalink() ), $title );
		}
		printf( '<%1$s class="product_title entry-title kwi-product-title__text">%2$s</%1$s>', $tag, $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag passed through tag_escape(), $title escaped above.
	}

	/**
	 * Price.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	private static function product_price( $product ) {
		printf( '<p class="price">%s</p>', wp_kses_post( $product->get_price_html() ) );
	}

	/**
	 * Featured image.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_image( $product, array $settings ) {
		$image_id = $product->get_image_id();
		$html     = $product->get_image( $settings['size'], array( 'class' => 'kwi-product-image__img' ) );
		if ( 'product' === $settings['link'] ) {
			$html = sprintf( '<a href="%s">%s</a>', esc_url( $product->get_permalink() ), $html );
		} elseif ( 'file' === $settings['link'] && $image_id ) {
			$full = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $full ) {
				$html = sprintf( '<a href="%s">%s</a>', esc_url( $full ), $html );
			}
		}
		if ( $settings['sale_badge'] && $product->is_on_sale() ) {
			woocommerce_show_product_sale_flash();
		}
		echo wp_kses_post( $html );
	}

	/**
	 * Gallery (WooCommerce native, supports zoom / slider / lightbox).
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_gallery( $product, array $settings ) {
		echo '<div class="woocommerce"><div class="product">';
		if ( $settings['sale_badge'] ) {
			woocommerce_show_product_sale_flash();
		}
		woocommerce_show_product_images();
		echo '</div></div>';
	}

	/**
	 * Add to cart.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_add_to_cart( $product, array $settings ) {
		$text   = (string) $settings['button_text'];
		$filter = null;
		if ( '' !== $text ) {
			$filter = static function () use ( $text ) {
				return $text;
			};
			add_filter( 'woocommerce_product_single_add_to_cart_text', $filter, 99 );
			add_filter( 'woocommerce_product_add_to_cart_text', $filter, 99 );
		}

		$classes = 'woocommerce kwi-add-to-cart';
		if ( ! $settings['show_quantity'] ) {
			$classes .= ' kwi-hide-quantity';
		}
		printf( '<div class="%s"><div class="product">', esc_attr( $classes ) );
		if ( 'button' === $settings['style'] ) {
			woocommerce_template_loop_add_to_cart();
		} else {
			woocommerce_template_single_add_to_cart();
		}
		echo '</div></div>';

		if ( $filter ) {
			remove_filter( 'woocommerce_product_single_add_to_cart_text', $filter, 99 );
			remove_filter( 'woocommerce_product_add_to_cart_text', $filter, 99 );
		}
	}

	/**
	 * Stock.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_stock( $product, array $settings ) {
		if ( ! $settings['show_in_stock'] && $product->is_in_stock() && ! $product->is_on_backorder() ) {
			return;
		}
		$html = wc_get_stock_html( $product );
		if ( '' === trim( $html ) ) {
			$availability = $product->get_availability();
			$text         = $availability['availability'] ? $availability['availability'] : ( $product->is_in_stock() ? __( 'In stock', 'kirki-woocommerce-bridge' ) : __( 'Out of stock', 'kirki-woocommerce-bridge' ) );
			$class        = $availability['class'] ? $availability['class'] : ( $product->is_in_stock() ? 'in-stock' : 'out-of-stock' );
			$html         = sprintf( '<p class="stock %s">%s</p>', esc_attr( $class ), esc_html( $text ) );
		}
		echo wp_kses_post( $html );
	}

	/**
	 * Rating.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_rating( $product, array $settings ) {
		$count   = $product->get_rating_count();
		$average = $product->get_average_rating();
		if ( ! $count && $settings['hide_if_empty'] ) {
			return;
		}
		echo '<div class="woocommerce-product-rating">';
		echo wc_get_rating_html( $average, $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes this.
		if ( ! $count ) {
			printf( '<div class="star-rating" role="img" aria-label="%1$s"><span style="width:0%%">%1$s</span></div>', esc_attr__( 'Not yet rated', 'kirki-woocommerce-bridge' ) );
		}
		if ( $settings['show_count'] ) {
			$reviews = $product->get_review_count();
			printf(
				'<a href="%1$s#reviews" class="woocommerce-review-link" rel="nofollow">(%2$s)</a>',
				esc_url( $product->get_permalink() ),
				esc_html(
					sprintf(
						/* translators: %s: number of reviews. */
						_n( '%s customer review', '%s customer reviews', $reviews, 'kirki-woocommerce-bridge' ),
						number_format_i18n( $reviews )
					)
				)
			);
		}
		echo '</div>';
	}

	/**
	 * Short description.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	private static function product_short_description( $product ) {
		$text = $product->get_short_description();
		if ( '' === trim( $text ) ) {
			return;
		}
		echo '<div class="woocommerce-product-details__short-description">';
		echo wp_kses_post( apply_filters( 'woocommerce_short_description', $text ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core filter.
		echo '</div>';
	}

	/**
	 * Description.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	private static function product_description( $product ) {
		$content = $product->get_description();
		if ( '' === trim( $content ) ) {
			return;
		}
		// Avoid recursion when the description itself contains Kirki markup.
		$content = wpautop( do_shortcode( wptexturize( $content ) ) );
		echo wp_kses_post( $content );
	}

	/**
	 * Meta.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_meta( $product, array $settings ) {
		echo '<div class="product_meta">';
		do_action( 'woocommerce_product_meta_start' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
		if ( $settings['show_sku'] && wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) {
			$sku = $product->get_sku();
			printf(
				'<span class="sku_wrapper">%1$s <span class="sku">%2$s</span></span>',
				esc_html__( 'SKU:', 'kirki-woocommerce-bridge' ),
				esc_html( $sku ? $sku : __( 'N/A', 'kirki-woocommerce-bridge' ) )
			);
		}
		if ( $settings['show_categories'] ) {
			echo wp_kses_post( wc_get_product_category_list( $product->get_id(), ', ', '<span class="posted_in">' . _n( 'Category:', 'Categories:', count( $product->get_category_ids() ), 'kirki-woocommerce-bridge' ) . ' ', '</span>' ) );
		}
		if ( $settings['show_tags'] ) {
			echo wp_kses_post( wc_get_product_tag_list( $product->get_id(), ', ', '<span class="tagged_as">' . _n( 'Tag:', 'Tags:', count( $product->get_tag_ids() ), 'kirki-woocommerce-bridge' ) . ' ', '</span>' ) );
		}
		do_action( 'woocommerce_product_meta_end' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
		echo '</div>';
	}

	/**
	 * SKU.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_sku( $product, array $settings ) {
		$sku = $product->get_sku();
		if ( '' === $sku ) {
			return;
		}
		if ( '' !== $settings['label'] ) {
			printf( '<span class="kwi-label">%s</span> ', esc_html( $settings['label'] ) );
		}
		printf( '<span class="sku">%s</span>', esc_html( $sku ) );
	}

	/**
	 * Terms.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_terms( $product, array $settings ) {
		$taxonomy = $settings['taxonomy'];
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		$terms = get_the_terms( $product->get_id(), $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}
		$items = array();
		foreach ( $terms as $term ) {
			if ( $settings['link'] ) {
				$link    = get_term_link( $term );
				$items[] = is_wp_error( $link ) ? esc_html( $term->name ) : sprintf( '<a href="%s" rel="tag">%s</a>', esc_url( $link ), esc_html( $term->name ) );
			} else {
				$items[] = esc_html( $term->name );
			}
		}
		echo implode( esc_html( $settings['separator'] ), $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Items escaped above.
	}

	/**
	 * Tabs.
	 *
	 * @return void
	 */
	private static function product_tabs() {
		echo '<div class="woocommerce"><div class="product">';
		woocommerce_output_product_data_tabs();
		echo '</div></div>';
	}

	/**
	 * Additional information table.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	private static function product_attributes( $product ) {
		if ( ! $product->has_attributes() && ! $product->has_weight() && ! $product->has_dimensions() ) {
			return;
		}
		wc_display_product_attributes( $product );
	}

	/**
	 * Reviews.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	private static function product_reviews( $product ) {
		if ( ! comments_open( $product->get_id() ) && ! $product->get_review_count() ) {
			return;
		}
		echo '<div class="woocommerce"><div class="product">';
		// comments_template() bails outside singular views unless $withcomments is set.
		// WooCommerce swaps the theme template for single-product-reviews.php on products.
		$previous                = isset( $GLOBALS['withcomments'] ) ? $GLOBALS['withcomments'] : null;
		$GLOBALS['withcomments'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
		comments_template();
		$GLOBALS['withcomments'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring.
		echo '</div></div>';
	}

	/**
	 * Related.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_related( $product, array $settings ) {
		$ids = wc_get_related_products( $product->get_id(), $settings['limit'], $product->get_upsell_ids() );
		if ( ! $ids ) {
			return;
		}
		self::linked_products( $ids, $settings, 'related', '' !== $settings['heading'] ? $settings['heading'] : __( 'Related products', 'kirki-woocommerce-bridge' ) );
	}

	/**
	 * Upsells.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_upsells( $product, array $settings ) {
		$ids = array_slice( array_filter( array_map( 'absint', $product->get_upsell_ids() ) ), 0, $settings['limit'] );
		if ( ! $ids ) {
			return;
		}
		self::linked_products( $ids, $settings, 'up-sells upsells', '' !== $settings['heading'] ? $settings['heading'] : __( 'You may also like&hellip;', 'kirki-woocommerce-bridge' ) );
	}

	/**
	 * Sale badge.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_sale_badge( $product, array $settings ) {
		if ( ! $product->is_on_sale() ) {
			return;
		}
		$text = '' !== $settings['text'] ? $settings['text'] : __( 'Sale!', 'kirki-woocommerce-bridge' );
		if ( $settings['show_percentage'] ) {
			$pct = Dynamic_Data::discount_percentage( $product );
			if ( $pct > 0 ) {
				/* translators: %d: discount percentage. */
				$text = sprintf( __( '-%d%%', 'kirki-woocommerce-bridge' ), $pct );
			}
		}
		printf( '<span class="onsale">%s</span>', esc_html( $text ) );
	}

	/**
	 * Render linked products (related / upsells / cross-sells) with the WooCommerce loop.
	 *
	 * @param int[]               $ids      Product ids.
	 * @param array<string,mixed> $settings Settings (limit, columns).
	 * @param string              $css_class Section class.
	 * @param string              $heading  Heading.
	 * @return void
	 */
	private static function linked_products( array $ids, array $settings, $css_class, $heading ) {
		$products = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_visible' );
		if ( ! $products ) {
			return;
		}
		printf( '<section class="%s products">', esc_attr( $css_class ) );
		if ( '' !== $heading ) {
			printf( '<h2>%s</h2>', esc_html( $heading ) );
		}
		echo '<div class="woocommerce columns-' . (int) $settings['columns'] . '">';
		wc_set_loop_prop( 'name', 'kirki-woo-' . sanitize_html_class( strtok( $css_class, ' ' ) ) );
		wc_set_loop_prop( 'columns', (int) $settings['columns'] );
		woocommerce_product_loop_start();
		foreach ( $products as $linked ) {
			self::loop_item_template( $linked );
		}
		woocommerce_product_loop_end();
		wc_reset_loop();
		echo '</div></section>';
	}

	/**
	 * Output content-product.php for a product, swapping globals.
	 *
	 * @param \WC_Product $product Product.
	 * @return void
	 */
	private static function loop_item_template( $product ) {
		$html = Context::with_product(
			$product,
			static function () {
				wc_get_template_part( 'content', 'product' );
			}
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output.
	}

	/*
	 * ------------------------------------------------------------------
	 * Shop / archive elements.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Product grid.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param array<string,mixed> $options  Options.
	 * @return void
	 */
	private static function el_products( array $settings, array $options ) {
		$result = Product_Query::run( $settings, $options );
		$ids    = $result['ids'];

		$style = sprintf(
			'--kwi-cols:%d;--kwi-cols-tablet:%d;--kwi-cols-mobile:%d;',
			(int) $settings['columns'],
			(int) $settings['columns_tablet'],
			(int) $settings['columns_mobile']
		);

		if ( ! $ids ) {
			$text = '' !== $settings['no_results'] ? $settings['no_results'] : __( 'No products were found matching your selection.', 'kirki-woocommerce-bridge' );
			printf( '<div class="woocommerce-info woocommerce-no-products-found">%s</div>', esc_html( $text ) );
			return;
		}

		if ( 'woocommerce' === $settings['layout'] ) {
			printf( '<div class="woocommerce columns-%1$d" style="%2$s">', (int) $settings['columns'], esc_attr( $style ) );
			wc_set_loop_prop( 'name', 'kirki-woo-products' );
			wc_set_loop_prop( 'columns', (int) $settings['columns'] );
			wc_set_loop_prop( 'total', $result['total'] );
			wc_set_loop_prop( 'total_pages', $result['total_pages'] );
			wc_set_loop_prop( 'current_page', $result['current_page'] );
			wc_set_loop_prop( 'is_paginated', (bool) $settings['paginate'] );
			woocommerce_product_loop_start();
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product && $product->is_visible() ) {
					self::loop_item_template( $product );
				}
			}
			woocommerce_product_loop_end();
			echo '</div>';
		} else {
			printf( '<ul class="kwi-products__grid products" style="%s">', esc_attr( $style ) );
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product || ! $product->is_visible() ) {
					continue;
				}
				$html = Context::with_product(
					$product,
					static function ( $product ) use ( $settings ) {
						self::product_card( $product, $settings );
					}
				);
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card markup is escaped in product_card().
			}
			echo '</ul>';
		}

		if ( $settings['paginate'] && $result['total_pages'] > 1 ) {
			self::pagination( $result['total_pages'], $result['current_page'], $result['page_var'] );
		}
		if ( 'woocommerce' === $settings['layout'] ) {
			wc_reset_loop();
		}
	}

	/**
	 * Clean product card.
	 *
	 * @param \WC_Product         $product  Product.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function product_card( $product, array $settings ) {
		$classes = wc_get_product_class( 'kwi-product-card', $product );
		printf( '<li class="%s">', esc_attr( implode( ' ', $classes ) ) );
		if ( $settings['show_image'] ) {
			printf( '<a class="kwi-product-card__media" href="%s">', esc_url( $product->get_permalink() ) );
			if ( $settings['show_sale_badge'] && $product->is_on_sale() ) {
				printf( '<span class="onsale">%s</span>', esc_html__( 'Sale!', 'kirki-woocommerce-bridge' ) );
			}
			echo wp_kses_post( $product->get_image( Settings::get( 'card_image_size' ), array( 'class' => 'kwi-product-card__image' ) ) );
			echo '</a>';
		}
		echo '<div class="kwi-product-card__body">';
		if ( $settings['show_title'] ) {
			$tag = tag_escape( $settings['title_tag'] );
			printf(
				'<%1$s class="kwi-product-card__title woocommerce-loop-product__title"><a href="%2$s">%3$s</a></%1$s>',
				$tag, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag_escape() above.
				esc_url( $product->get_permalink() ),
				esc_html( $product->get_name() )
			);
		}
		if ( $settings['show_rating'] && $product->get_rating_count() ) {
			echo wc_get_rating_html( $product->get_average_rating(), $product->get_rating_count() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes this.
		}
		if ( $settings['show_price'] ) {
			printf( '<span class="price">%s</span>', wp_kses_post( $product->get_price_html() ) );
		}
		echo '</div>';
		if ( $settings['show_button'] ) {
			echo '<div class="kwi-product-card__actions">';
			woocommerce_template_loop_add_to_cart();
			echo '</div>';
		}
		echo '</li>';
	}

	/**
	 * Pagination links for a custom query.
	 *
	 * @param int    $total    Total pages.
	 * @param int    $current  Current page.
	 * @param string $page_var Query var.
	 * @return void
	 */
	private static function pagination( $total, $current, $page_var ) {
		if ( 'paged' === $page_var ) {
			$base   = esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) );
			$format = '';
		} else {
			$base   = esc_url_raw( add_query_arg( $page_var, '%#%', remove_query_arg( array( 'add-to-cart', $page_var ) ) ) );
			$format = '';
		}
		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => $format,
				'current'   => max( 1, $current ),
				'total'     => $total,
				'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
				'next_text' => is_rtl() ? '&larr;' : '&rarr;',
				'type'      => 'list',
				'end_size'  => 3,
				'mid_size'  => 3,
			)
		);
		if ( $links ) {
			printf(
				'<nav class="woocommerce-pagination kwi-pagination" aria-label="%s">%s</nav>',
				esc_attr__( 'Product Pagination', 'kirki-woocommerce-bridge' ),
				wp_kses_post( $links )
			);
		}
	}

	/**
	 * Whether we are on a WooCommerce product archive.
	 *
	 * @return bool
	 */
	private static function on_archive() {
		return ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) || ( is_search() && 'product' === get_query_var( 'post_type' ) );
	}

	/**
	 * Result count.
	 *
	 * @return void
	 */
	private static function el_shop_result_count() {
		if ( ! self::on_archive() ) {
			if ( Context::is_editor_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				printf( '<p class="woocommerce-result-count">%s</p>', esc_html__( 'Showing all results', 'kirki-woocommerce-bridge' ) );
			}
			return;
		}
		self::sync_loop_props_from_main_query();
		woocommerce_result_count();
	}

	/**
	 * Ordering.
	 *
	 * @return void
	 */
	private static function el_shop_ordering() {
		if ( ! self::on_archive() && ! Context::is_editor_request() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		self::sync_loop_props_from_main_query();
		wc_set_loop_prop( 'is_paginated', true );
		woocommerce_catalog_ordering();
	}

	/**
	 * Pagination for the main query.
	 *
	 * @return void
	 */
	private static function el_shop_pagination() {
		if ( ! self::on_archive() ) {
			return;
		}
		global $wp_query;
		$total   = (int) $wp_query->max_num_pages;
		$current = max( 1, (int) get_query_var( 'paged' ) );
		if ( $total > 1 ) {
			self::pagination( $total, $current, 'paged' );
		}
	}

	/**
	 * Copy main query numbers into the WooCommerce loop props.
	 *
	 * @return void
	 */
	private static function sync_loop_props_from_main_query() {
		global $wp_query;
		if ( ! $wp_query ) {
			return;
		}
		$per_page = (int) $wp_query->get( 'posts_per_page' );
		wc_set_loop_prop( 'total', (int) $wp_query->found_posts );
		wc_set_loop_prop( 'per_page', $per_page > 0 ? $per_page : (int) $wp_query->found_posts );
		wc_set_loop_prop( 'current_page', max( 1, (int) get_query_var( 'paged' ) ) );
		wc_set_loop_prop( 'total_pages', (int) $wp_query->max_num_pages );
		wc_set_loop_prop( 'is_paginated', true );
	}

	/**
	 * Filter.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_shop_filter( array $settings ) {
		Shop_Filters::render( $settings );
	}

	/**
	 * Product search.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_product_search( array $settings ) {
		static $n = 0;
		++$n;
		$id = 'kwi-product-search-' . $n;
		printf(
			'<form role="search" method="get" class="woocommerce-product-search kwi-search" action="%1$s">' .
			'<label class="screen-reader-text" for="%2$s">%3$s</label>' .
			'<input type="search" id="%2$s" class="search-field" placeholder="%4$s" value="%5$s" name="s" />' .
			'<button type="submit">%6$s</button>' .
			'<input type="hidden" name="post_type" value="product" />' .
			'</form>',
			esc_url( home_url( '/' ) ),
			esc_attr( $id ),
			esc_html__( 'Search for:', 'kirki-woocommerce-bridge' ),
			esc_attr( $settings['placeholder'] ),
			esc_attr( get_search_query() ),
			esc_html( $settings['button_text'] )
		);
	}

	/**
	 * Category grid.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_product_categories( array $settings ) {
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => (bool) $settings['hide_empty'],
			'number'     => (int) $settings['limit'],
			'orderby'    => 'menu_order',
			'pad_counts' => true,
		);
		if ( $settings['parent'] >= 0 ) {
			$args['parent'] = (int) $settings['parent'];
		}
		$terms = get_terms( $args );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}
		printf( '<ul class="kwi-categories__grid" style="--kwi-cols:%d">', (int) $settings['columns'] );
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			echo '<li class="kwi-category-card product-category">';
			printf( '<a href="%s">', esc_url( $link ) );
			if ( $settings['show_image'] ) {
				$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
				if ( $thumb ) {
					echo wp_get_attachment_image( $thumb, 'woocommerce_thumbnail', false, array( 'class' => 'kwi-category-card__image' ) );
				} else {
					echo wp_kses_post( wc_placeholder_img( 'woocommerce_thumbnail', array( 'class' => 'kwi-category-card__image' ) ) );
				}
			}
			printf( '<span class="kwi-category-card__name">%s</span>', esc_html( $term->name ) );
			if ( $settings['show_count'] ) {
				printf( ' <mark class="count">(%s)</mark>', esc_html( number_format_i18n( $term->count ) ) );
			}
			echo '</a></li>';
		}
		echo '</ul>';
	}

	/**
	 * Archive title.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_archive_title( array $settings ) {
		$tag = tag_escape( $settings['html_tag'] );
		printf( '<%1$s class="woocommerce-products-header__title page-title">%2$s</%1$s>', $tag, esc_html( wp_strip_all_tags( woocommerce_page_title( false ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag_escape() above.
	}

	/**
	 * Archive description.
	 *
	 * @return void
	 */
	private static function el_archive_description() {
		if ( is_product_taxonomy() ) {
			woocommerce_taxonomy_archive_description();
		} elseif ( is_shop() ) {
			$shop_page = get_post( wc_get_page_id( 'shop' ) );
			if ( $shop_page && 0 === absint( get_query_var( 'paged' ) ) && '' !== trim( $shop_page->post_excerpt ) ) {
				echo '<div class="page-description">' . wp_kses_post( wpautop( $shop_page->post_excerpt ) ) . '</div>';
			}
		}
	}

	/**
	 * Breadcrumb.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_breadcrumb( array $settings ) {
		woocommerce_breadcrumb(
			array(
				'delimiter' => '<span class="kwi-breadcrumb__sep">' . esc_html( $settings['delimiter'] ) . '</span>',
				'home'      => $settings['home'],
			)
		);
	}

	/**
	 * Notices.
	 *
	 * @return void
	 */
	private static function el_store_notices() {
		echo '<div class="woocommerce-notices-wrapper">';
		if ( function_exists( 'WC' ) && WC()->session ) {
			wc_print_notices();
		}
		echo '</div>';
	}

	/*
	 * ------------------------------------------------------------------
	 * Cart, checkout, account.
	 * ------------------------------------------------------------------
	 */

	/**
	 * Mini cart.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_mini_cart( array $settings ) {
		static $n = 0;
		++$n;
		$drawer_id = 'kwi-mini-cart-drawer-' . $n;
		$count     = Fragments::cart_count();
		$drawer    = 'drawer' === $settings['behavior'] && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) );

		printf( '<div class="kwi-mini-cart" data-behavior="%s">', esc_attr( $drawer ? 'drawer' : 'cart' ) );
		printf(
			'<a class="kwi-mini-cart__toggle" href="%1$s"%2$s aria-label="%3$s">',
			esc_url( wc_get_cart_url() ),
			$drawer ? ' role="button" aria-expanded="false" aria-controls="' . esc_attr( $drawer_id ) . '"' : '',
			esc_attr__( 'View cart', 'kirki-woocommerce-bridge' )
		);
		if ( $settings['show_icon'] ) {
			echo '<svg class="kwi-mini-cart__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M3 4h2l2.4 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.76L20 8H6.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="19" r="1.5" fill="currentColor"/><circle cx="17" cy="19" r="1.5" fill="currentColor"/></svg>';
		}
		if ( $settings['show_count'] ) {
			echo Fragments::count_html( $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Fragments.
		}
		if ( $settings['show_total'] ) {
			echo Fragments::total_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Fragments.
		}
		echo '</a>';
		if ( $drawer ) {
			printf(
				'<div class="kwi-mini-cart__drawer" id="%1$s" role="dialog" aria-modal="true" aria-label="%2$s" hidden>' .
				'<div class="kwi-mini-cart__panel"><div class="kwi-mini-cart__head"><span>%2$s</span>' .
				'<button type="button" class="kwi-mini-cart__close" aria-label="%3$s">&times;</button></div>' .
				'<div class="widget_shopping_cart_content">',
				esc_attr( $drawer_id ),
				esc_html__( 'Your cart', 'kirki-woocommerce-bridge' ),
				esc_attr__( 'Close', 'kirki-woocommerce-bridge' )
			);
			if ( function_exists( 'WC' ) && WC()->cart ) {
				woocommerce_mini_cart();
			}
			echo '</div></div><div class="kwi-mini-cart__overlay" data-kwi-close></div></div>';
		}
		echo '</div>';
	}

	/**
	 * Cart.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_cart( array $settings ) {
		if ( 'block' === $settings['mode'] ) {
			echo do_blocks( '<!-- wp:woocommerce/cart /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup.
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		echo \WC_Shortcodes::cart(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce shortcode output.
	}

	/**
	 * Cart totals.
	 *
	 * @return void
	 */
	private static function el_cart_totals() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		WC()->cart->calculate_totals();
		echo '<div class="woocommerce"><div class="cart-collaterals">';
		woocommerce_cart_totals();
		echo '</div></div>';
	}

	/**
	 * Cross-sells.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_cart_cross_sells( array $settings ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$ids = array_slice( WC()->cart->get_cross_sells(), 0, (int) $settings['limit'] );
		if ( ! $ids ) {
			return;
		}
		self::linked_products( $ids, $settings, 'cross-sells', __( 'You may be interested in&hellip;', 'kirki-woocommerce-bridge' ) );
	}

	/**
	 * Checkout.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private static function el_checkout( array $settings ) {
		if ( 'block' === $settings['mode'] && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) ) {
			echo do_blocks( '<!-- wp:woocommerce/checkout /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block markup.
			return;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( Context::is_preview() && WC()->cart->is_empty() ) {
			printf( '<div class="woocommerce-info">%s</div>', esc_html__( 'Checkout preview: add a product to the cart in another tab to see the full form here.', 'kirki-woocommerce-bridge' ) );
			return;
		}
		echo \WC_Shortcodes::checkout( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce shortcode output.
	}

	/**
	 * My account.
	 *
	 * @return void
	 */
	private static function el_my_account() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		echo \WC_Shortcodes::my_account( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce shortcode output.
	}

	/**
	 * Order tracking.
	 *
	 * @return void
	 */
	private static function el_order_tracking() {
		if ( ! get_post() ) {
			// The WooCommerce form posts back to the current page, so it needs one.
			if ( Context::is_preview() ) {
				printf( '<div class="woocommerce-info">%s</div>', esc_html__( 'Order tracking form: visitors enter an order ID and billing email here.', 'kirki-woocommerce-bridge' ) );
			}
			return;
		}
		echo \WC_Shortcodes::order_tracking( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce shortcode output.
	}
}
