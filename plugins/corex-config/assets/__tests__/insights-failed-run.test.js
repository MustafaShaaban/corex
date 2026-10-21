/**
 * A PageSpeed run that could not complete (spec 114, US-SAFE-12).
 *
 * It was stored and drawn as a completed run with a score of 50, so the card read "D" and "50"
 * for a site the service never measured. A failed result has no score and no grade: the card
 * says it could not run, gives the reason, and offers Retry.
 *
 * The script is a plain function over the page, run here against a page of its own. The
 * network is the one thing replaced: each request is answered by the test.
 */

let requests;

const ok = ( data ) => ( { envelope: { ok: true, data } } );

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

const failedRun = {
	provider: 'performance',
	label: 'Performance',
	score: null,
	grade: null,
	status: 'failed',
	failure: 'local_url',
	summary:
		'PageSpeed cannot reach a local site: it measures only an address that is public on the internet.',
	metrics: [],
	recommendations: [],
	checkedAt: 1760000000,
};

it( 'says a failed run could not run, why, and offers Retry, with no score or grade', async () => {
	await answer( '/insights', ok( { results: [ failedRun ] } ) );

	const [ performance ] = cards();
	expect( performance.classList.contains( 'is-failed' ) ).toBe( true );
	expect( performance.textContent ).toContain( 'Could not run' );
	expect( performance.textContent ).toContain(
		'PageSpeed cannot reach a local site'
	);
	expect(
		performance.querySelector( '.corex-insight-card__score' )
	).toBeNull();
	expect( performance.textContent ).not.toMatch( /50|D/ );
	expect( performance.querySelector( 'button' ).textContent ).toBe( 'Retry' );
} );

it( 'draws a completed run with its grade and score, and Run check', async () => {
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

	const [ performance ] = cards();
	expect(
		performance.querySelector( '.corex-insight-card__grade' ).textContent
	).toBe( 'A' );
	expect( performance.textContent ).not.toContain( 'Could not run' );
	expect( performance.querySelector( 'button' ).textContent ).toBe(
		'Run check'
	);
} );
