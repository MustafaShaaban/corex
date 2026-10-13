/**
 * A query is added to a REST address as the site spells that address.
 *
 * With pretty permalinks a route is a path (`/wp-json/corex/v1/submissions`) and what a request
 * adds starts the query. Without them the route is itself a query
 * (`/index.php?rest_route=/corex/v1/submissions`), and a second `?` makes what was added part
 * of the route: WordPress knows no route of that name and answers 404. The Submissions inbox
 * did not open on such a site.
 *
 * The second half holds every admin client that adds a query to both spellings. A client added
 * later belongs in its table. (Found 2026-10-13 on a fresh install; DECISIONS #359.)
 */
import { withQuery } from '../restAddress.js';
import { buildListUrl, buildMigrationsUrl } from '../dataClient.js';
import { buildEmailLogUrl, buildInboxUrl } from '../../Submissions/inbox.js';
import { buildFlowListUrl } from '../../Forms/flowEditor.js';
import { buildHealthUrl } from '../../Email/emailStudioClient.js';
import { loadBlogData } from '../../Blog/blogProClient.js';

const PRETTY = 'https://acme.test/wp-json/corex/v1';
const PLAIN = 'https://acme.test/index.php?rest_route=/corex/v1';

afterEach( () => {
	delete global.fetch;
} );

describe( 'withQuery', () => {
	it( 'starts the query of an address that has none', () => {
		expect( withQuery( `${ PRETTY }/flows`, 'state=draft' ) ).toBe(
			`${ PRETTY }/flows?state=draft`
		);
	} );

	it( 'adds to the query of an address that is spelled as one', () => {
		expect( withQuery( `${ PLAIN }/flows`, 'state=draft' ) ).toBe(
			`${ PLAIN }/flows&state=draft`
		);
	} );

	it( 'takes the parameters as the browser holds them', () => {
		const query = new URLSearchParams( { search: 'a b', page: '2' } );

		expect( withQuery( `${ PLAIN }/flows`, query ) ).toBe(
			`${ PLAIN }/flows&search=a+b&page=2`
		);
	} );

	it.each( [ PRETTY, PLAIN ] )(
		'leaves %s as it is when there is nothing to add',
		( root ) => {
			expect( withQuery( `${ root }/flows`, '' ) ).toBe(
				`${ root }/flows`
			);
			expect(
				withQuery( `${ root }/flows`, new URLSearchParams() )
			).toBe( `${ root }/flows` );
		}
	);
} );

describe( 'an admin client’s address, with and without pretty permalinks', () => {
	it.each( [
		[
			'the Submissions inbox’s list',
			( root ) =>
				buildInboxUrl( `${ root }/submissions`, {
					search: 'ada',
					page: 1,
					perPage: 25,
				} ),
			'/submissions',
			'search=ada&page=1&per_page=25',
		],
		[
			'the email log of a submission’s attempt',
			( root ) => buildEmailLogUrl( `${ root }/submissions`, 7, 'a/1' ),
			'/submissions/7/email-log',
			'attempt_id=a%2F1',
		],
		[
			'the Forms & Flows list, filtered',
			( root ) =>
				buildFlowListUrl( `${ root }/flows`, 'contact', 'draft' ),
			'/flows',
			'search=contact&state=draft',
		],
		[
			'a Data model’s records',
			( root ) =>
				buildListUrl( `${ root }/data`, 'submissions', { page: 2 } ),
			'/data/submissions',
			'page=2&per_page=20',
		],
		[
			'a Data model’s migrations',
			( root ) => buildMigrationsUrl( `${ root }/data`, 'my model' ),
			'/data/migrations',
			'source=my%20model',
		],
		[
			'an Email Studio template’s health checks',
			( root ) => buildHealthUrl( `${ root }/email-studio`, 4, 2 ),
			'/email-studio/templates/4/health',
			'version=2',
		],
	] )( '%s', ( _client, address, route, query ) => {
		expect( address( PRETTY ) ).toBe( `${ PRETTY }${ route }?${ query }` );
		expect( address( PLAIN ) ).toBe( `${ PLAIN }${ route }&${ query }` );
	} );

	it.each( [
		[ PRETTY, '?' ],
		[ PLAIN, '&' ],
	] )( 'what Blog Pro reads about a post, from %s', async ( root, sign ) => {
		const asked = [];
		global.fetch = jest.fn( async ( address ) => {
			asked.push( address );
			return { ok: true, json: async () => ( { ok: true, data: {} } ) };
		} );

		await loadBlogData( { restUrl: root, nonce: 'n' }, 12 );

		expect( asked ).toEqual( [
			`${ root }/blog/analytics${ sign }post_id=12`,
			`${ root }/blog/comments${ sign }post_id=12`,
			`${ root }/blog/authors`,
			`${ root }/blog/share-controls${ sign }post_id=12`,
		] );
	} );
} );
