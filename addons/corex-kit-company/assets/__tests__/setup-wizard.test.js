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

const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

async function press( id ) {
	document.getElementById( id ).click();
	await settle();
}

async function openTheWizard() {
	document.body.innerHTML = '<div id="corex-setup-app"></div>';
	window.wp = {};
	window.corexSetup = {
		restUrl: '/corex/v1/setup',
		nonce: 'n',
		adminUrl: '/wp-admin/',
	};
	const answerFor = async ( url ) =>
		answers[ url.split( '/' ).pop().split( '?' )[ 0 ] ];
	window.Corex = { api: { get: answerFor, post: answerFor } };

	jest.isolateModules( () => {
		require( '../setup-wizard.js' );
	} );
	await settle();
}

const alert = () => document.querySelector( '#corex-setup-app [role="alert"]' );

beforeEach( () => {
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

async function goToThePlan() {
	await openTheWizard();
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
