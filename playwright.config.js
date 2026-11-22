// @ts-check
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 90000,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://127.0.0.1:8895',
		screenshot: 'only-on-failure',
		viewport: { width: 1360, height: 900 },
		launchOptions: process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {},
	},
} );
