/**
 * Which Email Studio button says it is working (spec 108, story 3).
 *
 * The studio has one flag for every request, and it disabled every button on the tab with
 * nothing to say which had been pressed: after "Save immutable draft" the button beside it,
 * "Activate latest draft", looked exactly the same. The state names the action that is out, and
 * the button with that name is the one that works.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { PendingControl } from '../../admin/components/working.js';
import { TemplatePanel } from '../components/TemplatePanel.js';
import {
	emailStudioReducer,
	initialEmailStudioState,
	studioStatus,
} from '../emailStudioClient.js';

describe( 'the action that is out', () => {
	const saving = emailStudioReducer( initialEmailStudioState, {
		type: 'mutating',
		control: 'draft',
	} );

	it( 'is named from the press, and through the reload that follows it', () => {
		// A save is a POST and then the studio is read again; the button works until both
		// are done, not only until the first.
		const reloading = emailStudioReducer( saving, { type: 'load' } );

		expect( saving.pending ).toBe( 'draft' );
		expect( reloading.pending ).toBe( 'draft' );
	} );

	it.each( [
		[ 'the studio has been read again', { type: 'loaded', payload: {} } ],
		[ 'the request failed', { type: 'failed', message: 'Nope' } ],
		[ 'there is only something to say', { type: 'notice', message: 'Hm' } ],
	] )( 'is over once %s', ( _when, action ) => {
		expect( emailStudioReducer( saving, action ).pending ).toBe( '' );
	} );
} );

describe( 'the template editor while a request is out', () => {
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		document.body.innerHTML = '';
	} );

	const button = ( name ) =>
		[ ...container.querySelectorAll( 'button' ) ].find(
			( candidate ) => candidate.textContent.trim() === name
		);

	it( 'shows the pressed button working, and holds the others', () => {
		const template = {
			id: 3,
			name: 'Welcome',
			slug: 'welcome',
			status: 'draft',
			draft_version: 2,
		};

		act( () => {
			root.render(
				<PendingControl.Provider value="draft">
					<TemplatePanel
						templates={ [ template ] }
						layouts={ [] }
						detail={ { template } }
						draft={ {
							subject: '',
							html_body: '',
							plain_text: '',
							plain_text_mode: 'auto',
							layout_slug: '',
							variable_keys: [],
						} }
						errors={ {} }
						busy
						onSelect={ () => {} }
						onCreate={ () => {} }
						onDraftChange={ () => {} }
						onSaveDraft={ () => {} }
						onActivate={ () => {} }
					/>
				</PendingControl.Provider>
			);
		} );

		const saved = button( 'Save immutable draft' );
		const other = button( 'Activate latest draft' );

		expect( saved.dataset.corexWorking ).toBe( 'true' );
		expect( saved.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( other.disabled ).toBe( true );
		expect( other.hasAttribute( 'data-corex-working' ) ).toBe( false );
		expect( button( 'Create' ).hasAttribute( 'data-corex-working' ) ).toBe(
			false
		);
	} );
} );

describe( 'whether the studio is still loading', () => {
	const read = ( payload ) =>
		emailStudioReducer( initialEmailStudioState, {
			type: 'loaded',
			payload,
		} );

	it( 'is, until it has been read once', () => {
		const asking = emailStudioReducer( initialEmailStudioState, {
			type: 'load',
		} );

		expect( studioStatus( initialEmailStudioState ) ).toBe( 'loading' );
		expect( studioStatus( asking ) ).toBe( 'loading' );
	} );

	it( 'is not again on a studio with no templates, when a save reads it once more', () => {
		// "No templates yet" was how a first load was told from a later one, and it is also
		// true of a new site that has been read: every save there took the open form away.
		const empty = read( { templates: [] } );
		const readingAgain = emailStudioReducer( empty, { type: 'load' } );

		expect( studioStatus( readingAgain ) ).toBe( 'ready' );
	} );

	it( 'is a failure only if it has never been read', () => {
		const failed = { type: 'failed', message: 'Nope' };

		expect(
			studioStatus(
				emailStudioReducer( initialEmailStudioState, failed )
			)
		).toBe( 'error' );
		// A save that fails says so in the notice, over a studio that is still there.
		expect( studioStatus( emailStudioReducer( read( {} ), failed ) ) ).toBe(
			'ready'
		);
	} );
} );

describe( 'a template chosen from the list', () => {
	let container;
	let root;

	beforeEach( () => {
		global.IS_REACT_ACT_ENVIRONMENT = true;
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		document.body.innerHTML = '';
	} );

	it( 'shows the editor that is coming, not the last one, and cannot be chosen twice', () => {
		const welcome = {
			id: 3,
			name: 'Welcome',
			slug: 'welcome',
			status: 'draft',
		};
		const receipt = {
			id: 4,
			name: 'Receipt',
			slug: 'receipt',
			status: 'draft',
		};

		act( () => {
			root.render(
				<TemplatePanel
					templates={ [ welcome, receipt ] }
					layouts={ [] }
					detail={ { template: { ...welcome, draft_version: 1 } } }
					draft={ {
						subject: 'Welcome aboard',
						html_body: '',
						plain_text: '',
						plain_text_mode: 'auto',
						layout_slug: '',
						variable_keys: [],
					} }
					errors={ {} }
					selecting={ 4 }
					onSelect={ () => {} }
					onCreate={ () => {} }
					onDraftChange={ () => {} }
					onSaveDraft={ () => {} }
					onActivate={ () => {} }
				/>
			);
		} );

		const editor = container.querySelector( '.corex-email-app__editor' );
		const rail = [
			...container.querySelectorAll( '.corex-email-app__list button' ),
		];

		expect(
			editor.querySelector( '.corex-admin-skeleton' )
		).not.toBeNull();
		expect( editor.textContent ).not.toContain( 'Welcome' );
		expect( rail.map( ( row ) => row.disabled ) ).toEqual( [ true, true ] );
		expect( rail[ 1 ].className ).toBe( 'is-active' );
	} );
} );
