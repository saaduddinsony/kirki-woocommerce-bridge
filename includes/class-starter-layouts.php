<?php
/**
 * Starter layouts for new WooCommerce designs.
 *
 * @package KirkiWoo
 */

namespace KirkiWoo;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Kirki block graphs (root > body > sections) made of native Kirki
 * layout elements and this plugin's WooCommerce elements.
 */
final class Starter_Layouts {

	/**
	 * Blocks being built.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $blocks = array();

	/**
	 * Style blocks being built.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $styles = array();

	/**
	 * Counter for ids.
	 *
	 * @var int
	 */
	private $n = 0;

	/**
	 * Id seed.
	 *
	 * @var string
	 */
	private $seed;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->seed = strtolower( wp_generate_password( 5, false, false ) );
	}

	/**
	 * Next id.
	 *
	 * @param string $prefix Prefix.
	 * @return string
	 */
	private function id( $prefix = 'kwi' ) {
		++$this->n;
		return $prefix . $this->seed . $this->n;
	}

	/**
	 * Add a style block.
	 *
	 * @param string               $css      Desktop CSS.
	 * @param array<string,string> $variants Extra variants (tablet, mobile...).
	 * @return string Style id.
	 */
	private function style( $css, array $variants = array() ) {
		$id                  = $this->id( 'kwis' );
		$this->styles[ $id ] = array(
			'id'        => $id,
			'type'      => 'class',
			'name'      => 'kwi-' . $id,
			'isDefault' => false,
			'variant'   => array_merge( array( 'md' => $css ), $variants ),
		);
		return $id;
	}

	/**
	 * Start a document with root and body.
	 *
	 * @return void
	 */
	private function start() {
		$body_style   = $this->style( 'display:flex;flex-direction:column;gap:32px;padding-top:40px;padding-bottom:64px;padding-left:24px;padding-right:24px;max-width:1200px;margin-left:auto;margin-right:auto;width:100%;box-sizing:border-box;' );
		$this->blocks = array(
			'root' => array(
				'accept'   => '*',
				'children' => array( 'body' ),
				'id'       => 'root',
				'name'     => 'root',
				'styleIds' => array(),
				'title'    => 'Root',
			),
			'body' => array(
				'visibility'       => true,
				'collapse'         => false,
				'name'             => 'body',
				'title'            => 'Body',
				'properties'       => array( 'tag' => 'div' ),
				'styleIds'         => array( $body_style ),
				'className'        => 'kirki-page',
				'source'           => 'kirki',
				'id'               => 'body',
				'parentId'         => 'root',
				'children'         => array(),
				'template_mounted' => true,
			),
		);
	}

	/**
	 * Add a native Kirki div.
	 *
	 * @param string $parent_id Parent id.
	 * @param string $css    CSS.
	 * @param array  $variants Variants.
	 * @param string $title  Navigator title.
	 * @return string Id.
	 */
	private function div( $parent_id, $css, array $variants = array(), $title = 'Div' ) {
		$id                                       = $this->id();
		$this->blocks[ $id ]                      = array(
			'id'         => $id,
			'name'       => 'div',
			'title'      => $title,
			'source'     => 'kirki',
			'parentId'   => $parent_id,
			'children'   => array(),
			'styleIds'   => array( $this->style( $css, $variants ) ),
			'visibility' => true,
			'collapse'   => false,
			'properties' => array(
				'tag'        => 'div',
				'attributes' => array(),
			),
		);
		$this->blocks[ $parent_id ]['children'][] = $id;
		return $id;
	}

	/**
	 * Add one of our elements.
	 *
	 * @param string              $parent_id   Parent id.
	 * @param string              $slug     Element slug.
	 * @param array<string,mixed> $settings Settings.
	 * @return string Id.
	 */
	private function el( $parent_id, $slug, array $settings = array() ) {
		$block = Elements::build_block( $slug, $settings, $parent_id, $this->id() );
		if ( is_wp_error( $block ) ) {
			return '';
		}
		$this->blocks[ $block['id'] ]             = $block;
		$this->blocks[ $parent_id ]['children'][] = $block['id'];
		return $block['id'];
	}

	/**
	 * Result.
	 *
	 * @return array{blocks:array<string,mixed>,styles:array<string,mixed>}
	 */
	private function result() {
		return array(
			'blocks' => $this->blocks,
			'styles' => $this->styles,
		);
	}

	/**
	 * Empty design.
	 *
	 * @return array{blocks:array<string,mixed>,styles:array<string,mixed>}
	 */
	public static function blank() {
		$b = new self();
		$b->start();
		return $b->result();
	}

	/**
	 * Starter for a context.
	 *
	 * @param string $context Context.
	 * @return array{blocks:array<string,mixed>,styles:array<string,mixed>}
	 */
	public static function build( $context ) {
		$b = new self();
		$b->start();
		$two_col_mobile = array(
			'tablet' => 'grid-template-columns:1fr;',
		);
		switch ( $context ) {
			case 'single_product':
				$b->el( 'body', 'breadcrumb' );
				$b->el( 'body', 'store-notices' );
				$grid = $b->div( 'body', 'display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start;', $two_col_mobile, 'Product' );
				$b->el( $grid, 'product-gallery' );
				$summary = $b->div( $grid, 'display:flex;flex-direction:column;gap:16px;', array(), 'Summary' );
				$b->el( $summary, 'product-title', array( 'html_tag' => 'h1' ) );
				$b->el( $summary, 'product-rating' );
				$b->el( $summary, 'product-price' );
				$b->el( $summary, 'product-short-description' );
				$b->el( $summary, 'product-add-to-cart' );
				$b->el( $summary, 'product-meta' );
				$b->el( 'body', 'product-tabs' );
				$b->el( 'body', 'product-upsells' );
				$b->el( 'body', 'product-related' );
				break;
			case 'shop':
			case 'product_archive':
			case 'product_search':
				$b->el( 'body', 'breadcrumb' );
				$b->el( 'body', 'archive-title' );
				$b->el( 'body', 'archive-description' );
				$b->el( 'body', 'store-notices' );
				$layout  = $b->div( 'body', 'display:grid;grid-template-columns:260px 1fr;gap:40px;align-items:start;', $two_col_mobile, 'Shop layout' );
				$sidebar = $b->div( $layout, 'display:flex;flex-direction:column;gap:24px;', array(), 'Filters' );
				$b->el( $sidebar, 'product-search' );
				$b->el( $sidebar, 'shop-filter', array( 'filter_type' => 'active' ) );
				$b->el( $sidebar, 'shop-filter', array( 'filter_type' => 'category' ) );
				$b->el( $sidebar, 'shop-filter', array( 'filter_type' => 'price' ) );
				$b->el( $sidebar, 'shop-filter', array( 'filter_type' => 'rating' ) );
				$b->el( $sidebar, 'shop-filter', array( 'filter_type' => 'on_sale' ) );
				$main = $b->div( $layout, 'display:flex;flex-direction:column;gap:24px;min-width:0;', array(), 'Products' );
				$bar  = $b->div( $main, 'display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;', array(), 'Toolbar' );
				$b->el( $bar, 'shop-result-count' );
				$b->el( $bar, 'shop-ordering' );
				$b->el(
					$main,
					'products',
					array(
						'source'  => 'current_query',
						'columns' => 3,
					)
				);
				$b->el( $main, 'shop-pagination' );
				break;
			case 'cart':
				$b->el( 'body', 'store-notices' );
				$b->el( 'body', 'cart' );
				$b->el( 'body', 'cart-cross-sells' );
				break;
			case 'cart_empty':
				$b->el( 'body', 'store-notices' );
				$b->el( 'body', 'cart' );
				$b->el(
					'body',
					'products',
					array(
						'source' => 'best_selling',
						'limit'  => 4,
					)
				);
				break;
			case 'checkout':
			case 'order_received':
				$b->el( 'body', 'store-notices' );
				$b->el( 'body', 'checkout' );
				break;
			case 'my_account':
			case 'my_account_login':
				$b->el( 'body', 'store-notices' );
				$b->el( 'body', 'my-account' );
				break;
		}
		return $b->result();
	}
}
