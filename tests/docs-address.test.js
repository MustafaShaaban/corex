/**
 * The docs build's address: one rule, read by the Astro config and by the link test (spec 130).
 *
 * The published build takes its address from GitHub Pages (`.github/workflows/docs.yml` passes
 * `actions/configure-pages`'s origin and base path). A Pages site on a custom domain has an
 * empty base path, and the build must read that as the domain root, not as "unset", or it is
 * made for `/corex` and every link and asset on the new host 404s.
 */
const { docsAddress } = require( '../docs-app/docs-address.mjs' );

describe( 'docsAddress', () => {
	it( 'is the GitHub project page when nothing is set', () => {
		expect( docsAddress( {} ) ).toEqual( {
			site: 'https://mustafashaaban.github.io',
			base: '/corex',
		} );
	} );

	it( 'reads an empty base path as the domain root', () => {
		expect(
			docsAddress( {
				COREX_DOCS_SITE: 'https://corex-docs.mustafashaaban.com',
				COREX_DOCS_BASE: '',
			} )
		).toEqual( {
			site: 'https://corex-docs.mustafashaaban.com',
			base: '/',
		} );
	} );

	it.each( [
		[ 'a trailing slash dropped', '/corex/' ],
		[ 'the leading slash added', 'corex' ],
	] )( 'writes a subpath base with %s as /corex', ( _case, given ) => {
		expect( docsAddress( { COREX_DOCS_BASE: given } ).base ).toBe(
			'/corex'
		);
	} );

	it( 'drops a trailing slash from the site', () => {
		expect(
			docsAddress( { COREX_DOCS_SITE: 'https://docs.example.test/' } )
				.site
		).toBe( 'https://docs.example.test' );
	} );
} );
