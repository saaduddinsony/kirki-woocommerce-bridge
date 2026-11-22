# Changelog

## 1.0.0 (2026-11-22)

First release.

- 36 Kirki elements for WooCommerce: single product parts, product grid, shop filters, sorting, pagination, search, category grid, archive title and description, breadcrumbs, notices, mini cart, cart, cart totals, cross-sells, checkout, my account and order tracking.
- Starter layouts and template rules for 10 WooCommerce contexts, with conditions.
- Kirki dynamic content: 33 product fields and 11 store fields. WooCommerce collections for Kirki's Collection element.
- Shortcodes `[kirki_woo_element]`, `[kirki_woo_mini_cart]` and `[kirki_woo]`. `[kecom_mini_cart]` is served from WooCommerce when Kirki eCommerce is not active.
- REST API `kirki-woo/v1` and WordPress Abilities (`kirki-woo/*`) with 21 operations, documented in `docs/API.md`.
- Coexistence with Kirki eCommerce:
  - Everything is namespaced.
  - Page conflicts are detected.
  - Includes a fix for Kirki eCommerce 1.0.2 overwriting `window._`, which broke WooCommerce variable products.
- HPOS, cart/checkout blocks and product block editor compatibility declared.
