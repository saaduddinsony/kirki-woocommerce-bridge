// End-to-end: storefront built from Kirki designs that use the plugin's elements.
const { test, expect } = require( '@playwright/test' );
const { shot } = require( './helpers' );

test.describe.configure( { mode: 'serial' } );

test( 'shop template renders grid, filters, ordering and result count', async ( { page } ) => {
	await page.goto( '/shop/' );
	await expect( page.locator( '[data-kwi-element="products"] .kwi-product-card' ).first() ).toBeVisible();
	await expect( page.locator( '[data-kwi-element="shop-filter"]' ).first() ).toBeVisible();
	await expect( page.locator( '[data-kwi-element="shop-ordering"] select.orderby' ) ).toBeVisible();
	await expect( page.locator( '[data-kwi-element="shop-result-count"]' ) ).toContainText( /result/i );
	const total = await page.locator( '.kwi-product-card' ).count();
	expect( total ).toBeGreaterThan( 3 );
	await shot( page, 'shop', { fullPage: true } );

	// Category filter narrows the main query.
	await page.locator( '.kwi-filter--category a', { hasText: 'Accessories' } ).first().click();
	await expect( page ).toHaveURL( /kwi_cat=accessories/ );
	const filtered = await page.locator( '.kwi-product-card' ).count();
	expect( filtered ).toBeGreaterThan( 0 );
	expect( filtered ).toBeLessThan( total );
	await expect( page.locator( '.kwi-product-card', { hasText: 'Hoodie' } ) ).toHaveCount( 0 );
	await expect( page.locator( '.kwi-filter__chips' ) ).toBeVisible();
	await shot( page, 'shop-filtered', { fullPage: true } );

	// Sorting by price (native WooCommerce orderby).
	await page.goto( '/shop/?orderby=price' );
	const prices = await page.locator( '.kwi-product-card .price' ).allInnerTexts();
	const first = parseFloat( prices[ 0 ].replace( /[^0-9.]+/g, ' ' ).trim().split( ' ' ).pop() );
	const last = parseFloat( prices[ prices.length - 1 ].replace( /[^0-9.]+/g, ' ' ).trim().split( ' ' ).pop() );
	expect( first ).toBeLessThanOrEqual( last );
} );

test( 'single product template renders all parts', async ( { page } ) => {
	await page.goto( '/product/classic-tee/' );
	await expect( page.locator( '[data-kwi-element="product-title"] h1' ) ).toHaveText( 'Classic Tee' );
	await expect( page.locator( '[data-kwi-element="product-price"]' ) ).toContainText( '20.00' );
	await expect( page.locator( '[data-kwi-element="product-gallery"] .woocommerce-product-gallery' ) ).toBeVisible();
	await expect( page.locator( '[data-kwi-element="product-tabs"] .woocommerce-tabs' ) ).toBeVisible();
	await expect( page.locator( '[data-kwi-element="product-rating"] .star-rating' ) ).toBeVisible();
	await expect( page.locator( '[data-kwi-element="product-meta"]' ) ).toContainText( 'KWT-100' );
	await expect( page.locator( '[data-kwi-element="product-upsells"] li.product' ).first() ).toBeVisible();
	// Gallery scripts are loaded even though WooCommerce skips them on block themes.
	await expect.poll( () => page.evaluate( () => !! ( window.jQuery && window.jQuery.fn.flexslider ) ) ).toBe( true );
	await shot( page, 'single-product', { fullPage: true } );
} );

test( 'add to cart, cart and checkout with cash on delivery', async ( { page } ) => {
	// Simple product (on sale).
	await page.goto( '/product/vintage-tee/' );
	await page.locator( '[data-kwi-element="product-add-to-cart"] input.qty' ).fill( '2' );
	await page.locator( '[data-kwi-element="product-add-to-cart"] button[name="add-to-cart"]' ).click();
	await expect( page.locator( '.woocommerce-message, .wc-block-components-notice-banner.is-success' ) ).toContainText( /added to your cart/i );

	// Variable product.
	await page.goto( '/product/logo-hoodie/' );
	await page.locator( 'select#pa_color' ).selectOption( 'blue' );
	await page.locator( 'select#pa_size' ).selectOption( 'large' );
	await expect( page.locator( '.woocommerce-variation-price' ) ).toContainText( '70.00' );
	await page.locator( '.single_add_to_cart_button:not(.disabled)' ).click();
	await expect( page.locator( '.woocommerce-message, .wc-block-components-notice-banner.is-success' ) ).toContainText( /added to your cart/i );

	// Cart (Kirki cart design).
	await page.goto( '/cart/' );
	await expect( page.locator( '[data-kwi-element="cart"] .woocommerce-cart-form' ) ).toBeVisible();
	await expect( page.locator( '.woocommerce-cart-form__cart-item' ) ).toHaveCount( 2 );
	await shot( page, 'cart', { fullPage: true } );

	// Checkout (Kirki checkout design, classic form).
	await page.goto( '/checkout/' );
	await expect( page.locator( '[data-kwi-element="checkout"] form.checkout' ) ).toBeVisible();
	await page.fill( '#billing_first_name', 'Test' );
	await page.fill( '#billing_last_name', 'Buyer' );
	await page.fill( '#billing_address_1', '1 Market St' );
	await page.fill( '#billing_city', 'San Francisco' );
	await page.fill( '#billing_postcode', '94105' );
	await page.fill( '#billing_phone', '5555550100' );
	await page.fill( '#billing_email', 'buyer@example.com' );
	await page.waitForLoadState( 'networkidle' );
	await page.locator( '#payment_method_cod' ).check( { force: true } );
	await shot( page, 'checkout', { fullPage: true } );
	await page.locator( '#place_order' ).click();
	await page.waitForURL( /order-received/, { timeout: 60000 } );
	await expect( page.locator( 'body' ) ).toContainText( /order has been received/i );
	await expect( page.locator( 'body' ) ).toContainText( /Cash on delivery/i );
	await expect( page.locator( 'body' ) ).toContainText( 'Logo Hoodie' );
	await expect( page.locator( 'body' ) ).toContainText( 'Vintage Tee' );
	await shot( page, 'order-received', { fullPage: true } );
} );

test( 'REST API refuses anonymous access to management routes', async ( { request } ) => {
	const res = await request.get( '/wp-json/kirki-woo/v1/templates' );
	expect( res.status() ).toBe( 401 );
	const el = await request.get( '/wp-json/kirki-woo/v1/elements' );
	expect( el.status() ).toBe( 401 );
} );
