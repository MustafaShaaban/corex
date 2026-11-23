/**
 * Jest: the states a records block's preview is drawn in (spec 136, FR-019). Each is WordPress's
 * own component, named in words: loading is a Placeholder holding a Spinner, empty is a
 * Placeholder, and a preview that failed is a Notice.
 */
import { recordsStates } from './records-states';

const PlaceholderMock = () => null;
const SpinnerMock = () => null;
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

	test( 'loading again: the last preview stays until the new one arrives', () => {
		expect(
			states.LoadingResponsePlaceholder( { children: 'last' } )
		).toBe( 'last' );
	} );

	test( 'empty: a Placeholder that says nothing is shown', () => {
		const element = states.EmptyResponsePlaceholder( {} );

		expect( element.type ).toBe( PlaceholderMock );
		expect( element.props.label ).toBe( 'Posts' );
		expect( element.props.instructions ).toBe( 'No posts to show.' );
	} );

	test( 'failed: a Notice that the preview did not load, which cannot be dismissed', () => {
		const element = states.ErrorResponsePlaceholder( {} );

		expect( element.type ).toBe( NoticeMock );
		expect( element.props.status ).toBe( 'warning' );
		expect( element.props.isDismissible ).toBe( false );
		expect( element.props.children ).toBe( 'The preview did not load.' );
	} );
} );
