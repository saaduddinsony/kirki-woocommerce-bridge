# Kirki WooCommerce Bridge API (REST and WordPress Abilities)

This API lets AI agents, the Kirki MCP and scripts inspect the store and build Kirki designs for WooCommerce. Every operation is defined once, in `KirkiWoo\Operations::all()`. That one table registers:

1. a **REST route** under `/wp-json/kirki-woo/v1`, and
2. a **WordPress ability** named `kirki-woo/<operation>` in the `kirki-woo` category (WordPress 6.9+ / Abilities API). Each ability has an input schema, a permission callback and annotations.

Because both come from the same table, the REST API and the abilities always have the same inputs, permissions and results. Plugins can add or adjust operations with the `kirki_woo_operations` filter.

- **API version:** `schema_version: 1`, reported by `get-status`. Fields are only ever added within a schema version. Breaking changes would bump the version and the REST namespace (`kirki-woo/v2`).

## Quick reference

| Ability | REST | Capability | Annotations |
|---|---|---|---|
| `kirki-woo/get-status` | `GET /status` | `edit_posts` | readonly |
| `kirki-woo/list-elements` | `GET /elements` | `edit_posts` | readonly |
| `kirki-woo/get-element` | `GET /elements/{name}` | `edit_posts` | readonly |
| `kirki-woo/render-element` | `POST /render` | `edit_posts` | readonly |
| `kirki-woo/build-block` | `POST /blocks` | `edit_posts` | readonly |
| `kirki-woo/list-dynamic-fields` | `GET /dynamic-data/fields` | `edit_posts` | readonly |
| `kirki-woo/resolve-dynamic-field` | `POST /dynamic-data/resolve` | `edit_posts` | readonly |
| `kirki-woo/get-settings` | `GET /settings` | `manage_woocommerce` | readonly |
| `kirki-woo/update-settings` | `PUT /settings` | `manage_woocommerce` | idempotent |
| `kirki-woo/list-template-contexts` | `GET /templates/contexts` | `edit_posts` | readonly |
| `kirki-woo/list-templates` | `GET /templates` | `manage_woocommerce` | readonly |
| `kirki-woo/create-template` | `POST /templates` | `manage_woocommerce` | — |
| `kirki-woo/resolve-template` | `POST /templates/resolve` | `manage_woocommerce` | readonly |
| `kirki-woo/get-template` | `GET /templates/{id}` | `manage_woocommerce` | readonly |
| `kirki-woo/update-template` | `PUT /templates/{id}` | `manage_woocommerce` | idempotent |
| `kirki-woo/delete-template` | `DELETE /templates/{id}` | `manage_woocommerce` | destructive, idempotent |
| `kirki-woo/list-designs` | `GET /designs` | `manage_woocommerce` | readonly |
| `kirki-woo/create-design` | `POST /designs` | `manage_woocommerce` | — |
| `kirki-woo/get-design` | `GET /designs/{id}` | `manage_woocommerce` | readonly |
| `kirki-woo/add-design-elements` | `POST /designs/{id}/elements` | `manage_woocommerce` | — |
| `kirki-woo/delete-design` | `DELETE /designs/{id}` | `manage_woocommerce` | destructive |

"Templates" here are **rules**: they say which Kirki design is used for a WooCommerce context. "Designs" are the Kirki posts (`kirki_template`, or Kirki-edited pages) that hold the layout.

## Authentication and permissions

- **REST.** Use a logged-in cookie with an `X-WP-Nonce: <wp_rest nonce>` header, or [application passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) (HTTP Basic). Anonymous requests get `401 rest_forbidden`. Signed-in users without the capability get `403 rest_forbidden`.
- **Capabilities.**
  - `edit_posts` covers reading and previewing: elements, rendering, dynamic data and contexts.
  - `manage_woocommerce` covers anything that changes the store or reads its configuration: settings, rules and designs.
  - Design edits also need edit rights on that post. `delete-design` needs delete rights.
- **Abilities.** The same capability checks run in each ability's `permission_callback`. Calling `wp_get_ability( 'kirki-woo/list-templates' )->execute()` as a user without permission returns a `WP_Error`.
- **Missing dependencies.** If WooCommerce or Kirki is not active, only `GET /status` is registered. It reports `ready: false` and which dependency is missing.

## Calling it

### REST

```bash
# Status (application password)
curl -u admin:APP-PASSWORD https://example.test/wp-json/kirki-woo/v1/status

# Render a Products Grid preview
curl -u admin:APP-PASSWORD -H 'Content-Type: application/json' \
  -d '{"name":"products","settings":{"source":"on_sale","columns":3,"limit":6}}' \
  https://example.test/wp-json/kirki-woo/v1/render

# Create a single-product design from the starter layout and a rule for one category
curl -u admin:APP-PASSWORD -H 'Content-Type: application/json' \
  -d '{"context":"single_product","title":"Hoodies product page","create_rule":false}' \
  https://example.test/wp-json/kirki-woo/v1/designs
curl -u admin:APP-PASSWORD -H 'Content-Type: application/json' \
  -d '{"context":"single_product","design_id":123,"priority":50,"conditions":{"product_cat":[18]}}' \
  https://example.test/wp-json/kirki-woo/v1/templates
```

### Abilities API, in PHP

```php
$ability = wp_get_ability( 'kirki-woo/render-element' );
$result  = $ability->execute( array( 'name' => 'product-price', 'product_id' => 42 ) );
if ( is_wp_error( $result ) ) { /* ... */ }
echo $result['html'];
```

### Abilities API, over REST (WordPress core routes)

- `GET /wp-json/wp-abilities/v1/abilities?category=kirki-woo` lists the abilities with their input and output schemas and their annotations.
- `/wp-json/wp-abilities/v1/abilities/kirki-woo/<operation>/run` runs one. Core picks the HTTP method from the annotations:
  - **readonly** abilities use `GET`, with input as query args, e.g. `?input[name]=products`.
  - **destructive + idempotent** abilities use `DELETE` (`delete-template`).
  - **everything else** uses `POST`, with a JSON body `{"input":{...}}`. That includes `delete-design`, which is destructive but not idempotent.

## Responses and errors

Successful responses are JSON objects; the shapes are described below. `create-template` and `create-design` return `201` over REST.

Errors use the standard WordPress format, `{"code": "...", "message": "...", "data": {"status": 400}}`:

| Code | Status | When |
|---|---|---|
| `rest_forbidden` | 401 / 403 | Not signed in / missing capability or post permission |
| `rest_invalid_param`, `rest_missing_callback_param` | 400 | Input fails the JSON schema |
| `kirki_woo_unknown_element` | 404 | Unknown element name or slug |
| `kirki_woo_invalid_settings` | 400 | Element settings invalid (strict render, build-block, add-design-elements). The message lists every problem, e.g. `columns: expected integer between 1 and 6; nope: unknown setting` |
| `kirki_woo_unknown_setting` | 400 | update-settings with an unknown key |
| `kirki_woo_unknown_field` | 400 | Unknown dynamic data field |
| `kirki_woo_invalid_product` | 400 / 404 | Product id is not a product |
| `kirki_woo_invalid_context` | 400 | Unknown template context, or a condition not allowed for the context |
| `kirki_woo_invalid_term` | 400 | Term id not in the expected taxonomy |
| `kirki_woo_invalid_design` | 400 | design_id is not a Kirki design |
| `kirki_woo_invalid_parent` / `kirki_woo_invalid_elements` | 400 | add-design-elements parent missing / no elements |
| `kirki_woo_not_found` | 404 | Rule or design not found |
| `kirki_woo_delete_failed` | 500 | WordPress could not delete the design |

## Main objects

**Element** (`list-elements`, `get-element`):

```json
{
  "name": "kirki-woo-product-title", "slug": "product-title", "title": "Product Title",
  "description": "The product name.", "group": "product", "context": "product", "tag": "div",
  "source": "kirki-woo",
  "settings": [ { "key": "html_tag", "type": "select", "label": "HTML tag", "options": { "h1": "H1" }, "default": "h1" } ],
  "schema": { "type": "object", "properties": { } }
}
```

- Setting types: `toggle` (boolean), `number` (integer with `min`/`max`), `product` (product id; `0` = current or preview product), `select` (one of `options`), and `text`.
- Each element's settings JSON schema is in `schema`.
- Full list: [ELEMENTS.md](ELEMENTS.md).

**Kirki block** (`build-block`, and what `add-design-elements` inserts):

```json
{ "block": { "id": "kwizv4w08lq", "name": "kirki-woo-product-price", "title": "Product Price",
  "source": "kirki-woo", "parentId": "body", "children": [], "styleIds": [],
  "visibility": true, "collapse": false,
  "properties": { "tag": "div", "attributes": [], "settings": { "product_id": 0 } } } }
```

This is Kirki's own block format. You can add the block to a design's `blocks` map, under a parent's `children`, with Kirki's tools, or let `add-design-elements` do it.

**Render result** (`render-element`):

```json
{ "name": "kirki-woo-product-price", "settings": { "product_id": 0 }, "product_id": 17,
  "html": "<div class=\"kwi kwi-product-price\" data-kwi-element=\"product-price\">…</div>",
  "assets": ["base"] }
```

- `assets` lists the bridge style or script bundles that the markup needs (`base`, `gallery`, `mini_cart`, …).
- With `strict: true`, invalid settings return `400`. Without it, they are coerced to the nearest valid value.

**Template rule** (`list-templates`, `get-template`, `create-template`, `update-template`):

```json
{ "id": "ruu1xuvlsd1", "title": "WooCommerce – Single product", "context": "single_product",
  "design_id": 46, "conditions": { "product_cat": [17], "user_state": "logged_in" },
  "priority": 10, "enabled": true,
  "created": "2026-11-21T23:27:01+00:00", "updated": "2026-11-21T23:27:01+00:00" }
```

Matching rules:

- Conditions are ANDed, and list values match if any element matches.
- The highest `priority` wins, then the rule with the most conditions.
- `resolve-template` returns the facts used for matching and the winning rule, with the design attached.

**Design** (`list-designs`, `create-design`, `get-design`):

```json
{ "id": 46, "title": "WooCommerce – Single product", "post_type": "kirki_template",
  "status": "publish", "editor_url": "https://example.test/?p=46&action=kirki",
  "modified": "2026-11-21T23:27:01+00:00" }
```

- `create-design` returns `{ "design": {…}, "rule": {…}|null }`.
- `get-design` adds `blocks` (a flat list of `{id, name, title, source, parentId, children, settings?}`), `rules`, and `has_unpublished_changes`.
- `add-design-elements` writes the **published** version.
  - If `has_unpublished_changes` is `true`, someone has unpublished edits in the Kirki editor. Publishing those edits from Kirki later replaces the published version, so tell the user, or have them publish or discard first.
- `delete-design`:
  - Only deletes `kirki_template` posts, never pages.
  - Moves the design to the trash unless `force` is set.
  - Removes the rules that use the design, and returns their ids in `removed_rules`.

**Status** (`get-status`):

```json
{ "plugin": "kirki-woocommerce-bridge", "version": "1.0.0", "schema_version": 1,
  "api_namespace": "kirki-woo/v1", "element_source": "kirki-woo", "element_prefix": "kirki-woo-",
  "ready": true,
  "dependencies": { "woocommerce": true, "kirki": true, "kirki_pro": true, "kirki_ecommerce": false },
  "versions": { "plugin": "1.0.0", "woocommerce": "11.3.1", "kirki": "6.4.3", "kirki_pro": "6.4.3", "kirki_ecommerce": null, "wordpress": "7.1.5", "php": "8.3.26" },
  "features": { "hpos": true, "cart_checkout_blocks": true, "abilities_api": true },
  "counts": { "elements": 36, "product_fields": 33, "store_fields": 11, "template_rules": 8 },
  "compat": { "kirki_ecommerce_active": false, "kecom_shortcode_aliases": ["kecom_mini_cart"], "underscore_fix": false, "page_conflicts": [] } }
```

## Suggested MCP tools (for Kirki MCP)

Each MCP tool can map one-to-one onto an ability:

- **Discovery.** Call the core `GET /wp-abilities/v1/abilities?category=kirki-woo`. It returns names, descriptions, `input_schema`, `output_schema` and `meta.annotations`. Map `readonly` → `readOnlyHint`, `destructive` → `destructiveHint` and `idempotent` → `idempotentHint`.
- **Transport.** Either run the ability through `/wp-abilities/v1/abilities/<name>/run`, or call the plugin route in `meta.rest` (`{method, route}`) under `/wp-json/kirki-woo/v1`.
- **Feature detection.** Call `kirki-woo/get-status` first. If `ready` is false, report the missing dependency. If the route returns 404, the bridge isn't installed.

Typical agent flows:

1. **"Build me a product page."**
   1. `list-template-contexts`
   2. `create-design` with `{context: "single_product", starter: true}`. This gives a complete layout and a rule.
   3. Optionally `add-design-elements` to add extra parts, and `update-template` to set conditions.
   4. `resolve-template` with `{context: "single_product", object_id: <product>}` to check which rule applies.
2. **"Add a sale grid to the shop page."**
   1. `list-designs` or `list-templates?context=shop` to find the design.
   2. `get-design` to choose a `parent_id`.
   3. `add-design-elements` with `[{name: "products", settings: {source: "on_sale", columns: 4}}]`.
3. **Preview.** Call `render-element` before inserting, to show the user the HTML.
4. **Dynamic data.** Call `list-dynamic-fields`, then `resolve-dynamic-field` (`scope: product|store`). In Kirki, bind with `dynamicContent: {type: "kirki_woo_product", value: "<field>"}`.

## Operation reference

Generated from `KirkiWoo\Operations::all()` (the same table registers the REST routes and the abilities, so they cannot drift apart).

### `kirki-woo/get-status`

Plugin, WooCommerce and Kirki versions, readiness, features and counts.

- REST: `GET /wp-json/kirki-woo/v1/status`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

No input.

### `kirki-woo/list-elements`

Every WooCommerce element available in the Kirki builder, with settings and JSON schema.

- REST: `GET /wp-json/kirki-woo/v1/elements`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `group` | string (`product`, `shop`, `cart`, `account`, `general`) | no |  |

### `kirki-woo/get-element`

One element definition with its settings schema.

- REST: `GET /wp-json/kirki-woo/v1/elements/{name}`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `name` | string | yes | Element name (kirki-woo-product-title) or slug (product-title). |

### `kirki-woo/render-element`

Server-render an element to HTML (the same output the frontend uses). Used for editor previews.

- REST: `POST /wp-json/kirki-woo/v1/render`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `name` | string | yes | Element name (kirki-woo-product-title) or slug (product-title). |
| `settings` | object | no | Element settings; see the element schema. |
| `product_id` | integer | no | Product to render for; 0 uses the preview product. |
| `strict` | boolean | no | Reject unknown or invalid settings instead of coercing them. |

### `kirki-woo/build-block`

Return a validated Kirki element node ready to insert into Kirki page data (does not save anything).

- REST: `POST /wp-json/kirki-woo/v1/blocks`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `name` | string | yes | Element name (kirki-woo-product-title) or slug (product-title). |
| `settings` | object | no | Element settings; see the element schema. |
| `parent_id` | string | no | Default `"body"`. |
| `id` | string | no |  |

### `kirki-woo/list-dynamic-fields`

Product and store fields usable as Kirki dynamic content and in the [kirki_woo] shortcode.

- REST: `GET /wp-json/kirki-woo/v1/dynamic-data/fields`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

No input.

### `kirki-woo/resolve-dynamic-field`

Value of a product or store field for a product (or the preview product).

- REST: `POST /wp-json/kirki-woo/v1/dynamic-data/resolve`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `field` | string | yes |  |
| `scope` | string (`product`, `store`) | no | Default `"product"`. |
| `product_id` | integer | no |  |
| `meta` | string | no | Attribute taxonomy / meta key for the attribute and meta fields. |

### `kirki-woo/get-settings`

Current settings and their schema.

- REST: `GET /wp-json/kirki-woo/v1/settings`
- Capability: `manage_woocommerce`
- Annotations: readonly, idempotent

No input.

### `kirki-woo/update-settings`

Change one or more settings. Unknown keys are rejected.

- REST: `PUT /wp-json/kirki-woo/v1/settings`
- Capability: `manage_woocommerce`
- Annotations: idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `settings` | object | yes | Partial settings object. |

### `kirki-woo/list-template-contexts`

WooCommerce page contexts a Kirki design can be assigned to, and the conditions each supports.

- REST: `GET /wp-json/kirki-woo/v1/templates/contexts`
- Capability: `edit_posts`
- Annotations: readonly, idempotent

No input.

### `kirki-woo/list-templates`

Template rules in match order (priority, then specificity).

- REST: `GET /wp-json/kirki-woo/v1/templates`
- Capability: `manage_woocommerce`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `context` | string (`single_product`, `shop`, `product_archive`, `product_search`, `cart`, `cart_empty`, `checkout`, `order_received`, `my_account`, `my_account_login`) | no | WooCommerce page context. |

### `kirki-woo/create-template`

Assign an existing Kirki design to a WooCommerce context with optional conditions.

- REST: `POST /wp-json/kirki-woo/v1/templates`
- Capability: `manage_woocommerce`
- Annotations: none (writes)

| Input | Type | Required | Description |
|---|---|---|---|
| `title` | string | no |  |
| `context` | string (`single_product`, `shop`, `product_archive`, `product_search`, `cart`, `cart_empty`, `checkout`, `order_received`, `my_account`, `my_account_login`) | yes | WooCommerce page context. |
| `design_id` | integer | yes | kirki_template / page post id that holds the Kirki design. |
| `conditions` | object | no | Conditions allowed for the context; see template contexts. |
| `priority` | integer | no |  |
| `enabled` | boolean | no |  |

### `kirki-woo/resolve-template`

Dry-run the matcher for a context and product / term id.

- REST: `POST /wp-json/kirki-woo/v1/templates/resolve`
- Capability: `manage_woocommerce`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `context` | string (`single_product`, `shop`, `product_archive`, `product_search`, `cart`, `cart_empty`, `checkout`, `order_received`, `my_account`, `my_account_login`) | yes | WooCommerce page context. |
| `object_id` | integer | no | Product id (single_product) or term id (product_archive). |
| `account_endpoint` | string | no |  |
| `logged_in` | boolean | no |  |

### `kirki-woo/get-template`

One template rule with its design.

- REST: `GET /wp-json/kirki-woo/v1/templates/{id}`
- Capability: `manage_woocommerce`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Template rule id. |

### `kirki-woo/update-template`

Partially update a rule. conditions replaces the whole conditions object.

- REST: `PUT /wp-json/kirki-woo/v1/templates/{id}`
- Capability: `manage_woocommerce`
- Annotations: idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Template rule id. |
| `title` | string | no |  |
| `context` | string (`single_product`, `shop`, `product_archive`, `product_search`, `cart`, `cart_empty`, `checkout`, `order_received`, `my_account`, `my_account_login`) | no | WooCommerce page context. |
| `design_id` | integer | no | kirki_template / page post id that holds the Kirki design. |
| `conditions` | object | no | Conditions allowed for the context; see template contexts. |
| `priority` | integer | no |  |
| `enabled` | boolean | no |  |

### `kirki-woo/delete-template`

Delete a rule. The Kirki design post is kept.

- REST: `DELETE /wp-json/kirki-woo/v1/templates/{id}`
- Capability: `manage_woocommerce`
- Annotations: destructive, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `id` | string | yes | Template rule id. |

### `kirki-woo/list-designs`

Kirki templates and pages that can be assigned to WooCommerce contexts.

- REST: `GET /wp-json/kirki-woo/v1/designs`
- Capability: `manage_woocommerce`
- Annotations: readonly, idempotent

No input.

### `kirki-woo/create-design`

Create a kirki_template post (optionally seeded with a starter layout) and, by default, a rule assigning it.

- REST: `POST /wp-json/kirki-woo/v1/designs`
- Capability: `manage_woocommerce`
- Annotations: none (writes)

| Input | Type | Required | Description |
|---|---|---|---|
| `context` | string (`single_product`, `shop`, `product_archive`, `product_search`, `cart`, `cart_empty`, `checkout`, `order_received`, `my_account`, `my_account_login`) | yes | WooCommerce page context. |
| `title` | string | no |  |
| `starter` | boolean | no | Default `true`. |
| `create_rule` | boolean | no | Default `true`. |

### `kirki-woo/get-design`

A design with its published element tree (ids, names, parents, children, settings of WooCommerce elements) and the rules that use it.

- REST: `GET /wp-json/kirki-woo/v1/designs/{id}`
- Capability: `manage_woocommerce`
- Annotations: readonly, idempotent

| Input | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes |  |

### `kirki-woo/add-design-elements`

Insert one or more elements (validated against their schemas) into the published version of a design, under parent_id at position (append by default). Existing elements are kept.

- REST: `POST /wp-json/kirki-woo/v1/designs/{id}/elements`
- Capability: `manage_woocommerce`
- Annotations: none (writes)

| Input | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes |  |
| `elements` | array | yes |  |
| `parent_id` | string | no | Element id to insert into (see get-design). Default `"body"`. |
| `position` | integer | no | Index among the parent's children; -1 appends. Default `-1`. |

### `kirki-woo/delete-design`

Move a kirki_template design to the trash (or delete it with force) and remove the rules that use it. Pages are never deleted.

- REST: `DELETE /wp-json/kirki-woo/v1/designs/{id}`
- Capability: `manage_woocommerce`
- Annotations: destructive

| Input | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes |  |
| `force` | boolean | no | Delete permanently instead of moving to the trash. Default `false`. |

## Dynamic data fields

Product fields (`scope: product`):

| Key | Label | Format |
|---|---|---|
| `id` | ID | number |
| `title` | Name | text |
| `permalink` | Product link | url |
| `price_html` | Price (formatted, with sale) | html |
| `price` | Active price | text |
| `regular_price` | Regular price | text |
| `sale_price` | Sale price | text |
| `price_raw` | Active price (number) | number |
| `discount_percentage` | Discount % | number |
| `sale_ends` | Sale end date | text |
| `sku` | SKU | text |
| `stock_status` | Stock status | text |
| `stock_quantity` | Stock quantity | number |
| `availability` | Availability text | text |
| `average_rating` | Average rating | number |
| `rating_count` | Rating count | number |
| `review_count` | Review count | number |
| `rating_html` | Rating stars | html |
| `short_description` | Short description | html |
| `description` | Description | html |
| `type` | Product type | text |
| `categories` | Categories | text |
| `tags` | Tags | text |
| `attribute` | Attribute (set slug in Meta) | text |
| `weight` | Weight | text |
| `dimensions` | Dimensions | text |
| `total_sales` | Total sales | number |
| `on_sale` | On sale label | text |
| `featured` | Featured (yes/no) | text |
| `image_url` | Image URL | url |
| `add_to_cart_url` | Add to cart URL | url |
| `add_to_cart_text` | Add to cart text | text |
| `meta` | Custom field (set key in Meta) | text |

Store fields (`scope: store`):

| Key | Label | Format |
|---|---|---|
| `cart_count` | Cart item count | number |
| `cart_subtotal` | Cart subtotal | html |
| `cart_total` | Cart total | html |
| `cart_url` | Cart URL | url |
| `checkout_url` | Checkout URL | url |
| `shop_url` | Shop URL | url |
| `account_url` | My account URL | url |
| `currency` | Currency code | text |
| `currency_symbol` | Currency symbol | text |
| `customer_name` | Customer first name | text |
| `free_shipping_remaining` | Amount left for free shipping | html |

## Template contexts and conditions

| Context | Label | Conditions |
|---|---|---|
| `single_product` | Single product | `product_ids`, `exclude_product_ids`, `product_cat`, `product_tag`, `product_type`, `stock_status`, `on_sale`, `featured`, `user_state` |
| `shop` | Shop page | `user_state` |
| `product_archive` | Product category, tag and attribute archives | `taxonomy`, `term_ids`, `user_state` |
| `product_search` | Product search results | `user_state` |
| `cart` | Cart (with items) | `user_state` |
| `cart_empty` | Empty cart | `user_state` |
| `checkout` | Checkout | `user_state` |
| `order_received` | Order received (thank you) | `user_state` |
| `my_account` | My account (signed in) | `account_endpoint` |
| `my_account_login` | My account login / register (signed out) | — |

| Condition | Type | Description |
|---|---|---|
| `product_ids` | integer[] | Only these products. |
| `exclude_product_ids` | integer[] | Never these products. |
| `product_cat` | integer[] | Products in any of these categories (child categories included). |
| `product_tag` | integer[] | Products with any of these tags. |
| `product_type` | string[] (`simple`, `grouped`, `external`, `variable`) | Product types such as simple or variable. |
| `stock_status` | string[] (`instock`, `outofstock`, `onbackorder`) | Stock statuses. |
| `on_sale` | boolean | true = only on-sale products, false = only full-price products. |
| `featured` | boolean | true = only featured products, false = only non-featured. |
| `taxonomy` | string[] | Archive taxonomies, e.g. product_cat, product_tag, pa_color. |
| `term_ids` | integer[] | Archive terms (child terms included). |
| `account_endpoint` | string[] | My account endpoints: dashboard, orders, view-order, downloads, edit-address, edit-account, payment-methods, add-payment-method. |
| `user_state` | string (`any`, `logged_in`, `logged_out`) | Visitor sign-in state. |

## Settings

| Key | Type | Default | Description |
|---|---|---|---|
| `preview_product_id` | integer | `0` | Product used to preview product elements in the Kirki editor and on template previews. 0 picks the newest published product. |
| `load_styles` | boolean | `true` | Load the small base stylesheet for WooCommerce elements. |
| `mini_cart_auto_open` | boolean | `true` | Open the mini cart drawer after a product is added with AJAX. |
| `enable_shop_filters` | boolean | `true` | Apply Kirki filter element query arguments (price, rating, stock, sale, attributes, categories) to the main shop query. |
| `card_image_size` | string | `"woocommerce_thumbnail"` | Image size used by product cards. |
| `template_debug` | boolean | `false` | Print an HTML comment naming the template rule used for each WooCommerce page (administrators only). |
| `respect_kirki_native` | boolean | `true` | When a native Kirki template already targets a product or product archive, let it win over rules from this plugin. |
