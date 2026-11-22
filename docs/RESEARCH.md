# Research: Kirki, Kirki Pro, Kirki eCommerce and WooCommerce

Research done in November 2026 on Kirki 6.4.3, Kirki Pro 6.4.3, Kirki eCommerce 1.0.2 and WooCommerce 11.3.1, running on WordPress 7.1.5. These notes explain what already existed and why this plugin is built the way it is.

## What Kirki already does for WooCommerce

**Almost nothing.**

- Kirki 6.4.3 and Kirki Pro 6.4.3 contain no WooCommerce code. A search of both plugins (PHP and the editor bundles) finds "woocommerce" only in the changelog line `Fix: Broken woocommerce catalog product title`.
- Kirki has no product elements, no cart or checkout elements, and no WooCommerce dynamic content. Its template conditions don't cover WooCommerce contexts such as the cart, checkout, account endpoints, or the empty cart.
- Kirki templates can target the `product` post type and its archives (that is how the catalogue-title fix applies). However, a template only gets standard post fields, not prices, stock, add to cart or galleries.
- There is no official "Kirki for WooCommerce" add-on.
- Kirki Pro runs **without a license key**. Every builder feature this plugin relies on works unlicensed. Pro adds its own editor bundle, which registers third-party elements through the same API.

## Kirki eCommerce 1.0.2

Kirki eCommerce (Themeum, wordpress.org slug `kirki-ecommerce`) is a **separate store system**, not a WooCommerce integration:

- **Catalogue:** its own products, variants, inventory, brands and collections, stored in custom tables (`{prefix}kirki_ecommerce_*`). It registers no post types.
- **Store operations:** its own cart, checkout, payments (PayPal, Stripe, cash on delivery, bank transfer, cheque), orders, customers, coupons, shipping, taxes, multi-currency, emails and analytics.
- **Storefront:** server-rendered PHP views for the shop (sidebar filters, breadcrumb, product cards), single product, cart, checkout (contact info, address picker, shipping and payment methods, coupon, consents), order success and failure, order tracking, and the account area (dashboard, orders, addresses, account details, wishlist). The views are driven by a bundled Alpine-style script, `assets/js/site.js`.
- **Pages:** it creates its own *Shop*, *Cart*, *Checkout* and *Account* pages. If WooCommerce pages already exist, WordPress makes the slugs unique (`/shop-2/`, `/cart-2/`, `/checkout-2/`, `/account/`).
- **Shortcodes and blocks:** `[kecom_mini_cart]`, `[kecom_currency_switcher]`, and a Gutenberg mini-cart block.
- **Migration:** a one-way WooCommerce → Kirki eCommerce importer.
- **Builder elements:** none. Kirki eCommerce adds no elements to the Kirki editor and no Kirki dynamic content.

So Kirki users have two options today. They can move their store off WooCommerce into Kirki eCommerce, or keep WooCommerce and lose the builder on every store page.

## Conflicts found when Kirki eCommerce and WooCommerce run together

1. **The global `_` is overwritten (real bug, fixed here).**
   - Kirki eCommerce's `site.js` is a classic script whose minified top level declares `var _=[]`. That replaces `window._` (Underscore) on every front-end page.
   - WooCommerce's variable product form uses `wp.template()`, which calls `_.template()`. With Kirki eCommerce active, choosing a variation throws `_.template is not a function`. The variation price never appears and the product can't be added to the cart.
   - Our end-to-end test caught this.
   - The bridge now copies the missing Underscore helpers onto Kirki eCommerce's array after its script runs (`Assets::underscore_compat()`). WooCommerce works again, and Kirki eCommerce still sees its own array.
2. **Duplicate store pages.** These are confusing but harmless: WordPress keeps the slugs unique. The bridge warns when the *same* page is assigned to both plugins (`Compat::page_conflicts()`).
3. **No identifier overlaps.**
   - Kirki eCommerce uses `kecom`, `kirki_ecommerce` and `kirki-ecommerce`. This plugin uses `kirki-woo`, `kirki_woo`, `KirkiWoo` and `kwi`.
   - The bridge registers its `[kecom_mini_cart]` alias only when Kirki eCommerce is **not** active, and only if nothing else owns the tag.

## How Kirki can be extended (what the bridge relies on)

- **Elements**
  - Third-party elements register in the editor with `kirki.hooks` / the element registry. They use `source` (ours: `kirki-woo`), a category, an icon, controls, constraints and settings, which are stored at `properties.settings`.
  - On the front end they render through the `kirki_element_generator_{source}` filter. Whole designs render with `kirki_html_generator`.
- **Collections.** `kirki_external_collection_options` and `kirki_collection_{name}` let Kirki's native Collection element loop over external data. The bridge adds *WooCommerce* collections: related, upsells, cross-sells, cart items, featured, on sale, best selling, top rated and recently viewed.
  - Kirki title-cases category names, so the bridge's categories are named "Woo single product" and so on.
  - Registered elements appear in Insert-panel search, and under *Base Elements* when **View → Show base elements** is on.
- **Canvas.** The editor canvas is a `srcdoc` iframe (`#iframe-kirki-canvas`). It loads only Kirki CSS and theme assets, so the bridge injects the WooCommerce CSS and its own CSS, and adds the `woocommerce` body classes.
- **Data.**
  - Page and template data is stored in post meta `kirki` (`{blocks}`), plus style-block metas.
  - Kirki keeps stage versions. The editor's **Save** writes a stage, and **Publish** copies it to the published version in `wp_postmeta`.
  - `\Kirki\App\Supports\Facades\Page` (a `__callStatic` facade) exposes `save_blocks()` and related methods. Passing `false` as the version writes the published version.
- **Templates.**
  - `kirki_template` posts are Kirki templates. The post type is not registered with `register_post_type()`, so capability checks fall back to `edit_others_posts`.
  - The template editor needs at least one `kirki_template_conditions` entry whose `to` is a string. The bridge's designs carry one inert condition, and the bridge's own rules decide where a design is used.
  - On block themes, Kirki's full-canvas template prints a bare `<body>`, so the bridge adds `body_class()` classes back with a tiny script.
- **Dynamic content.** The `kirki_dynamic_content_fields` and `kirki_dynamic_content` filters add new content types (ours: `kirki_woo_product`, `kirki_woo_store`) for text and link bindings.
- **Kirki's AppsService.** It calls a remote app catalogue, which returns HTTP 500 when the site is offline (`/kirki/v1/app-l…`). This is unrelated to this plugin.

## WooCommerce integration decisions

- **Render through WooCommerce.** Wherever possible, elements render through WooCommerce's own templates and functions (`woocommerce_template_single_add_to_cart()`, `wc_get_template()`, the cart, checkout and account shortcodes or blocks). That keeps extension hooks, gateways and theme overrides working.
- **Product grids** use `WC_Shortcode_Products`. The bridge subclasses it because `get_query_results()` is protected.
- **Shop filters** modify the main product query (`woocommerce_product_query`) with `kwi_*` query args, which keeps pagination, sorting and result counts consistent.
- **HPOS** compatibility is declared. Order data is only read through WooCommerce APIs.
- **Cart and checkout blocks.** Compatibility is declared, and the Cart and Checkout elements can render either the classic shortcode or the block.
- **Fragments.** The mini cart uses WooCommerce cart fragments, so counts update after AJAX add to cart.
