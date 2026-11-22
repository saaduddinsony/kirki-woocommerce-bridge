=== Kirki WooCommerce Bridge ===
Contributors: saaduddinsony
Tags: kirki, woocommerce, page builder, product page, checkout
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce, kirki
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Design your whole WooCommerce store in the Kirki builder: product, shop, cart, checkout and account elements, dynamic data and template rules.

== Description ==

Kirki WooCommerce Bridge lets Kirki and Kirki Pro users build every WooCommerce page in the Kirki visual builder, while WooCommerce keeps doing the selling: payments, shipping, taxes and extensions.

You can use it instead of Kirki eCommerce for stores that run on WooCommerce. It also runs safely next to Kirki eCommerce.

* 36 builder elements: product title, price, gallery, add to cart (every product type and variation), stock, rating, descriptions, meta, tabs, reviews, related products, upsells, sale badge, product grid, filters, sorting, pagination, search, category grid, breadcrumbs, notices, mini cart, cart, cart totals, cross-sells, checkout, my account and order tracking.
* Starter layouts and template rules for single product, shop, archives, search, cart, empty cart, checkout, order received, my account and login pages. Rules can match products, categories, tags, type, stock, sale state, archive terms, account endpoints or sign-in state.
* 33 product fields and 11 store fields in Kirki dynamic content, and WooCommerce collections for Kirki's Collection element.
* Shortcodes: [kirki_woo_element], [kirki_woo_mini_cart] and [kirki_woo].
* A REST API (kirki-woo/v1) and WordPress Abilities API (kirki-woo/*) for AI agents and the Kirki MCP.
* HPOS compatible, with support for the classic or block cart and checkout. Translation ready.

Documentation: https://github.com/saaduddinsony/kirki-woocommerce-bridge

== Installation ==

1. Install and activate WooCommerce and Kirki (Kirki Pro is optional).
2. Upload and activate this plugin.
3. Go to WooCommerce > Kirki Templates, create a design for a context, then open it in Kirki.

== Frequently Asked Questions ==

= Do I need Kirki eCommerce? =

No. This plugin uses WooCommerce. If Kirki eCommerce is also active, both keep working side by side.

= Where are the elements in the Kirki editor? =

Search for an element name in the Insert panel, or turn on View > Show base elements to browse the "Woo" categories.

= My design changes do not show on the site =

In Kirki, Save stores a draft stage. Use Save > Publish.

== Changelog ==

= 1.0.0 =
* First release.
