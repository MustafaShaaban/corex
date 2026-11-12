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
		readinessUrl: '/corex/v1/readiness',
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

	answers.apply = ok( { applied: true, pages: 2 } );
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

/*
 * Spec 118, US-WIZ-06: the launch step is the one launch checklist, and Go live is the Operations
 * screen's own mode form.
 */

const readiness = ( overrides = {} ) => ( {
	checklist: {
		count: '4 of 6 ready',
		blocking: 2,
		blockingText: '2 checks still block going live',
		groups: [
			{
				key: 'security',
				title: 'Security and hosting',
				count: '4 of 6 ready',
				rows: [
					{
						key: 'ssl',
						label: 'HTTPS',
						chip: 'blocked',
						word: 'Blocks going live',
						note: 'Serve the site over HTTPS.',
						href: '/wp-admin/options-general.php',
					},
					{
						key: 'debug_display',
						label: 'Debug output hidden',
						chip: 'done',
						word: 'Ready',
						note: 'PHP errors are not displayed.',
						href: '',
					},
				],
			},
		],
	},
	mode: { key: 'staging', label: 'Staging', production: false },
	goLive: {
		url: '/wp-admin/admin-post.php',
		action: 'corex_ops_mode',
		nonceField: 'corex_ops_mode_nonce',
		nonce: 'mode-nonce',
		mode: 'production',
		phrase: 'PRODUCTION',
		return: 'setup',
	},
	...overrides,
} );

async function goToTheLaunchStep() {
	await openTheWizard();
	for ( let step = 0; step < 7; step++ ) {
		await press( 'corex-setup-next' );
	}
	await settle();
}

it( 'shows the launch checklist in its groups, each check in words, open ones linked', async () => {
	answers.readiness = ok( readiness() );

	await goToTheLaunchStep();

	const rows = document.querySelectorAll( '.corex-checklist__row' );
	expect( rows ).toHaveLength( 2 );
	expect( rows[ 0 ].textContent ).toContain( 'Blocks going live' );
	expect( rows[ 0 ].querySelector( 'a' ).getAttribute( 'href' ) ).toBe(
		'/wp-admin/options-general.php'
	);
	expect( rows[ 1 ].querySelector( 'a' ) ).toBeNull();
	expect( document.body.textContent ).toContain(
		'2 checks still block going live'
	);
} );

it( 'goes live through the Operations mode form, returning to the wizard', async () => {
	answers.readiness = ok( readiness() );

	await goToTheLaunchStep();

	const form = document.querySelector( '.corex-setup__golive' );
	expect( form.getAttribute( 'action' ) ).toBe( '/wp-admin/admin-post.php' );
	const field = ( name ) =>
		form.querySelector( `[name="${ name }"]` ).getAttribute( 'value' );
	expect( field( 'action' ) ).toBe( 'corex_ops_mode' );
	expect( field( 'corex_ops_mode_nonce' ) ).toBe( 'mode-nonce' );
	expect( field( 'corex_mode' ) ).toBe( 'production' );
	expect( field( 'corex_return' ) ).toBe( 'setup' );
	expect(
		form.querySelector( '[name="corex_confirm_phrase"]' )
	).not.toBeNull();
} );

it( 'offers no Go live once the site is in Production', async () => {
	answers.readiness = ok(
		readiness( {
			mode: { key: 'production', label: 'Production', production: true },
		} )
	);

	await goToTheLaunchStep();

	expect( document.querySelector( '.corex-setup__golive' ) ).toBeNull();
	expect( document.body.textContent ).toContain( 'The site is live.' );
} );

it( 'says the checklist could not be read, offers Retry and no Go live', async () => {
	answers.readiness = failed( 'Nope.' );

	await goToTheLaunchStep();

	expect( alert().textContent ).toBe(
		'The launch checklist could not be read.'
	);
	expect( document.querySelector( '.corex-setup__golive' ) ).toBeNull();

	answers.readiness = ok( readiness() );
	await press( 'corex-setup-readiness-retry' );

	expect( document.querySelector( '.corex-setup__golive' ) ).not.toBeNull();
} );

it( 'never calls the site ready on the Done step while a check blocks going live', async () => {
	answers.readiness = ok( readiness() );

	await goToTheLaunchStep();
	await press( 'corex-setup-next' );
	await settle();

	expect( document.body.textContent ).toContain(
		'2 checks still block going live'
	);
	expect( document.body.textContent ).not.toContain( 'ready to review' );
} );

it( 'says an apply that did not write every page did not complete', async () => {
	answers.apply = ok( { applied: false, pages: 3 } );
	await goToThePlan();
	await press( 'corex-setup-next' );
	document.getElementById( 'corex-setup-backup' ).click();
	await press( 'corex-setup-next' );

	await press( 'corex-setup-apply' );

	expect( alert().textContent ).toContain( 'not marked applied' );
} );

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
