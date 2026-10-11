/**
 * An uploaded file under "Answers" in the pane, rendered (spec 081, SC-002).
 *
 * Reported on 2026-10-11: a submission that carried a file showed `224` there, the number its
 * file is stored under, with nothing to press. The server now sends the file described; this is
 * what a person is shown for it, in the inbox and in the trash.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import DetailPane from '../detail/DetailPane.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

// `@wordpress/components` does not load under Jest here; its Button is a button.
jest.mock( '@wordpress/components', () => {
	const { forwardRef } = require( '@wordpress/element' );

	return {
		Button: forwardRef(
			( { variant, isDestructive, children, ...rest }, ref ) => (
				<button ref={ ref } type="button" { ...rest }>
					{ children }
				</button>
			)
		),
	};
} );

const ADDRESS =
	'https://example.test/wp-admin/admin-post.php?action=corex_submission_file&submission=41&field=cv&_wpnonce=abc';

const application = ( values, more = {} ) => ( {
	id: 41,
	form: 'apply',
	status: 'new',
	questions: [
		{ key: 'name', label: 'Name', type: 'text' },
		{ key: 'cv', label: 'Your CV', type: 'file' },
	],
	values: { name: 'Salma', ...values },
	replies: [],
	reply_delivery: { mode: 'deliver', code: 'live', reason: '' },
	...more,
} );

let container;
let root;

function open( record ) {
	act( () => {
		root.render(
			<DetailPane
				id="pane"
				drawer={ { status: 'ready', record } }
				inbox={ { state: {}, close: jest.fn() } }
				onTrash={ jest.fn() }
				onDelete={ jest.fn() }
			/>
		);
	} );
}

/**
 * What the pane says under one question.
 *
 * @param {string} question The question, as the form words it.
 * @return {Element|undefined} The answer's cell, or nothing when the question is not listed.
 */
const answerTo = ( question ) =>
	[ ...container.querySelectorAll( '.corex-pane__fields dt' ) ].find(
		( heading ) => heading.textContent === question
	)?.nextElementSibling;

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	installDateTimeConfig();
} );

afterAll( removeDateTimeConfig );

beforeEach( () => {
	// jsdom has the element and not its methods: the pane is a dialog, and opens as one.
	window.HTMLDialogElement.prototype.showModal = jest.fn( function () {
		this.setAttribute( 'open', '' );
	} );
	window.HTMLDialogElement.prototype.close = jest.fn( function () {
		this.removeAttribute( 'open' );
	} );
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

const FILE = { id: 224, name: 'salma-cv.pdf', url: ADDRESS, missing: false };

it.each( [
	[ 'in the inbox', {} ],
	[ 'in the trash', { trashed: true, trashed_at: '2026-10-11 09:30:00' } ],
] )(
	'shows an uploaded file by its name, as a link to where it is opened: %s',
	( place, more ) => {
		open( application( { cv: FILE }, more ) );

		const link = answerTo( 'Your CV' ).querySelector( 'a' );

		expect( link.textContent ).toContain( 'salma-cv.pdf' );
		expect( link.getAttribute( 'href' ) ).toBe( ADDRESS );
		// Not the number the file is stored under.
		expect( answerTo( 'Your CV' ).textContent ).not.toContain( '224' );
	}
);

it( 'says a file is missing, and offers nothing to press', () => {
	open(
		application( {
			cv: { id: 224, name: '', url: '', missing: true },
		} )
	);

	expect( answerTo( 'Your CV' ).textContent ).toBe( 'File missing' );
	expect( answerTo( 'Your CV' ).querySelector( 'a' ) ).toBeNull();
} );

it( 'leaves out a file question nobody answered', () => {
	open( application( {} ) );

	expect( answerTo( 'Name' ).textContent ).toBe( 'Salma' );
	expect( answerTo( 'Your CV' ) ).toBeUndefined();
} );
