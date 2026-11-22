# Testing

All testing runs on disposable local WordPress sites (SQLite and PHP's built-in server). Nothing is tested against a live site.

## Compatibility matrix (v1.0.0)

Environment: WordPress 7.1.5, PHP 8.3, WooCommerce 11.3.1 with HPOS on, Kirki 6.4.3, Kirki Pro 6.4.3 (unlicensed), Kirki eCommerce 1.0.2, Twenty Twenty-Five theme.

| Combination | REST smoke (36 elements, 21 abilities) | PHPUnit | Playwright E2E (5) | Front-end probes | PHP warnings / fatals |
|---|---|---|---|---|---|
| A. WooCommerce + Kirki | ✅ | ✅ 27 tests | ✅ 5/5 | ✅ | 0 |
| B. WooCommerce + Kirki + Kirki Pro | ✅ | ✅ 27 tests | ✅ 5/5 | ✅ | 0 |
| C. WooCommerce + Kirki + Kirki eCommerce | ✅ | ✅ 27 tests | ✅ 5/5 | ✅ (incl. Kirki eCommerce pages) | 0 from this plugin (1 Kirki core fatal, see below) |
| D. WooCommerce + Kirki + Kirki Pro + Kirki eCommerce | ✅ | ✅ 27 tests | ✅ 5/5 | ✅ (incl. Kirki eCommerce pages) | 0 |
| E. Kirki + Kirki eCommerce, WooCommerce inactive | — | — | — | ✅ site loads; admin notice "needs WooCommerce"; only `GET /status` registered (`ready: false`) | 0 |
| F. WooCommerce, Kirki inactive | — | — | — | ✅ store pages render with WooCommerce defaults; `ready: false` | 0 |

Each of A–D was run with `tests/bin/matrix.sh`. Front-end probes cover `/`, `/shop/`, simple and variable product pages, a category archive, `/cart/`, `/my-account/` and product search. In C and D they also cover Kirki eCommerce's own `/shop-2/`, `/cart-2/` and `/account/` pages.

### What the tests cover

- **REST smoke** (`tests/bin/smoke.php`):
  - Every element renders through `POST /render`.
  - Dynamic fields resolve, blocks build, and invalid input returns 400.
  - All abilities are registered, and anonymous requests get 401.
- **PHPUnit** (`tests/phpunit`), running inside the real site with WooCommerce and Kirki loaded:
  - Element schemas and setting coercion, and rendering of every element.
  - Dynamic data and shortcodes.
  - Template rule CRUD, validation, matching and priority.
  - REST permissions: anonymous 401, subscriber 403, editor vs `manage_woocommerce`.
  - Every operation has a route and an ability with matching annotations.
  - Design creation, element insertion (position, validation) and deletion (rules removed, pages never deleted).
  - Namespacing, and that Kirki eCommerce shortcodes are never hijacked.
- **Playwright E2E** (`tests/e2e`):
  1. Shop template with product grid, the category filter narrowing results, sorting and result count.
  2. Single product template with all parts and the gallery scripts.
  3. Add a simple product (quantity 2) and a variable product (chosen variation), then cart, classic checkout and **cash on delivery**, ending on order received. The order is created in HPOS.
  4. The REST API refuses anonymous access.
  5. **Kirki editor:**
     - All 36 elements are registered, and live previews appear in the canvas.
     - A new blank design is created over the API.
     - An element is dragged from the Insert panel into the canvas, and the design is published with **Save → Publish**.
     - The design is assigned to one product by a rule and checked on the storefront. Other products keep their design.
     - The rule and design are cleaned up.

### Issues found and fixed during testing

In run C, the only PHP error came from **Kirki core**, not this plugin:

- `Kirki\Framework\DTO::from_list(): Argument #1 must be of type array, null given` in `AppsService::get_all_apps()`. It is raised when the Kirki editor calls `/wp-json/kirki/v1/app-list…` and Kirki's remote app catalogue can't be reached, as on the offline test machine.
- It happens with or without this plugin. No frame of the stack trace is in this plugin.

- **Kirki eCommerce 1.0.2 broke WooCommerce variable products.** Its storefront script overwrites `window._`, so `wp.template()` throws `_.template is not a function`. Found by E2E 3 in combination D. Fixed by `Assets::underscore_compat()`. E2E 3 now passes in C and D.
- **Kirki eCommerce, right after WP-CLI activation:** its tables are created on the first web request. Until then, *creating any WordPress user* throws an uncaught `QueryException` from Kirki eCommerce's `user_register` hook. This is not caused by this plugin. `bin/setup-test-site.sh` sends warm-up requests to avoid it in CI.

## Running locally

```bash
composer install && npm ci
bin/setup-test-site.sh ../kirki-woo-site                 # http://127.0.0.1:8895, admin / admin-local-test (local only)
EXTRA_PLUGINS="/path/to/kirki-pro.zip /path/to/kirki-ecommerce.zip" bin/setup-test-site.sh ../kirki-woo-site-full
vendor/bin/phpcs
KIRKI_WOO_WP_LOAD=../kirki-woo-site/site/wp-load.php vendor/bin/phpunit
WP_BASE_URL=http://127.0.0.1:8895 npx playwright test       # CHROME_PATH=/usr/bin/google-chrome to use a local Chrome
tests/bin/matrix.sh ../kirki-woo-site http://127.0.0.1:8895 "A: Woo + Kirki"
```

## CI

The GitHub Actions workflow is in `ci/github-actions-ci.yml`. Copy it to `.github/workflows/ci.yml` to enable it; pushing workflow files needs a token with the `workflow` scope. Once enabled, it runs on every push and pull request:

- PHP syntax on 7.4 and 8.3, WordPress Coding Standards (phpcs) and JavaScript syntax.
- Integration in two combinations, WooCommerce only and WooCommerce + Kirki eCommerce. Each job builds a fresh WordPress + SQLite + WooCommerce + Kirki site, runs the REST smoke test, PHPUnit and the storefront E2E, and fails on any PHP warning.
- A packaging job that builds the installable zip.

The Kirki editor E2E needs a full browser editor session, so it runs in the local matrix rather than in CI. Kirki Pro is not on WordPress.org, so it is tested locally only.
