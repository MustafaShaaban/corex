/**
 * A health check's status and an attempt's state are shown in words, not as the server's keys
 * (spec 120, FR-038: every text a person reads is translatable). The quality job's words check
 * found `healthy`, `warning` and `captured` printed raw on the Email Studio screen.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import {
	OverviewPanel,
	healthStatusLabel,
} from '../components/OverviewPanel.js';
import { LogsPanel, attemptStateLabel } from '../components/LogsPanel.js';

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

function mount( element ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => {
		root.render( element );
	} );
	return {
		container,
		cleanup: () =>
			act( () => {
				root.unmount();
				container.remove();
			} ),
	};
}

test( 'a state no label is written for is not printed as its key', () => {
	expect( attemptStateLabel( 'something_new' ) ).toBe( 'Unknown' );
	expect( healthStatusLabel( 'something_new' ) ).toBe( 'Unknown' );
} );

test( 'the overview shows each health check in words', () => {
	const { container, cleanup } = mount(
		<OverviewPanel
			data={ {
				health: [
					{ template_id: 1, status: 'healthy', errors: [] },
					{ template_id: 2, status: 'warning', errors: {} },
				],
				recentTestSends: [],
			} }
			settingsUrl="#"
			busy={ false }
			onScope={ () => {} }
		/>
	);
	const health = container.querySelector( '.corex-email-app__health' );
	expect( health.textContent ).toContain( 'Healthy' );
	expect( health.textContent ).toContain( 'Needs attention' );
	expect( health.textContent ).not.toMatch( /healthy|warning/ );
	cleanup();
} );

test( 'the log shows an attempt state in words and keeps the key for its badge', () => {
	const { container, cleanup } = mount(
		<LogsPanel
			attempts={ [
				{
					attempt_id: 7,
					recipient: 'jane@example.com',
					subject: 'Hello',
					state: 'captured',
					provider: 'capture',
					occurred_at: '2026-11-11T10:00:00Z',
				},
			] }
			captures={ [] }
			busy={ false }
			onResend={ () => {} }
		/>
	);
	const badge = container.querySelector( '.corex-email-app__badge' );
	expect( badge.textContent ).toBe( 'Captured' );
	expect( badge.className ).toContain( 'is-captured' );
	cleanup();
} );
