/**
 * Choosing how the whole site delivers mail stays with site administration (spec 117, US-EML-20):
 * a holder of "Manage Email Studio" who is not a site administrator is told so, and is not sent to
 * a setting they cannot change.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { OverviewPanel } from '../components/OverviewPanel.js';
import { LogsPanel } from '../components/LogsPanel.js';

const data = {
	delivery: { environment: 'production', provider_configured: false },
	counts: {},
	recentTestSends: [],
	health: [],
};

let container;
let root;

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

async function draw( canConfigureDelivery ) {
	await act( async () => {
		root.render(
			<OverviewPanel
				data={ data }
				settingsUrl="/wp-admin/admin.php?page=corex-settings-config"
				canConfigureDelivery={ canConfigureDelivery }
				onScope={ () => {} }
			/>
		);
	} );
}

it( 'offers a site administrator the way to configure delivery', async () => {
	await draw( true );

	expect( container.textContent ).toContain( 'Configure delivery' );
} );

it( 'tells anyone else that a site administrator configures delivery', async () => {
	await draw( false );

	expect( container.textContent ).not.toContain( 'Configure delivery' );
	expect( container.textContent ).toContain(
		'so a site administrator does them'
	);
} );

it( 'leaves the capture switch to a site administrator, and says why to anyone else', async () => {
	data.outcome = { environment: 'development', scope: 'all' };
	try {
		await draw( false );
		const toggle = container.querySelector( '#corex-email-capture-scope' );

		expect( toggle.disabled ).toBe( true );
		expect( toggle.getAttribute( 'aria-describedby' ) ).toContain(
			'corex-email-delivery-admin'
		);

		await draw( true );
		expect(
			container.querySelector( '#corex-email-capture-scope' ).disabled
		).toBe( false );
	} finally {
		delete data.outcome;
	}
} );

describe( 'the captured mail list', () => {
	const capture = ( extra ) => ( {
		capture_id: 'c1',
		subject: 'Hello',
		to: [ 'someone@example.test' ],
		path: 'Form notification',
		state: 'captured',
		captured_at: '2026-10-26T10:00:00+00:00',
		...extra,
	} );

	async function drawLogs( captures ) {
		await act( async () => {
			root.render(
				<LogsPanel
					attempts={ [] }
					captures={ captures }
					busy={ false }
					onResend={ () => {} }
				/>
			);
		} );
	}

	it( 'says that only a site administrator opens a captured email when its content was withheld', async () => {
		await drawLogs( [ capture( { content_withheld: true } ) ] );

		expect( container.textContent ).toContain(
			'Only a site administrator can open a captured email.'
		);
	} );

	it( 'says nothing of the kind to a site administrator', async () => {
		await drawLogs( [ capture( { content_withheld: false } ) ] );

		expect( container.textContent ).not.toContain(
			'Only a site administrator can open a captured email.'
		);
	} );
} );
