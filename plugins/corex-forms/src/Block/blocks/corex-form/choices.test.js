/**
 * Jest: the forms `corex/form` can name, as its chooser loads them (spec 136, FR-020). A list
 * that loaded, even empty, is told from one that failed, and a person who may not list the forms
 * is told so. When the source changes the chooser goes back to loading, and an answer to an
 * earlier source is dropped.
 */
import apiFetch from '@wordpress/api-fetch';
import { loadChoices, useChoices } from './choices';

const mockHook = { state: undefined, set: jest.fn(), effects: [] };

jest.mock(
	'@wordpress/element',
	() => ( {
		useState: ( init ) => [ mockHook.state ?? init, mockHook.set ],
		useEffect: ( effect ) => mockHook.effects.push( effect ),
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
// What flowBlockEditor.js, which lists the flows, imports besides.
jest.mock( '@wordpress/blocks', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/components', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => () => null, {
	virtual: true,
} );
jest.mock(
	'@wordpress/i18n',
	() => ( { __: ( s ) => s, sprintf: ( s ) => s } ),
	{ virtual: true }
);

/**
 * @return {{promise: Promise<Object>, resolve: (value: Object) => void, reject: (error: Object) => void}} A promise the test settles.
 */
function deferred() {
	const handle = {};
	handle.promise = new Promise( ( resolve, reject ) => {
		handle.resolve = resolve;
		handle.reject = reject;
	} );

	return handle;
}

beforeEach( () => {
	apiFetch.mockReset();
	mockHook.state = undefined;
	mockHook.set = jest.fn();
	mockHook.effects = [];
} );

describe( 'loadChoices (FR-020)', () => {
	test( 'every flow ever published, closed and expired ones too, with drafts left out', async () => {
		const closed = { id: 9, name: 'Last year', state: 'closed' };
		apiFetch.mockResolvedValue( {
			data: {
				flows: [
					{ id: 7, name: 'Contact', state: 'published' },
					{ id: 8, name: 'Draft', state: 'draft' },
					closed,
				],
			},
		} );

		await expect( loadChoices( 'flow' ) ).resolves.toEqual( {
			status: 'ready',
			items: [ { id: 7, name: 'Contact', state: 'published' }, closed ],
		} );
		// No state filter: a saved block naming a closed or expired flow still finds it.
		expect( apiFetch ).toHaveBeenCalledWith( { path: '/corex/v1/flows' } );
	} );

	test( 'the registered forms; an empty list is ready, not failed', async () => {
		apiFetch.mockResolvedValue( [] );

		await expect( loadChoices( 'registered' ) ).resolves.toEqual( {
			status: 'ready',
			items: [],
		} );
		expect( apiFetch ).toHaveBeenCalledWith( { path: '/corex/v1/forms' } );
	} );

	test.each( [
		[
			'CoreX refuses (403)',
			{
				code: 'forbidden',
				message: 'You do not have permission to perform this action.',
				data: { status: 403 },
			},
		],
		[
			'WordPress refuses (401)',
			{
				code: 'rest_forbidden',
				message: 'Sorry, you are not allowed to do that.',
				data: { status: 401 },
			},
		],
	] )(
		'a person who may not list the forms is told so: %s',
		async ( _who, error ) => {
			apiFetch.mockRejectedValue( error );

			await expect( loadChoices( 'flow' ) ).resolves.toEqual( {
				status: 'failed',
				forbidden: true,
				message: error.message,
			} );
		}
	);

	test( 'any other failure is a failure, with its message', async () => {
		apiFetch.mockRejectedValue( {
			code: 'fetch_error',
			message: 'You are probably offline.',
		} );

		await expect( loadChoices( 'flow' ) ).resolves.toEqual( {
			status: 'failed',
			forbidden: false,
			message: 'You are probably offline.',
		} );
	} );
} );

describe( 'useChoices (FR-020)', () => {
	test( 'loads when its source changes, and drops the answer to an earlier source', async () => {
		const first = deferred();
		const second = deferred();
		apiFetch
			.mockReturnValueOnce( first.promise )
			.mockReturnValueOnce( second.promise );

		expect( useChoices( 'flow' ) ).toEqual( { status: 'loading' } );
		const cleanFirst = mockHook.effects[ 0 ]();
		expect( mockHook.set ).toHaveBeenLastCalledWith( {
			status: 'loading',
		} );

		// The source changes before the first answer arrives.
		useChoices( 'registered' );
		cleanFirst();
		mockHook.effects[ 1 ]();
		expect( mockHook.set ).toHaveBeenLastCalledWith( {
			status: 'loading',
		} );

		first.resolve( { data: { flows: [ { id: 7, name: 'Old' } ] } } );
		await first.promise;
		await Promise.resolve();
		expect( mockHook.set ).toHaveBeenLastCalledWith( {
			status: 'loading',
		} );

		second.resolve( [ { slug: 'contact', label: 'Contact' } ] );
		await second.promise;
		await Promise.resolve();
		expect( mockHook.set ).toHaveBeenLastCalledWith( {
			status: 'ready',
			items: [ { slug: 'contact', label: 'Contact' } ],
		} );
	} );
} );
