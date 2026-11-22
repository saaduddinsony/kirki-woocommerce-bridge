# Elements reference

All elements are registered in the Kirki editor with the `kirki-woo` source and the `kirki-woo-` name prefix. In the Insert panel, search for an element title (for example "Product Title"), or turn on **View → Show base elements** to browse the "Woo …" categories.

Every element can also be rendered anywhere with the shortcode `[kirki_woo_element name="<slug>" setting="value"]`, or via the REST/Abilities API (`render-element`).

Settings of type `product` take a product ID; `0` means "the current product" (the product being viewed, the product in a Kirki collection/loop, or the preview product in the editor).

## Single product

### Product Title — `product-title`

The product name.

Name `kirki-woo-product-title` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `html_tag` | select | `"h1"` | HTML tag: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `p`, `span`, `div` |
| `link` | toggle | `false` | Link to product |

### Product Price — `product-price`

Price HTML including sale and variation ranges.

Name `kirki-woo-product-price` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |

### Product Image — `product-image`

Featured image of the product.

Name `kirki-woo-product-image` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `size` | select | `"woocommerce_single"` | Image size: `woocommerce_single`, `woocommerce_thumbnail`, `large`, `medium`, `full` |
| `link` | select | `"none"` | Link: `none`, `product`, `file` |
| `sale_badge` | toggle | `false` | Show sale badge |

### Product Gallery — `product-gallery`

WooCommerce gallery with zoom, lightbox and slider, switching with variations.

Name `kirki-woo-product-gallery` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `sale_badge` | toggle | `true` | Show sale badge |

### Add to Cart — `product-add-to-cart`

Add to cart form for simple, variable, grouped and external products.

Name `kirki-woo-product-add-to-cart` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `style` | select | `"form"` | Style: `form`, `button` |
| `show_quantity` | toggle | `true` | Show quantity field |
| `button_text` | text | `""` | Button text (blank = WooCommerce default) |

### Stock Status — `product-stock`

Availability text such as "In stock" or "Out of stock".

Name `kirki-woo-product-stock` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `show_in_stock` | toggle | `true` | Show when in stock |

### Product Rating — `product-rating`

Star rating and review count.

Name `kirki-woo-product-rating` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `show_count` | toggle | `true` | Show review count |
| `hide_if_empty` | toggle | `true` | Hide when there are no reviews |

### Short Description — `product-short-description`

Product short description (excerpt).

Name `kirki-woo-product-short-description` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |

### Description — `product-description`

Full product description.

Name `kirki-woo-product-description` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |

### Product Meta — `product-meta`

SKU, categories and tags.

Name `kirki-woo-product-meta` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `show_sku` | toggle | `true` | Show SKU |
| `show_categories` | toggle | `true` | Show categories |
| `show_tags` | toggle | `true` | Show tags |

### SKU — `product-sku`

Product SKU.

Name `kirki-woo-product-sku` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `label` | text | `"SKU:"` | Label |

### Product Categories / Tags — `product-terms`

Linked list of the product categories, tags or brands.

Name `kirki-woo-product-terms` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `taxonomy` | select | `"product_cat"` | Taxonomy: `product_cat`, `product_tag`, `product_brand` |
| `separator` | text | `", "` | Separator |
| `link` | toggle | `true` | Link terms |

### Product Tabs — `product-tabs`

Description, additional information and reviews tabs.

Name `kirki-woo-product-tabs` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |

### Additional Information — `product-attributes`

Attributes, weight and dimensions table.

Name `kirki-woo-product-attributes` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |

### Reviews — `product-reviews`

Review list and review form.

Name `kirki-woo-product-reviews` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |

### Related Products — `product-related`

Products related by category and tag.

Name `kirki-woo-product-related` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `limit` | number | `4` | Products (1–24) |
| `columns` | number | `4` | Columns (1–6) |
| `heading` | text | `""` | Heading (blank = default) |

### Upsells — `product-upsells`

Upsell products linked to this product.

Name `kirki-woo-product-upsells` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `limit` | number | `4` | Products (1–24) |
| `columns` | number | `4` | Columns (1–6) |
| `heading` | text | `""` | Heading (blank = default) |

### Sale Badge — `product-sale-badge`

Shown only when the product is on sale.

Name `kirki-woo-product-sale-badge` · context `product`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `product_id` | product | `0` | Product ID (0 = current product) |
| `text` | text | `""` | Text (blank = "Sale!") |
| `show_percentage` | toggle | `false` | Show discount percentage |

## Shop & archives

### Products Grid — `products`

Product loop for shops, archives and landing pages.

Name `kirki-woo-products` · context `none`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `source` | select | `"recent"` | Source: `current_query`, `recent`, `featured`, `on_sale`, `best_selling`, `top_rated`, `ids`, `related`, `upsells`, `cross_sells` |
| `limit` | number | `8` | Products per page (1–100) |
| `columns` | number | `4` | Columns (desktop) (1–6) |
| `columns_tablet` | number | `2` | Columns (tablet) (1–6) |
| `columns_mobile` | number | `1` | Columns (mobile) (1–4) |
| `orderby` | select | `"date"` | Order by: `date`, `title`, `price`, `popularity`, `rating`, `menu_order`, `rand`, `id` |
| `order` | select | `"DESC"` | Order: `DESC`, `ASC` |
| `category` | text | `""` | Category slugs (comma separated) |
| `tag` | text | `""` | Tag slugs (comma separated) |
| `ids` | text | `""` | Product IDs (comma separated) |
| `paginate` | toggle | `false` | Paginate |
| `layout` | select | `"card"` | Card layout: `card`, `woocommerce` |
| `show_image` | toggle | `true` | Image |
| `show_title` | toggle | `true` | Title |
| `show_rating` | toggle | `true` | Rating |
| `show_price` | toggle | `true` | Price |
| `show_button` | toggle | `true` | Add to cart button |
| `show_sale_badge` | toggle | `true` | Sale badge |
| `title_tag` | select | `"h3"` | Card title tag: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `p`, `span`, `div` |
| `no_results` | text | `""` | Text when nothing is found |

### Result Count — `shop-result-count`

"Showing 1–12 of 40 results".

Name `kirki-woo-shop-result-count` · context `archive`

No settings.

### Sorting — `shop-ordering`

Catalog ordering dropdown.

Name `kirki-woo-shop-ordering` · context `archive`

No settings.

### Pagination — `shop-pagination`

Pagination for the current shop query.

Name `kirki-woo-shop-pagination` · context `archive`

No settings.

### Product Filter — `shop-filter`

Filter the shop by price, category, attribute, rating, stock or sale.

Name `kirki-woo-shop-filter` · context `archive`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `filter_type` | select | `"price"` | Filter: `price`, `category`, `attribute`, `rating`, `stock`, `on_sale`, `active` |
| `attribute` | text | `""` | Attribute slug, e.g. pa_color |
| `title` | text | `""` | Title |
| `show_count` | toggle | `true` | Show counts |
| `auto_submit` | toggle | `true` | Apply on change |

### Product Search — `product-search`

Search form limited to products.

Name `kirki-woo-product-search` · context `none`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `placeholder` | text | `"Search products\u2026"` | Placeholder |
| `button_text` | text | `"Search"` | Button text |

### Category Grid — `product-categories`

Grid of product categories with images.

Name `kirki-woo-product-categories` · context `none`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `parent` | number | `0` | Parent category ID (0 = top level, -1 = all) |
| `limit` | number | `6` | Categories (1–100) |
| `columns` | number | `3` | Columns (1–6) |
| `hide_empty` | toggle | `true` | Hide empty |
| `show_count` | toggle | `true` | Show product count |
| `show_image` | toggle | `true` | Show image |

### Archive Title — `archive-title`

Shop, category, tag or search title.

Name `kirki-woo-archive-title` · context `archive`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `html_tag` | select | `"h1"` | HTML tag: `h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `p`, `span`, `div` |

### Archive Description — `archive-description`

Shop page content or term description.

Name `kirki-woo-archive-description` · context `archive`

No settings.

## Cart & checkout

### Mini Cart — `mini-cart`

Cart icon with live count, total and a drawer.

Name `kirki-woo-mini-cart` · context `none`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `show_icon` | toggle | `true` | Icon |
| `show_count` | toggle | `true` | Item count |
| `show_total` | toggle | `true` | Total |
| `behavior` | select | `"drawer"` | On click: `drawer`, `cart` |

### Cart — `cart`

The full cart: items, coupons, totals and proceed to checkout.

Name `kirki-woo-cart` · context `cart`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `mode` | select | `"classic"` | Render as: `classic`, `block` |

### Cart Totals — `cart-totals`

Subtotal, shipping, taxes and total with the checkout button.

Name `kirki-woo-cart-totals` · context `cart`

No settings.

### Cross-sells — `cart-cross-sells`

Cross-sell products for items in the cart.

Name `kirki-woo-cart-cross-sells` · context `cart`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `limit` | number | `2` | Products (1–12) |
| `columns` | number | `2` | Columns (1–6) |

### Checkout — `checkout`

Checkout form, payment methods and the order-received page.

Name `kirki-woo-checkout` · context `checkout`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `mode` | select | `"classic"` | Render as: `classic`, `block` |

## Account

### My Account — `my-account`

Login / register, dashboard, orders, addresses and account details.

Name `kirki-woo-my-account` · context `account`

No settings.

### Order Tracking — `order-tracking`

Order tracking form.

Name `kirki-woo-order-tracking` · context `none`

No settings.

## General

### Store Breadcrumb — `breadcrumb`

WooCommerce breadcrumb trail.

Name `kirki-woo-breadcrumb` · context `none`

| Setting | Type | Default | Notes |
|---|---|---|---|
| `delimiter` | text | `" / "` | Delimiter |
| `home` | text | `"Home"` | Home label |

### Store Notices — `store-notices`

Success, error and info notices such as "Added to cart".

Name `kirki-woo-store-notices` · context `none`

No settings.

