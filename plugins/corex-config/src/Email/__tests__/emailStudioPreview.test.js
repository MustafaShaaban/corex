/**
 * The preview, the plain text and the test send show what the server drew (spec 120, story 1).
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import {
	PREVIEW_WIDTHS,
	PlainTextPanel,
	PreviewPanel,
	TestSendPanel,
} from '../components/PreviewPanels.js';

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

const drawn = {
	status: 'ready',
	subject: 'Hello Contact form',
	html: '<!DOCTYPE html><html><body><p>Hello</p></body></html>',
	text: 'Hello\n\n-- \nThis message was sent by Acme.',
	samples: [
		{
			key: 'form.title',
			label: 'Form title',
			value: 'Contact form',
			editable: true,
		},
		{
			key: 'form.answers',
			label: 'All answers',
			value: 'Three sample answers',
			editable: false,
		},
	],
};

it( 'shows the server’s email in a sandbox at the width chosen, with the samples beside it', () => {
	const samples = [];
	const { container, cleanup } = mount(
		<PreviewPanel
			selected
			drawn={ drawn }
			direction="ltr"
			device="mobile"
			onDirection={ () => {} }
			onDevice={ () => {} }
			onSample={ ( key, value ) => samples.push( [ key, value ] ) }
		/>
	);

	const frame = container.querySelector( 'iframe' );
	expect( frame.getAttribute( 'sandbox' ) ).toBe( '' );
	expect( frame.getAttribute( 'srcdoc' ) ).toBe( drawn.html );
	expect(
		container.querySelector( '.corex-email-app__preview-frame' ).style
			.maxInlineSize
	).toBe( `${ PREVIEW_WIDTHS.mobile }px` );
	expect( container.textContent ).toContain( 'Three sample answers' );

	const title = container.querySelector( 'input[name="form.title"]' );
	expect( title.value ).toBe( 'Contact form' );
	cleanup();
} );

it( 'names what a template cannot be drawn without, in place of the email, and offers no test', () => {
	const failed = {
		status: 'error',
		errors: [ 'Unknown variable: nonsense.key.' ],
	};
	const preview = mount(
		<PreviewPanel
			selected
			drawn={ failed }
			direction="ltr"
			device="desktop"
			onDirection={ () => {} }
			onDevice={ () => {} }
			onSample={ () => {} }
		/>
	);
	const test = mount(
		<TestSendPanel
			outcome={ { state: 'captured' } }
			selected
			drawn={ failed }
			busy={ false }
			lastResult={ null }
			error=""
			onSend={ () => {} }
		/>
	);

	expect( preview.container.querySelector( 'iframe' ) ).toBeNull();
	expect( preview.container.textContent ).toContain(
		'Unknown variable: nonsense.key.'
	);
	expect( test.container.querySelector( 'form' ) ).toBeNull();
	preview.cleanup();
	test.cleanup();
} );

it( 'labels the plain text as what is sent, and says what a test did with a link to its log row', () => {
	const plain = mount(
		<PlainTextPanel selected drawn={ drawn } mode="auto" />
	);
	const test = mount(
		<TestSendPanel
			outcome={ { state: 'captured' } }
			selected
			drawn={ drawn }
			busy={ false }
			lastResult={ {
				result: { state: 'captured' },
				summary: 'Captured: nothing left this site',
				reason: 'Development mail is captured locally.',
				log_url:
					'/wp-admin/admin.php?page=corex-email-studio&tab=logs#corex-attempt-1',
			} }
			error=""
			onSend={ () => {} }
		/>
	);

	expect( plain.container.querySelector( 'h2' ).textContent ).toBe(
		'Sent as the plain-text version'
	);
	expect( plain.container.querySelector( 'pre' ).textContent ).toBe(
		drawn.text
	);
	expect( test.container.textContent ).toContain(
		'Captured: nothing left this site'
	);
	expect(
		test.container.querySelector( 'a' ).getAttribute( 'href' )
	).toContain( '#corex-attempt-1' );
	plain.cleanup();
	test.cleanup();
} );
