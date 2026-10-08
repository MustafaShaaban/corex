/**
 * What the Insights screen shows before its two answers arrive (spec 108, slice 5).
 *
 * The screen drew its cards at once, each reading "Not run yet" with a dash for a score, and
 * then filled in whichever had a result: for as long as the request took, a check that had
 * been run an hour before said it never had been. The widgets were not drawn at all until they
 * arrived, and when either request failed nothing said so.
 *
 * The script is a plain function over the page, run here against a page of its own. The
 * network is the one thing replaced: each request is answered by the test.
 */

let requests;

const ok = ( data ) => ( { envelope: { ok: true, data } } );
const refused = { envelope: { ok: false } };

async function answer( path, body ) {
	const request = requests.find(
		( candidate ) => candidate.url.endsWith( path ) && ! candidate.done
	);
	request.done = true;
	request.resolve( body );
	// The script acts on the answer in a promise's callback: let it run.
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

const root = () => document.getElementById( 'corex-insights-app' );
const cards = () => [ ...root().querySelectorAll( '.corex-insight-card' ) ];
const placeholders = () => [
	...root().querySelectorAll( '.corex-insight-widget.corex-admin-skeleton' ),
];

beforeEach( () => {
	requests = [];
	document.body.innerHTML = '<div id="corex-insights-app"></div>';
	window.wp = {};
	window.corexInsights = {
		restUrl: 'https://x.test/wp-json/corex/v1/insights',
		nonce: 'n',
		providers: [
			{ id: 'performance', label: 'Performance' },
			{ id: 'readiness', label: 'Readiness' },
		],
	};
	window.Corex = {
		api: {
			get: ( url ) =>
				new Promise( ( resolve ) => {
					requests.push( { url, resolve } );
				} ),
		},
	};
	jest.isolateModules( () => {
		require( '../insights.js' );
	} );
} );

afterEach( () => {
	document.body.innerHTML = '';
	delete window.corexInsights;
	delete window.Corex;
	delete window.wp;
} );

it( 'does not say a check was never run before it knows', async () => {
	expect( root().dataset.corexState ).toBe( 'loading' );
	expect( root().textContent ).not.toContain( 'Not run yet' );
	expect(
		cards().every( ( card ) => card.querySelector( 'button' ).disabled )
	).toBe( true );

	await answer(
		'/insights',
		ok( {
			results: [
				{
					provider: 'performance',
					score: 91,
					grade: 'A',
					status: 'good',
					summary: 'Fast.',
				},
			],
		} )
	);

	const [ performance, readiness ] = cards();
	expect( performance.textContent ).toContain( '91' );
	expect( readiness.textContent ).toContain( 'Not run yet' );
	expect( readiness.querySelector( 'button' ).disabled ).toBe( false );
} );

it( 'holds the widgets’ place until they arrive', async () => {
	expect( placeholders() ).toHaveLength( 3 );
	expect( placeholders()[ 0 ].getAttribute( 'aria-hidden' ) ).toBe( 'true' );

	await answer(
		'/widgets',
		ok( {
			widgets: [
				{ title: 'Security events', sub: 'Last 7 days', chip: 'Live' },
			],
		} )
	);

	expect( placeholders() ).toHaveLength( 0 );
	expect( root().textContent ).toContain( 'Security events' );
} );

it( 'is ready only when both have answered', async () => {
	await answer( '/insights', ok( { results: [] } ) );
	expect( root().dataset.corexState ).toBe( 'loading' );

	await answer( '/widgets', ok( { widgets: [] } ) );
	expect( root().dataset.corexState ).toBe( 'ready' );
	expect( root().hasAttribute( 'aria-busy' ) ).toBe( false );
} );

it( 'says the last results could not be loaded, on the cards they belong to', async () => {
	await answer( '/insights', refused );

	for ( const card of cards() ) {
		expect( card.querySelector( '[role="alert"]' ).textContent ).toBe(
			'The last results could not be loaded. Run a check to get new ones.'
		);
		expect( card.querySelector( 'button' ).disabled ).toBe( false );
	}
} );

it( 'says the widgets could not be loaded, and asks again when told to', async () => {
	await answer( '/widgets', refused );

	expect( placeholders() ).toHaveLength( 0 );
	expect( root().textContent ).toContain(
		'The rest of the insights could not be loaded.'
	);

	root().querySelector( '.corex-error-state button' ).click();

	expect(
		requests.filter( ( request ) => request.url.endsWith( '/widgets' ) )
	).toHaveLength( 2 );
	expect( placeholders() ).toHaveLength( 3 );
	expect( root().textContent ).not.toContain(
		'The rest of the insights could not be loaded.'
	);
} );
