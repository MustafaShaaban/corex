/**
 * The two requests behind "Preview changes" on the role matrix (#313).
 *
 * The routes were complete and nothing called them: the button that should have was a primary
 * button with no handler, enabled the moment a change was staged.
 *
 * A change is applied in two steps on purpose. The preview answers what would change and whether
 * the safety policy allows it, with a hash of exactly that change. Applying sends the hash back,
 * so what is applied is what was read, by the person who read it, within a few minutes.
 */
import { __ } from '@wordpress/i18n';

/** Must be `AccessService::ROLE_CHANGE_OPERATION`: the server checks the confirmation against it. */
const ROLE_CHANGE_OPERATION = 'access.role.change';

/** How long somebody has between reading a preview and applying it. */
const CONFIRMATION_LIFETIME_MS = 5 * 60 * 1000;

async function post( config, path, body ) {
	const response = await window.fetch( `${ config.restUrl }/${ path }`, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': config.nonce,
		},
		body: JSON.stringify( body ),
	} );

	if ( ! response.ok ) {
		// Deliberately not the response body: it may carry a WordPress error payload, and this
		// message is rendered on the page.
		throw new Error(
			__(
				'CoreX could not reach the access routes. Nothing changed — please try again.',
				'corex'
			)
		);
	}

	return ( await response.json() ).data;
}

/**
 * Ask what a staged change to one role would do.
 *
 * @param {Object} config  The localized screen config.
 * @param {string} role    The role's key.
 * @param {Object} changes Ability key => effect.
 * @return {Promise<{allowed:boolean,blockers:Array,changes:Object,target_hash:string}>} The preview.
 */
export function previewRoleChanges( config, role, changes ) {
	return post( config, `roles/${ encodeURIComponent( role ) }/preview`, {
		changes,
	} );
}

/**
 * Apply a change that was previewed.
 *
 * @param {Object} config  The localized screen config.
 * @param {string} role    The role's key.
 * @param {Object} preview The preview being confirmed: its `changes` and `target_hash`.
 * @return {Promise<{state:string,message:string,errors:Array}>} What the server did.
 */
export async function applyRoleChanges( config, role, preview ) {
	const data = await post(
		config,
		`roles/${ encodeURIComponent( role ) }/apply`,
		{
			changes: preview.changes,
			confirmation: {
				operation_kind: ROLE_CHANGE_OPERATION,
				target_hash: preview.target_hash,
				actor_id: config.actorId,
				expires_at: new Date(
					Date.now() + CONFIRMATION_LIFETIME_MS
				).toISOString(),
			},
		}
	);

	return data.result;
}
