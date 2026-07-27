/**
 * The one path between Blog Pro and its own REST routes (spec 075, FR-1/FR-6).
 *
 * `BlogProScreen` has always localized `restUrl` and `nonce`, and nothing has ever used them: the app
 * read the initial blob and stopped, so all seven routes were reachable by an HTTP client and by
 * nothing in the product. This is the caller they were missing.
 *
 * Deliberately thin. `blogProState.js` already owns the endpoint building and the reducer, so this
 * adds transport and nothing else — a second state layer next to a working one is how the drawer and
 * the screen drifted apart before DECISIONS #157.
 */
import { __ } from '@wordpress/i18n';
import { blogEndpoint } from './blogProState.js';

/**
 * One request, returning the envelope's `data` or throwing something a person can read.
 *
 * The controller answers `{ ok, message, data }`, so a 200 can still carry a refusal — a failure that
 * only checks the HTTP status would report success and render nothing.
 */
export async function blogRequest( config, path, { method = 'GET', data } = {} ) {
	const response = await fetch( blogEndpoint( config.restUrl, path ), {
		method,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': config.nonce,
		},
		...( data ? { body: JSON.stringify( data ) } : {} ),
	} );

	const envelope = await response.json().catch( () => null );

	if ( ! response.ok || envelope?.ok === false ) {
		throw new Error(
			envelope?.message || __( 'CoreX could not reach Blog Pro.', 'corex' )
		);
	}

	return envelope?.data ?? envelope ?? {};
}

/**
 * Everything the screen shows for one post, refetched together.
 *
 * Called after a mutation rather than on selection: choosing a post navigates to `?post=<id>` and the
 * server renders it, because **there is no GET route for a post's editorial item** — refreshing four
 * of the five panels over REST would leave the fifth stale (spec 075, FR-1). After a transition or a
 * moderation the editorial item comes back in the mutation's own response, so this can fill in the
 * rest without one.
 *
 * These four calls are what finally give `/blog/analytics`, `/blog/comments`, `/blog/authors`, and
 * `/blog/share-controls` a caller in the product.
 */
export async function loadBlogData( config, postId ) {
	const query = `?post_id=${ encodeURIComponent( postId ) }`;

	const [ analytics, comments, authors, shareControls ] = await Promise.all( [
		blogRequest( config, `blog/analytics${ query }` ),
		blogRequest( config, `blog/comments${ query }` ),
		blogRequest( config, 'blog/authors' ),
		blogRequest( config, `blog/share-controls${ query }` ),
	] );

	return {
		analytics,
		comments: comments?.comments ?? [],
		authors: authors?.authors ?? [],
		shareControls: shareControls?.controls ?? [],
	};
}
