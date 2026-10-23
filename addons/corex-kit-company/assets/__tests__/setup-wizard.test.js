/**
 * The setup wizard says when the plan cannot be read or applied (#313).
 *
 * Both failed without a word. The plan step read "0 pages will be created or adopted", which is
 * a statement about the plan and not about a request that failed; and "Apply plan" came back as
 * it was, as if it had never been pressed.
 *
 * The wizard is a script with no build step. It is loaded into a page that has its mount point,
 * with the network as the one thing replaced.
 * @param data
 */
const steps = [
	'welcome',
	'brand',
	'kit',
	'demo',
	'plan',
	'backup',
	'apply',
	'launch',
	'done',
].map( ( label ) => ( { label } ) );

const ok = ( data ) => ( { envelope: { ok: true, data } } );
const failed = ( message ) => ( { envelope: { ok: false, message } } );

/** What each route answers next. */
let answers;

/** Every address the wizard asked, in order. */
let asked;

/**
 * The last part of the route an address is for, however the site spells its routes: a path
 * with pretty permalinks, the `rest_route` parameter without them.
 *
 * @param {string} url The address the wizard asked.
 * @return {string} The route's last part, such as `plan`.
 */
function routeEnd( url ) {
	const address = new URL( url, 'https://acme.test' );
	const route = address.searchParams.get( 'rest_route' ) || address.pathname;
	return route.split( '/' ).pop();
}

const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

async function press( id ) {
	document.getElementById( id ).click();
	await settle();
}

async function openTheWizard( restUrl = '/corex/v1/setup' ) {
	document.body.innerHTML = '<div id="corex-setup-app"></div>';
	window.wp = {};
	window.corexSetup = {
		restUrl,
		nonce: 'n',
		adminUrl: '/wp-admin/',
	};
	const answerFor = async ( url ) => {
		asked.push( url );
		return answers[ routeEnd( url ) ];
	};
	window.Corex = { api: { get: answerFor, post: answerFor } };

	jest.isolateModules( () => {
		require( '../setup-wizard.js' );
	} );
	await settle();
}

const alert = () => document.querySelector( '#corex-setup-app [role="alert"]' );

beforeEach( () => {
	asked = [];
	answers = {
		state: ok( {
			config: { kits: [ { name: 'company' } ], demoLevels: [] },
			progress: { steps },
		} ),
		plan: ok( { plan: { pages: [ {}, {} ] }, conflicts: [] } ),
	};
} );

afterEach( () => {
	document.body.innerHTML = '';
	delete window.Corex;
	delete window.corexSetup;
	delete window.wp;
} );

async function goToThePlan( restUrl ) {
	await openTheWizard( restUrl );
	for ( let step = 0; step < 4; step++ ) {
		await press( 'corex-setup-next' );
	}
}

it( 'says the plan could not be read, where it used to count no pages', async () => {
	answers.plan = failed( 'The kit is not installed.' );

	await goToThePlan();

	expect( alert().textContent ).toBe( 'The kit is not installed.' );
	expect( document.body.textContent ).not.toContain( 'pages will be' );
} );

it( 'says the plan could not be applied, and offers the button again', async () => {
	answers.apply = failed( 'A page could not be created.' );
	await goToThePlan();
	await press( 'corex-setup-next' );
	document.getElementById( 'corex-setup-backup' ).click();
	await press( 'corex-setup-next' );

	await press( 'corex-setup-apply' );

	expect( alert().textContent ).toBe( 'A page could not be created.' );
	expect( document.getElementById( 'corex-setup-apply' ).disabled ).toBe(
		false
	);
} );

it( 'says nothing went wrong once a second press applies the plan', async () => {
	answers.apply = failed( 'A page could not be created.' );
	await goToThePlan();
	await press( 'corex-setup-next' );
	document.getElementById( 'corex-setup-backup' ).click();
	await press( 'corex-setup-next' );
	await press( 'corex-setup-apply' );

	answers.apply = ok( { pages: 2 } );
	await press( 'corex-setup-apply' );

	expect( alert() ).toBeNull();
	expect( document.body.textContent ).toContain( '2 pages processed.' );
} );

it.each( [
	[
		'pretty permalinks',
		'https://acme.test/wp-json/corex/v1/setup',
		'/wp-json/corex/v1/setup/plan',
		null,
	],
	[
		'no pretty permalinks',
		'https://acme.test/index.php?rest_route=/corex/v1/setup',
		'/index.php',
		'/corex/v1/setup/plan',
	],
] )(
	'asks for the plan of the chosen kit on a site with %s',
	async ( _site, restUrl, path, route ) => {
		await goToThePlan( restUrl );

		const plan = new URL( asked[ asked.length - 1 ] );
		expect( plan.pathname ).toBe( path );
		expect( plan.searchParams.get( 'rest_route' ) ).toBe( route );
		expect( plan.searchParams.get( 'kit' ) ).toBe( 'company' );
		expect( plan.searchParams.get( 'level' ) ).toBe( 'standard' );
		expect( document.body.textContent ).toContain( 'pages will be' );
	}
);

describe( 'the wizard says what CoreX records, and takes no backup (spec 115)', () => {
	const RECORDED =
		'Before applying, CoreX records the pages this plan replaces or adopts and your front-page settings, so a reset can put them back. It does not back up anything else.';

	beforeEach( () => {
		answers.state = ok( {
			config: {
				kits: [ { name: 'company' } ],
				demoLevels: [],
				conflictChoices: [
					{ id: 'keep', label: 'Keep mine' },
					{ id: 'replace', label: 'Replace' },
				],
			},
			progress: { steps },
		} );
		answers.plan = ok( {
			plan: { pages: [ {}, {}, {} ] },
			conflicts: [
				{ slug: 'about', title: 'About', pageTitle: 'About us' },
			],
			adopted: [
				{ slug: 'contact', title: 'Contact', pageTitle: 'Contact' },
			],
		} );
	} );

	it( 'states what is recorded on the welcome, and never promises a backup', async () => {
		await openTheWizard();

		const text = document.getElementById( 'corex-setup-app' ).textContent;
		expect( text ).toContain( RECORDED );
		expect( text ).not.toMatch( /with a backup|backs? up your site/i );
	} );

	it( 'lists the replaced and adopted pages on the Backup step, by their titles', async () => {
		await goToThePlan();
		document
			.querySelector( 'input[name="conflict-about"][value="replace"]' )
			.click();
		await press( 'corex-setup-next' );

		const panel = document.querySelector( '.corex-setup__panel' );
		const listed = [
			...panel.querySelectorAll( '.corex-setup__recorded li' ),
		].map( ( item ) => item.textContent );
		expect( panel.textContent ).toContain( RECORDED );
		expect( listed ).toEqual( [ 'Contact', 'About us' ] );
		expect( panel.textContent ).toContain( 'backup plugin' );
		expect( panel.textContent ).not.toMatch(
			/(takes|took|made|making|with) a backup|backs? up (your|the) site/i
		);
	} );

	it( 'does not list a conflict the person keeps', async () => {
		await goToThePlan();
		document
			.querySelector( 'input[name="conflict-about"][value="keep"]' )
			.click();
		await press( 'corex-setup-next' );

		const listed = [
			...document.querySelectorAll( '.corex-setup__recorded li' ),
		].map( ( item ) => item.textContent );
		expect( listed ).toEqual( [ 'Contact' ] );
	} );

	it( 'does not list a page an earlier apply made, which is the kit’s own', async () => {
		answers.plan = ok( {
			plan: { pages: [ {}, {} ] },
			conflicts: [
				{
					slug: 'about',
					title: 'About',
					pageTitle: 'About',
					recorded: false,
				},
			],
			adopted: [],
		} );
		await goToThePlan();
		document
			.querySelector( 'input[name="conflict-about"][value="replace"]' )
			.click();
		await press( 'corex-setup-next' );

		expect(
			document.querySelectorAll( '.corex-setup__recorded li' )
		).toHaveLength( 0 );
		expect( document.body.textContent ).toContain(
			'This plan changes none of your existing pages.'
		);
	} );

	it( 'keeps Apply off until the person confirms a backup of their own', async () => {
		await goToThePlan();
		await press( 'corex-setup-next' );
		await press( 'corex-setup-next' );
		expect( document.getElementById( 'corex-setup-apply' ).disabled ).toBe(
			true
		);

		await press( 'corex-setup-back' );
		document.getElementById( 'corex-setup-backup' ).click();
		await press( 'corex-setup-next' );

		expect( document.getElementById( 'corex-setup-apply' ).disabled ).toBe(
			false
		);
	} );
} );
