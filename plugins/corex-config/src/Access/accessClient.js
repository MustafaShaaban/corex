/**
 * The requests behind the role matrix's "Preview changes" (#313), and behind approving a request
 * and the grants it makes (spec 112).
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

/** Must be `AccessService::APPROVAL_OPERATION`: the server checks the confirmation against it. */
const APPROVAL_OPERATION = 'access.request.approve';

/** How long somebody has between reading a preview and applying it. */
const CONFIRMATION_LIFETIME_MS = 5 * 60 * 1000;

async function post( config, path, body ) {
	return send( config, path, {
		method: 'POST',
		body: JSON.stringify( body ),
	} );
}

async function send( config, path, request ) {
	const response = await window.fetch( `${ config.restUrl }/${ path }`, {
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': config.nonce,
		},
		...request,
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

/**
 * What approving a request would grant, read before confirming (spec 112, FR-002).
 *
 * @param {Object} config The localized screen config.
 * @param {number} id     The request's id.
 * @return {Promise<Object|null>} The preview, or null when the request is no longer pending.
 */
export async function previewApproval( config, id ) {
	return ( await post( config, `requests/${ id }/preview`, {} ) ).preview;
}

/**
 * Approve a previewed request.
 *
 * @param {Object} config            The localized screen config.
 * @param {Object} preview           The preview being confirmed.
 * @param {Object} choice            What the approver chose.
 * @param {string} choice.note       Why access is approved.
 * @param {number} choice.expiryDays When the grant ends, in days.
 * @param {string} choice.typed      The name typed, when the preview asks for one.
 * @return {Promise<{state:string,errors:Array}>} What the server did.
 */
export async function approveRequest(
	config,
	preview,
	{ note, expiryDays, typed }
) {
	const confirmation = {
		operation_kind: APPROVAL_OPERATION,
		target_hash: preview.target_hash,
		actor_id: config.actorId,
		expires_at: new Date(
			Date.now() + CONFIRMATION_LIFETIME_MS
		).toISOString(),
	};
	if ( preview.requires_typed_name ) {
		confirmation.required_phrase = typed;
	}

	const data = await post(
		config,
		`requests/${ preview.request_id }/decision`,
		{
			approved: true,
			note,
			expiry_days: expiryDays,
			confirmation,
		}
	);

	return data.result;
}

/**
 * Deny a request.
 *
 * @param {Object} config The localized screen config.
 * @param {number} id     The request's id.
 * @return {Promise<{state:string,errors:Array}>} What the server did.
 */
export async function denyRequest( config, id ) {
	return (
		await post( config, `requests/${ id }/decision`, {
			approved: false,
			note: '',
		} )
	).result;
}

/**
 * The grants made by approval that stand.
 *
 * @param {Object} config The localized screen config.
 * @return {Promise<Array>} The grants, newest first.
 */
export async function fetchGrants( config ) {
	return ( await send( config, 'grants', { method: 'GET' } ) ).grants;
}

/**
 * Revoke a grant.
 *
 * @param {Object} config The localized screen config.
 * @param {number} id     The grant's id: the approved request's.
 * @return {Promise<{state:string,errors:Array}>} What the server did.
 */
export async function revokeGrant( config, id ) {
	return ( await post( config, `grants/${ id }/revoke`, {} ) ).result;
}

/**
 * What a refused answer says, for the page.
 *
 * @param {{state:string,errors:Array}} result The server's answer.
 * @return {string} The first error's message, or a general one.
 */
export function refusalOf( result ) {
	return (
		result?.errors?.[ 0 ]?.message ||
		__( 'CoreX could not do that. Nothing changed.', 'corex' )
	);
}
