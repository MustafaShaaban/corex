/**
 * Jest: the states a records block's preview is drawn in (spec 136, FR-019). Each is WordPress's
 * own component, named in words: the first load is a Placeholder holding a Spinner, a later load
 * keeps the last preview, dimmed under a Spinner after a second (WordPress's own drawing), empty
 * is a Placeholder, and a preview that failed is a Notice that also gives WordPress's message.
 *
 * The same file is copied into the careers and forms packages; `tests/block-editor-records.test.js`
 * holds the copies to this one.
 */
// React comes from the root's devDependencies.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act, createElement } from 'react';
// eslint-disable-next-line import/no-extraneous-dependencies
import { createRoot } from 'react-dom/client';
import { recordsStates } from './records-states';

const PlaceholderMock = () => null;
const SpinnerMock = () => <span className="probe-spinner" />;
const NoticeMock = () => null;

jest.mock(
	'@wordpress/components',
	() => ( {
		Placeholder: PlaceholderMock,
		Spinner: SpinnerMock,
		Notice: NoticeMock,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

describe( 'recordsStates (FR-019)', () => {
	const states = recordsStates( {
		label: 'Posts',
		loading: 'Loading posts…',
		empty: 'No posts to show.',
	} );

	test( 'loading for the first time: a Placeholder named in words, holding a Spinner', () => {
		const element = states.LoadingResponsePlaceholder( {} );

		expect( element.type ).toBe( PlaceholderMock );
		expect( element.props.label ).toBe( 'Posts' );
		expect( element.props.instructions ).toBe( 'Loading posts…' );
		expect( element.props.children.type ).toBe( SpinnerMock );
	} );

	describe( 'loading again', () => {
		let holder;
		let root;

		beforeEach( () => {
			global.IS_REACT_ACT_ENVIRONMENT = true;
			jest.useFakeTimers();
			holder = document.createElement( 'div' );
			root = createRoot( holder );
		} );

		afterEach( () => {
			act( () => root.unmount() );
			jest.useRealTimers();
			delete global.IS_REACT_ACT_ENVIRONMENT;
		} );

		test( 'the last preview stays, then dims under a Spinner after a second, as WordPress draws it', () => {
			act( () =>
				root.render(
					createElement( states.LoadingResponsePlaceholder, {
						children: createElement( 'p', null, 'last' ),
					} )
				)
			);
			const dimmed = () => holder.querySelector( 'p' ).parentElement;

			expect( holder.querySelector( '.probe-spinner' ) ).toBeNull();
			expect( dimmed().style.opacity ).toBe( '1' );

			act( () => jest.advanceTimersByTime( 1000 ) );

			expect( holder.querySelector( '.probe-spinner' ) ).not.toBeNull();
			expect( dimmed().style.opacity ).toBe( '0.3' );
		} );
	} );

	test( 'empty: a Placeholder that says nothing is shown', () => {
		const element = states.EmptyResponsePlaceholder( {} );

		expect( element.type ).toBe( PlaceholderMock );
		expect( element.props.label ).toBe( 'Posts' );
		expect( element.props.instructions ).toBe( 'No posts to show.' );
	} );

	test( "failed: a Notice that the preview did not load, then WordPress's message; it cannot be dismissed", () => {
		const element = states.ErrorResponsePlaceholder( {
			message: 'Sorry, you are not allowed to do that.',
		} );

		expect( element.type ).toBe( NoticeMock );
		expect( element.props.status ).toBe( 'warning' );
		expect( element.props.isDismissible ).toBe( false );
		expect( element.props.children ).toEqual( [
			'The preview did not load.',
			' Sorry, you are not allowed to do that.',
		] );
		expect( states.ErrorResponsePlaceholder( {} ).props.children ).toEqual(
			[ 'The preview did not load.', '' ]
		);
	} );
} );
