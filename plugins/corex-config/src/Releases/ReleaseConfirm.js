/**
 * What an administrator is told, and asked, before a release is installed (spec 107, FR-015,
 * FR-028, FR-041, FR-042, FR-050, FR-062; plan D8).
 *
 * Told: what is replaced and what leaves the site with it, the way back that needs no admin,
 * what going back does not undo, and that none of this is a backup. Asked: the version, typed.
 *
 * The way back is first, and can be saved as a file, because it is for the day this screen
 * does not open.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import { workingProps } from '../admin/components/working.js';
import { saveRecoveryFile } from './recoveryFile.js';

function Folders( { folders } ) {
	return (
		<ul className="corex-releases__cautions">
			{ folders.map( ( folder ) => (
				<li key={ folder }>
					<code>{ folder }</code>
				</li>
			) ) }
		</ul>
	);
}

function WayBack( { prepared } ) {
	return (
		<div className="corex-releases__group">
			<h3 className="corex-releases__subheading">
				{ __( 'Keep the way back first', 'corex' ) }
			</h3>
			<p className="corex-releases__line">
				{ __(
					'If the site does not load after the release is installed, this address puts the previous release back and takes the site out of maintenance. It works without the admin. It is shown here once.',
					'corex'
				) }
			</p>
			<label
				className="corex-releases__label"
				htmlFor="corex-releases-recovery"
			>
				{ __( 'Recovery link', 'corex' ) }
			</label>
			<div className="corex-releases__send">
				<input
					id="corex-releases-recovery"
					className="corex-releases__field corex-releases__field--wide"
					type="text"
					readOnly
					dir="ltr"
					value={ prepared.recovery_link }
					onFocus={ ( event ) => event.target.select() }
				/>
				<button
					type="button"
					className="button"
					onClick={ () =>
						saveRecoveryFile( {
							link: prepared.recovery_link,
							version: prepared.version,
						} )
					}
				>
					{ __( 'Save it as a file', 'corex' ) }
				</button>
			</div>
		</div>
	);
}

function WhatChanges( { prepared } ) {
	return (
		<div className="corex-releases__group">
			<h3 className="corex-releases__subheading">
				{ __( 'What installing does', 'corex' ) }
			</h3>
			<p className="corex-releases__line">
				{ __(
					'Each folder the release holds is replaced whole: a file the release no longer has does not stay, and a change made on the host inside one of them is replaced. Uploads, the site’s configuration, WordPress itself and any plugin or theme the package does not name are left as they are. Visitors see a brief maintenance notice while the release is put in place.',
					'corex'
				) }
			</p>
			{ prepared.drops.length > 0 && (
				<>
					<p className="corex-releases__line">
						{ __(
							'The running release holds these folders and this package does not. They leave the site with the previous release; a plugin among them is switched off by WordPress:',
							'corex'
						) }
					</p>
					<Folders folders={ prepared.drops } />
				</>
			) }
			<p className="corex-releases__line">
				{ sprintf(
					/* translators: %d: a number of days. */
					__(
						'Going back puts the previous release’s files back. It reverses no change to the database: a table or column this release adds stays. A copy of CoreX’s tables as they are now is kept for %d days, for a person to read from. This is not a backup of the site: a backup plugin or the host’s backups are what restore a broken one.',
						'corex'
					),
					prepared.copies_days
				) }
			</p>
		</div>
	);
}

/**
 * @param {Object}                                props           Component props.
 * @param {Object}                                props.prepared  The site's answer to `prepare`.
 * @param {boolean}                               props.working   Whether the confirmation has been sent and not answered.
 * @param {string}                                props.problem   Why the last confirmation was not taken, in the site's words.
 * @param {(typed: string, more: Object) => void} props.onConfirm Called with what was typed and `{ client, older }`.
 * @param {() => void}                            props.onCancel  Called when the administrator decides not to.
 * @return {Element} The panel.
 */
export default function ReleaseConfirm( {
	prepared,
	working,
	problem,
	onConfirm,
	onCancel,
} ) {
	const heading = useRef( null );
	const [ typed, setTyped ] = useState( '' );
	const [ client, setClient ] = useState( '' );
	const [ older, setOlder ] = useState( false );

	useEffect( () => {
		heading.current.focus();
	}, [] );

	const asksClient = prepared.client_unconfirmed && prepared.client;
	const ready =
		typed.trim() === prepared.version &&
		( ! prepared.older || older ) &&
		( ! asksClient || client.trim() === prepared.client );

	return (
		<section
			className="corex-releases__panel corex-releases__confirm"
			aria-labelledby="corex-releases-confirm"
		>
			<h2
				id="corex-releases-confirm"
				className="corex-releases__heading"
				ref={ heading }
				tabIndex={ -1 }
			>
				{ sprintf(
					/* translators: %s: a CoreX version number. */
					__( 'Install CoreX %s', 'corex' ),
					prepared.version
				) }
			</h2>
			<WayBack prepared={ prepared } />
			<WhatChanges prepared={ prepared } />
			<form
				className="corex-releases__group"
				onSubmit={ ( event ) => {
					event.preventDefault();
					if ( ready && ! working ) {
						onConfirm( typed.trim(), {
							older,
							...( asksClient && { client: client.trim() } ),
						} );
					}
				} }
			>
				<label
					className="corex-releases__label"
					htmlFor="corex-releases-version"
				>
					{ sprintf(
						/* translators: %s: a CoreX version number, to be typed. */
						__(
							'Type the version being installed, %s, to confirm',
							'corex'
						),
						prepared.version
					) }
				</label>
				<input
					id="corex-releases-version"
					className="corex-releases__field"
					type="text"
					dir="ltr"
					autoComplete="off"
					value={ typed }
					disabled={ working }
					onChange={ ( event ) => setTyped( event.target.value ) }
				/>
				{ asksClient && (
					<>
						<label
							className="corex-releases__label"
							htmlFor="corex-releases-client"
						>
							{ sprintf(
								/* translators: %s: the name of the client a package was built for, to be typed. */
								__(
									'This site cannot say whose it is. Type the client this package was built for, %s, to confirm it is this site’s',
									'corex'
								),
								prepared.client
							) }
						</label>
						<input
							id="corex-releases-client"
							className="corex-releases__field"
							type="text"
							dir="ltr"
							autoComplete="off"
							value={ client }
							disabled={ working }
							onChange={ ( event ) =>
								setClient( event.target.value )
							}
						/>
					</>
				) }
				{ prepared.older && (
					<div className="corex-releases__check">
						<input
							id="corex-releases-older"
							type="checkbox"
							checked={ older }
							disabled={ working }
							onChange={ ( event ) =>
								setOlder( event.target.checked )
							}
						/>
						<label htmlFor="corex-releases-older">
							{ __(
								'This is an older release than the one the site runs, and that is what I mean to install.',
								'corex'
							) }
						</label>
					</div>
				) }
				{ problem && (
					<CorexErrorState scale="action" message={ problem } />
				) }
				<div className="corex-releases__actions">
					<button
						type="submit"
						className="button button-primary"
						disabled={ ! ready || working }
						{ ...workingProps( working ) }
					>
						{ sprintf(
							/* translators: %s: a CoreX version number. */
							__( 'Install CoreX %s', 'corex' ),
							prepared.version
						) }
					</button>
					<button
						type="button"
						className="button"
						disabled={ working }
						onClick={ onCancel }
					>
						{ __( 'Do not install', 'corex' ) }
					</button>
				</div>
			</form>
		</section>
	);
}
