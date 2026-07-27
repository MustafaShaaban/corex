import { useReducer } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	buildLoginPolicyPayload,
	initialSecurityState,
	lockoutSummary,
	securityEndpoint,
	securityReducer,
} from './securityCenterState.js';

export default function SecurityCenter( { config = {} } ) {
	const [ state, dispatch ] = useReducer(
		securityReducer,
		initialSecurityState(),
		( initial ) =>
			securityReducer( initial, { type: 'loaded', payload: config } )
	);
	const lockouts = lockoutSummary( state.lockouts );
	const policyPayload = buildLoginPolicyPayload( state.loginPolicy );

	return (
		<div className="corex-security" data-testid="corex-security-center">
			<LaunchChecklist state={ state } />
			<LoginPolicy
				policy={ state.loginPolicy }
				policyPayload={ policyPayload }
				dispatch={ dispatch }
				config={ config }
				saving={ state.status === 'saving' }
			/>
			<div className="corex-security__grid">
				<Lockouts lockouts={ state.lockouts } summary={ lockouts } />
				<Recovery config={ config } />
				<Activity activity={ state.activity } />
			</div>
			{ state.notice && (
				<p
					className={ `corex-security__notice is-${ state.notice.tone }` }
					role="status"
				>
					{ state.notice.message }
				</p>
			) }
		</div>
	);
}

/**
 * Production readiness — evidence, not a control.
 *
 * It used to carry a target-mode selector, a "Type PRODUCTION" box and a maintenance confirmation,
 * none of which applied anything: the real, nonce- and capability-gated mode form is rendered by
 * OperationsSecurityScreen::modeCard() further down the same page. Two identical sets of controls,
 * one inert, sat one above the other.
 *
 * Removing them also fixes what they hid. The blocker badge read its count from `modeActionState`,
 * which reports zero for every mode except production — so the header claimed "Ready" while real
 * blockers were listed directly beneath it, unless you happened to pick Production in the preview.
 * It now reads the readiness snapshot itself, which is what it was always describing.
 *
 * @param {Object} root0       Props.
 * @param {Object} root0.state The security-center state.
 */
function LaunchChecklist( { state } ) {
	const blockingCount = state.readiness?.blockingCount || 0;

	return (
		<section className="corex-surface corex-security__panel">
			<header className="corex-security__head">
				<div>
					<p className="corex-admin__eyebrow">
						{ __( 'LAUNCH CHECKLIST', 'corex' ) }
					</p>
					<h2>{ __( 'Production readiness', 'corex' ) }</h2>
				</div>
				<span
					className={ blockingCount > 0 ? 'is-warning' : 'is-ready' }
				>
					{ blockingCount > 0
						? sprintf(
								/* translators: %d: number of blocking readiness checks. */
								__( '%d blocker(s)', 'corex' ),
								blockingCount
						  )
						: __( 'Ready', 'corex' ) }
				</span>
			</header>
			<ul className="corex-security__checks">
				{ state.readiness.checks.map( ( check ) => (
					<li key={ check.key } className={ `is-${ check.status }` }>
						<span>{ check.label }</span>
						<small>{ check.detail }</small>
					</li>
				) ) }
			</ul>
		</section>
	);
}

function LoginPolicy( {
	policy,
	policyPayload,
	dispatch,
	config = {},
	saving = false,
} ) {
	const save = async () => {
		if ( ! window.Corex || ! window.Corex.api || ! config.restUrl ) {
			dispatch( {
				type: 'error',
				message: __( 'The security API is unavailable.', 'corex' ),
			} );
			return;
		}
		dispatch( { type: 'savingLoginPolicy' } );
		const result = await window.Corex.api.post(
			securityEndpoint( config.restUrl, 'login-protection' ),
			policyPayload,
			{ nonce: config.nonce }
		);
		if ( result && result.ok ) {
			dispatch( {
				type: 'savedLoginPolicy',
				policy:
					result.envelope?.data?.login_protection || policyPayload,
				message: result.envelope?.data?.message,
			} );
		} else {
			dispatch( {
				type: 'error',
				message: __(
					'Could not save login protection settings.',
					'corex'
				),
			} );
		}
	};

	return (
		<section className="corex-surface corex-security__panel">
			<header className="corex-security__head">
				<div>
					<p className="corex-admin__eyebrow">
						{ __( 'LOGIN POLICY', 'corex' ) }
					</p>
					<h2>{ __( 'Protection settings', 'corex' ) }</h2>
				</div>
				<span>
					{ policy.enabled
						? __( 'Enabled', 'corex' )
						: __( 'Disabled', 'corex' ) }
				</span>
			</header>
			{ policy.slugSubstituted && (
				<p className="corex-security__notice is-error" role="alert">
					{ sprintf(
						/* translators: 1: the unusable saved address, 2: the address in use instead. */
						__(
							'The saved login address (%1$s) cannot be used, so CoreX is serving the login at %2$s instead. Save a valid address to resolve this.',
							'corex'
						),
						policy.storedSlug,
						policy.customSlug
					) }
				</p>
			) }
			<div className="corex-security__policy">
				<label>
					<input
						type="checkbox"
						checked={ policy.enabled }
						onChange={ ( event ) =>
							dispatch( {
								type: 'setLoginPolicy',
								patch: { enabled: event.target.checked },
							} )
						}
					/>
					{ __( 'Enable failed-login protection', 'corex' ) }
				</label>
				<label>
					<input
						type="checkbox"
						checked={ policy.blockDefaultEndpoints }
						onChange={ ( event ) =>
							dispatch( {
								type: 'setLoginPolicy',
								patch: {
									blockDefaultEndpoints: event.target.checked,
								},
							} )
						}
					/>
					{ __( 'Hide wp-login.php and wp-admin', 'corex' ) }
				</label>
				<label>
					{ __( 'Custom login address', 'corex' ) }
					<input
						type="text"
						value={ policy.customSlug }
						onChange={ ( event ) =>
							dispatch( {
								type: 'setLoginPolicy',
								patch: { customSlug: event.target.value },
							} )
						}
					/>
				</label>
				<label>
					{ __( 'Max attempts', 'corex' ) }
					<input
						type="number"
						min="1"
						max="50"
						value={ policy.maxAttempts }
						onChange={ ( event ) =>
							dispatch( {
								type: 'setLoginPolicy',
								patch: { maxAttempts: event.target.value },
							} )
						}
					/>
				</label>
			</div>
			{ policy.loginUrl && (
				/* Where the login IS, from the saved settings — not where the unsaved checkboxes
				   above imply it would be. Showing a predicted address next to a live one is how
				   an owner ends up bookmarking a URL that does not exist yet. It refreshes from
				   the save response. */
				<p className="corex-security__login-url">
					{ __( 'Sign in at:', 'corex' ) }{ ' ' }
					<a href={ policy.loginUrl } rel="bookmark">
						{ policy.loginUrl }
					</a>
				</p>
			) }
			{ policy.enabled && policy.blockDefaultEndpoints && (
				<p className="corex-security__warning" role="note">
					{ sprintf(
						/* translators: %s: the CLI recovery command. */
						__(
							'Bookmark that address before you sign out. Once saved, wp-login.php and wp-admin return "not found" for everyone, and this address is the only way in. If you lose it, run %s.',
							'corex'
						),
						config.recoveryCommand ||
							'wp corex security reset-login'
					) }
				</p>
			) }
			<div className="corex-security__actions">
				<button
					type="button"
					className="button button-primary"
					disabled={ saving }
					onClick={ save }
				>
					{ saving
						? __( 'Saving…', 'corex' )
						: __( 'Save login protection', 'corex' ) }
				</button>
			</div>
		</section>
	);
}

function Lockouts( { lockouts, summary } ) {
	return (
		<section className="corex-surface corex-security__panel">
			<h2>{ __( 'Lockouts', 'corex' ) }</h2>
			<p>
				{ sprintf(
					/* translators: 1: active lockouts, 2: expired lockouts. */
					__( '%1$d active · %2$d expired', 'corex' ),
					summary.active,
					summary.expired
				) }
			</p>
			{ lockouts.length === 0 ? (
				<p>
					{ __( 'No login lockouts have been recorded.', 'corex' ) }
				</p>
			) : (
				<ul className="corex-security__lockouts">
					{ lockouts.map( ( lockout ) => (
						<li
							key={ lockout.id }
							className={
								lockout.active ? 'is-active' : 'is-expired'
							}
						>
							<span>
								{ lockout.account ||
									sprintf(
										/* translators: %s: a short fingerprint of the hashed identity. */
										__(
											'Unrecognised sign-in (%s)',
											'corex'
										),
										lockout.identity
									) }
							</span>
							<small>
								{ lockout.active
									? sprintf(
											/* translators: %s: date and time the lockout ends. */
											__( 'Locked until %s', 'corex' ),
											lockout.lockedUntil
									  )
									: sprintf(
											/* translators: %s: date and time the lockout ended. */
											__( 'Expired %s', 'corex' ),
											lockout.lockedUntil
									  ) }
							</small>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}

/**
 * Recovery is documentation, not a control.
 *
 * It used to carry a "Mark command reviewed" button that only flipped a label — no server call, no
 * effect. Recovery necessarily runs from the CLI: it exists for the case where the admin cannot be
 * reached, so a button inside the admin could not perform it even in principle. Showing the command
 * is the honest whole of what this panel can do.
 * @param root0
 * @param root0.config
 */
function Recovery( { config } ) {
	const command = config.recoveryCommand || 'wp corex security reset-login';

	return (
		<section className="corex-surface corex-security__panel">
			<h2>{ __( 'Recovery', 'corex' ) }</h2>
			<p>
				{ __(
					'Locked out? Run this on the server to restore the default login address and release every active lockout. Your users and passwords are untouched.',
					'corex'
				) }
			</p>
			<code>{ command }</code>
		</section>
	);
}

function Activity( { activity } ) {
	return (
		<section className="corex-surface corex-security__panel">
			<h2>{ __( 'Security activity', 'corex' ) }</h2>
			{ activity.length === 0 ? (
				<p>
					{ __(
						'No security events are present in the localized snapshot.',
						'corex'
					) }
				</p>
			) : (
				<ul>
					{ activity.map( ( event ) => (
						<li key={ event.id }>
							<span>{ event.label }</span>
							<small>{ event.occurredAt }</small>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}
