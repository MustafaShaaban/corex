/**
 * The published-docs check (spec 130, SC-001 and SC-002): run after every Pages deploy, and by
 * the owner's switch steps, against the live site. Tested here against a fake site.
 */
const { checkDocsSite } = require( '../scripts/check-docs-host.mjs' );

/**
 * A fake `fetch` over a table of URL → response. Anything not in the table is a 404.
 *
 * @param {Record<string, {status?: number, location?: string, body?: string}>} site
 * @return {typeof fetch} A `fetch` with the subset of the API the check uses.
 */
function fakeFetch( site ) {
	return async ( url ) => {
		const answer = site[ url ] || { status: 404, body: 'Not found' };

		return {
			status: answer.status || 200,
			headers: {
				get: ( name ) =>
					name.toLowerCase() === 'location'
						? answer.location || null
						: null,
			},
			text: async () => answer.body || '',
		};
	};
}

const NEW = 'https://corex-docs.mustafashaaban.com/';
const OLD = 'https://mustafashaaban.github.io/corex/';

/**
 * A page of the docs as Starlight writes it at the root.
 *
 * @param {string[]} links Path-absolute links on the page.
 * @return {string} HTML.
 */
function page( links ) {
	return [
		'<html><head><link rel="stylesheet" href="/_astro/common.css"></head><body>',
		...links.map( ( link ) => `<a href="${ link }">x</a>` ),
		'</body></html>',
	].join( '' );
}

/** A docs site at the new host's root that serves everything the check reads. */
function healthySite() {
	return {
		[ NEW ]: { body: page( [ '/guides/media/', '/reference/' ] ) },
		[ `${ NEW }guides/media/` ]: { body: page( [ '/' ] ) },
		[ `${ NEW }reference/` ]: {
			body: page( [ '/reference/add-ons/accordionrenderer/' ] ),
		},
		[ `${ NEW }reference/add-ons/accordionrenderer/` ]: {
			body: page( [ '/reference/' ] ),
		},
		[ `${ NEW }_astro/common.css` ]: { body: 'body{}' },
		[ `${ NEW }pagefind/pagefind.js` ]: { body: '//' },
		'http://corex-docs.mustafashaaban.com/': {
			status: 301,
			location: NEW,
		},
	};
}

describe( 'checkDocsSite', () => {
	it( 'finds nothing wrong with a site that serves its pages, styles and search', async () => {
		const problems = await checkDocsSite( {
			baseUrl: NEW,
			fetch: fakeFetch( healthySite() ),
		} );

		expect( problems ).toEqual( [] );
	} );

	it( 'names a page linked from the home page that does not load', async () => {
		const site = healthySite();
		delete site[ `${ NEW }guides/media/` ];

		const problems = await checkDocsSite( {
			baseUrl: NEW,
			fetch: fakeFetch( site ),
		} );

		expect( problems ).toEqual( [
			`${ NEW }guides/media/ answered 404, expected 200`,
		] );
	} );

	it( 'names a stylesheet that does not load, which is what a build for the wrong base looks like', async () => {
		const site = healthySite();
		delete site[ `${ NEW }_astro/common.css` ];

		const problems = await checkDocsSite( {
			baseUrl: NEW,
			fetch: fakeFetch( site ),
		} );

		expect( problems ).toContain(
			`${ NEW }_astro/common.css answered 404, expected 200`
		);
	} );

	it( 'names a page whose links point under a base the site is not served from', async () => {
		const site = healthySite();
		site[ `${ NEW }guides/media/` ] = {
			body: page( [ '/corex/guides/forms/' ] ),
		};

		const problems = await checkDocsSite( {
			baseUrl: NEW,
			fetch: fakeFetch( site ),
		} );

		expect( problems ).toContain(
			`${ NEW }corex/guides/forms/ answered 404, expected 200`
		);
	} );

	it( 'names a missing search index', async () => {
		const site = healthySite();
		delete site[ `${ NEW }pagefind/pagefind.js` ];

		const problems = await checkDocsSite( {
			baseUrl: NEW,
			fetch: fakeFetch( site ),
		} );

		expect( problems ).toEqual( [
			`${ NEW }pagefind/pagefind.js answered 404, expected 200`,
		] );
	} );

	it( 'names a request that got no answer, and goes on checking the rest', async () => {
		const site = healthySite();
		const answering = fakeFetch( site );
		const silentFor = `${ NEW }guides/media/`;
		const fetch = ( url, options ) =>
			url === silentFor
				? Promise.reject(
						Object.assign( new TypeError( 'fetch failed' ), {
							cause: { code: 'UND_ERR_CONNECT_TIMEOUT' },
						} )
					)
				: answering( url, options );

		const problems = await checkDocsSite( { baseUrl: NEW, fetch } );

		expect( problems ).toEqual( [
			`${ NEW }guides/media/ did not answer (UND_ERR_CONNECT_TIMEOUT)`,
		] );
	} );

	it( 'names plain HTTP that is not sent to HTTPS', async () => {
		const site = healthySite();
		site[ 'http://corex-docs.mustafashaaban.com/' ] = {
			body: page( [] ),
		};

		const problems = await checkDocsSite( {
			baseUrl: NEW,
			fetch: fakeFetch( site ),
		} );

		expect( problems ).toEqual( [
			'http://corex-docs.mustafashaaban.com/ answered 200, expected a redirect to https://corex-docs.mustafashaaban.com/',
		] );
	} );

	describe( 'with the address it moved from', () => {
		/** The healthy site plus GitHub's redirects from the project page, path kept. */
		function movedSite() {
			const site = healthySite();

			for ( const path of [
				'',
				'guides/media/',
				'reference/',
				'reference/add-ons/accordionrenderer/',
			] ) {
				site[ `${ OLD }${ path }` ] = {
					status: 301,
					location: `${ NEW }${ path }`,
				};
			}

			return site;
		}

		it( 'finds nothing wrong when every old address lands on the same page', async () => {
			const problems = await checkDocsSite( {
				baseUrl: NEW,
				movedFrom: OLD,
				fetch: fakeFetch( movedSite() ),
			} );

			expect( problems ).toEqual( [] );
		} );

		it( 'names an old address that redirects somewhere other than the same page', async () => {
			const site = movedSite();
			site[ `${ OLD }guides/media/` ].location = NEW;

			const problems = await checkDocsSite( {
				baseUrl: NEW,
				movedFrom: OLD,
				fetch: fakeFetch( site ),
			} );

			expect( problems ).toEqual( [
				`${ OLD }guides/media/ answered 301, expected a redirect to ${ NEW }guides/media/`,
			] );
		} );

		it( 'names an old address that still serves the site itself', async () => {
			const site = movedSite();
			site[ OLD ] = { body: page( [] ) };

			const problems = await checkDocsSite( {
				baseUrl: NEW,
				movedFrom: OLD,
				fetch: fakeFetch( site ),
			} );

			expect( problems ).toEqual( [
				`${ OLD } answered 200, expected a redirect to ${ NEW }`,
			] );
		} );
	} );

	describe( 'on the project page, under a base path', () => {
		it( 'reads path-absolute links under the base and checks them on the same host', async () => {
			const site = {
				[ OLD ]: {
					body: page( [ '/corex/guides/media/' ] ).replace(
						'/_astro/',
						'/corex/_astro/'
					),
				},
				[ `${ OLD }guides/media/` ]: { body: '<html></html>' },
				[ `${ OLD }_astro/common.css` ]: { body: 'body{}' },
				[ `${ OLD }pagefind/pagefind.js` ]: { body: '//' },
				'http://mustafashaaban.github.io/corex/': {
					status: 301,
					location: OLD,
				},
			};

			const problems = await checkDocsSite( {
				baseUrl: OLD,
				fetch: fakeFetch( site ),
			} );

			expect( problems ).toEqual( [] );
		} );
	} );
} );
