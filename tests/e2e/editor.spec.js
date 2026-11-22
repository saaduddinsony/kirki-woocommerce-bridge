// End-to-end: elements are registered in the Kirki editor, previews render
// in the canvas, and a design built by drag and drop is used on the storefront.
const { test, expect } = require( '@playwright/test' );
const { login, shot } = require( './helpers' );

test.use( { viewport: { width: 1920, height: 1080 } } );

async function api( page, method, path, data ) {
	return page.evaluate(
		async ( { method, path, data } ) => {
			const nonce = await ( await fetch( '/wp-admin/admin-ajax.php?action=rest-nonce' ) ).text();
			const res = await fetch( '/wp-json/kirki-woo/v1' + path, {
				method,
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				body: data ? JSON.stringify( data ) : undefined,
			} );
			return { status: res.status, body: await res.json() };
		},
		{ method, path, data }
	);
}

test( 'Kirki editor: register, preview, drag and drop, publish, assign', async ( { page } ) => {
	test.setTimeout( 240000 );
	await login( page );

	const elements = await api( page, 'GET', '/elements' );
	expect( elements.status ).toBe( 200 );
	const expected = elements.body.elements.length;

	// Starter single product design: previews render inside the canvas.
	const designs = await api( page, 'GET', '/designs' );
	const single = designs.body.designs.find( ( d ) => /Single product/i.test( d.title ) );
	expect( single ).toBeTruthy();
	await page.goto( single.editor_url );
	await page.waitForFunction( () => Array.isArray( window.kirkiWooRegistered ), null, { timeout: 90000 } );
	expect( await page.evaluate( () => window.kirkiWooRegistered.length ) ).toBe( expected );
	const canvas = page.frameLocator( 'iframe#iframe-kirki-canvas' );
	await expect( canvas.locator( '[data-kwi-editor="product-title"] [data-kwi-element="product-title"]' ).first() ).toBeVisible( { timeout: 60000 } );
	await expect( canvas.locator( '[data-kwi-editor="product-add-to-cart"] form.cart' ).first() ).toBeVisible();
	await page.waitForTimeout( 2000 );
	await shot( page, 'kirki-editor' );

	// Blank design: insert an element by drag and drop, then publish.
	const created = await api( page, 'POST', '/designs', { context: 'single_product', title: 'E2E drag and drop', starter: false, create_rule: false } );
	expect( created.status ).toBe( 201 );
	const design = created.body.design;
	await page.goto( design.editor_url );
	await page.waitForFunction( () => Array.isArray( window.kirkiWooRegistered ), null, { timeout: 90000 } );
	await page.locator( 'iframe#iframe-kirki-canvas' ).waitFor( { timeout: 60000 } );
	const search = page.locator( 'input[placeholder*="Search pre-built"]' ).first();
	// Open the Insert panel (second icon in Kirki's left bar) once the UI is ready.
	for ( let i = 0; i < 20 && ! ( await search.isVisible() ); i++ ) {
		await page.waitForTimeout( 2000 );
		await page.mouse.click( 22, 113 );
		await page.waitForTimeout( 1000 );
	}
	await search.fill( 'Product Title' );
	await page.waitForTimeout( 1500 );
	await shot( page, 'kirki-editor-insert' );
	const item = page.getByText( 'Product Title', { exact: true } ).first();
	const box = await item.boundingBox();
	const frame = await page.locator( 'iframe#iframe-kirki-canvas' ).boundingBox();
	await page.mouse.move( box.x + 20, box.y + 8 );
	await page.mouse.down();
	await page.mouse.move( frame.x + 500, frame.y + 200, { steps: 20 } );
	await page.mouse.move( frame.x + 520, frame.y + 220, { steps: 10 } );
	await page.mouse.up();
	await expect( canvas.locator( '[data-kwi-editor="product-title"] [data-kwi-element="product-title"]' ).first() ).toBeVisible( { timeout: 30000 } );

	const save = await page.getByText( 'Save', { exact: true } ).first().boundingBox();
	await page.mouse.click( save.x + save.width + 22, save.y + save.height / 2 );
	await page.getByText( 'Publish', { exact: true } ).last().click();
	await page.waitForTimeout( 6000 );

	// Assign the design to one product only and check the storefront.
	const zipId = await page.evaluate( async () => ( await ( await fetch( '/wp-json/wc/store/v1/products?slug=zip-hoodie' ) ).json() )[ 0 ].id );
	const rule = await api( page, 'POST', '/templates', {
		context: 'single_product',
		design_id: design.id,
		priority: 100,
		conditions: { product_ids: [ zipId ] },
	} );
	expect( rule.status ).toBe( 201 );
	try {
		const resolved = await api( page, 'POST', '/templates/resolve', { context: 'single_product', object_id: zipId } );
		expect( resolved.body.rule.id ).toBe( rule.body.id );
		await page.goto( '/product/zip-hoodie/' );
		await expect( page.locator( '[data-kwi-element="product-title"]' ) ).toHaveCount( 1 );
		await expect( page.locator( '[data-kwi-element="product-title"]' ) ).toContainText( 'Zip Hoodie' );
		await expect( page.locator( '[data-kwi-element="product-tabs"]' ) ).toHaveCount( 0 );
		// Other products still use the starter design.
		await page.goto( '/product/classic-tee/' );
		await expect( page.locator( '[data-kwi-element="product-tabs"]' ) ).toHaveCount( 1 );
	} finally {
		await api( page, 'DELETE', '/templates/' + rule.body.id );
		expect( ( await api( page, 'DELETE', '/designs/' + design.id, { force: true } ) ).status ).toBe( 200 );
	}
} );
