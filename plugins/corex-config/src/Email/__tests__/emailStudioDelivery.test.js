/**
 * Choosing how the whole site delivers mail stays with site administration (spec 117, US-EML-20):
 * a holder of "Manage Email Studio" who is not a site administrator is told so, and is not sent to
 * a setting they cannot change.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { OverviewPanel } from '../components/OverviewPanel.js';

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
		'a site administrator does them in Settings'
	);
} );
