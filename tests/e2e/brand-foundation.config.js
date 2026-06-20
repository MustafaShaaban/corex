const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	testMatch: 'brand-foundation.spec.js',
	reporter: 'list',
	use: { browserName: 'chromium' },
} );
