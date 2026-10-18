/**
 * An installation under way, and how it ended (spec 107, US1 and US3; FR-022, FR-030, FR-032).
 *
 * The steps are listed once and the one being done is said in words, with how far into it the
 * site has got where the site counts. Colour marks nothing here that a word does not.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/** The steps in the order they are done, each with what the site is doing in it. */
const STEPS = [
	[ 'unpack', __( 'Unpack the package beside the site', 'corex' ) ],
	[
		'verify',
		__( 'Check each folder against the package’s description', 'corex' ),
	],
	[ 'copy_tables', __( 'Copy CoreX’s tables as they are', 'corex' ) ],
	[ 'swap', __( 'Put the release in place', 'corex' ) ],
	[ 'finish', __( 'Bring the database to the release', 'corex' ) ],
];

const placeOf = ( step ) => STEPS.findIndex( ( [ name ] ) => name === step );

/**
 * A heading that takes the focus when its panel arrives: each of these panels is the answer to
 * a button pressed in the panel it replaced.
 *
 * @param {Object} props          Component props.
 * @param {string} props.id       The heading's id, which its section is labelled by.
 * @param {Object} props.children What it says.
 * @return {Element} The heading.
 */
export function ArrivingHeading( { id, children } ) {
	const heading = useRef( null );

	useEffect( () => {
		heading.current.focus();
	}, [] );

	return (
		<h2
			id={ id }
			className="corex-releases__heading"
			ref={ heading }
			tabIndex={ -1 }
		>
			{ children }
		</h2>
	);
}

function stateOf( at, place ) {
	if ( place < at ) {
		return [ 'done', __( 'Done', 'corex' ) ];
	}

	return place === at ? [ 'now', __( 'Now', 'corex' ) ] : [ 'waiting', '' ];
}

/**
 * @param {Object} props         Component props.
 * @param {Object} props.stand   Where the installation stands: `{ step, cursor, of }`, and `waiting` while the site has not answered the last request.
 * @param {string} props.version The release being installed.
 * @return {Element} The panel.
 */
export function ReleaseProgress( { stand, version } ) {
	const at = placeOf( stand.step );
	const counted = stand.of > 0;

	return (
		<section
			className="corex-releases__panel corex-releases__run"
			aria-labelledby="corex-releases-run"
		>
			<ArrivingHeading id="corex-releases-run">
				{ sprintf(
					/* translators: %s: a CoreX version number. */
					__( 'Installing CoreX %s', 'corex' ),
					version
				) }
			</ArrivingHeading>
			<p className="corex-releases__line">
				{ __(
					'Keep this page open until it says the release is installed. If it is closed, open Releases again to go on from where it stopped.',
					'corex'
				) }
			</p>
			<ol className="corex-releases__steps">
				{ STEPS.map( ( [ name, label ], place ) => {
					const [ state, word ] = stateOf( at, place );

					return (
						<li
							key={ name }
							className="corex-releases__step"
							data-corex-step={ state }
							aria-current={
								state === 'now' ? 'step' : undefined
							}
						>
							<span className="corex-releases__step-name">
								{ label }
							</span>
							<span className="corex-releases__step-state">
								{ word }
							</span>
						</li>
					);
				} ) }
			</ol>
			<div className="corex-releases__progress">
				<progress
					max={ counted ? stand.of : undefined }
					value={ counted ? stand.cursor : undefined }
					aria-label={ __( 'This step', 'corex' ) }
				/>
				<p
					className="corex-releases__line corex-releases__line--quiet"
					role="status"
				>
					{ sprintf(
						/* translators: 1: which step this is, a number. 2: how many steps there are. 3: what the step does. */
						__( 'Step %1$d of %2$d: %3$s.', 'corex' ),
						at + 1,
						STEPS.length,
						STEPS[ at ]?.[ 1 ] ?? ''
					) }
					{ counted &&
						' ' +
							sprintf(
								/* translators: 1: how much of a step is done, a number. 2: how much there is in all. */
								__( '%1$s of %2$s.', 'corex' ),
								Number( stand.cursor ).toLocaleString(),
								Number( stand.of ).toLocaleString()
							) }
					{ stand.waiting &&
						' ' +
							__(
								'The site has not answered yet. While a release is put in place WordPress holds the site in maintenance, for up to ten minutes; this page goes on asking until it answers.',
								'corex'
							) }
				</p>
			</div>
		</section>
	);
}

function changeSentence( { component, from, to } ) {
	return from === ''
		? sprintf(
				/* translators: 1: the name of a part of CoreX's database. 2: its version. */
				__( '%1$s was made, at version %2$s.', 'corex' ),
				component,
				to
			)
		: sprintf(
				/* translators: 1: the name of a part of CoreX's database. 2: the version it was at. 3: the version it is at now. */
				__( '%1$s went from version %2$s to %3$s.', 'corex' ),
				component,
				from,
				to
			);
}

/**
 * @param {Object}     props         Component props.
 * @param {Object}     props.outcome `{ version, changes }`.
 * @param {() => void} props.onClose Called when the administrator has read it.
 * @return {Element} The panel.
 */
export function ReleaseInstalled( { outcome, onClose } ) {
	return (
		<section
			className="corex-releases__panel corex-releases__installed"
			aria-labelledby="corex-releases-installed"
		>
			<ArrivingHeading id="corex-releases-installed">
				{ sprintf(
					/* translators: %s: a CoreX version number. */
					__( 'CoreX %s is installed', 'corex' ),
					outcome.version
				) }
			</ArrivingHeading>
			{ outcome.changes.length === 0 ? (
				<p className="corex-releases__line">
					{ __( 'No change to the database was needed.', 'corex' ) }
				</p>
			) : (
				<>
					<p className="corex-releases__line">
						{ __(
							'The database was brought to the release:',
							'corex'
						) }
					</p>
					<ul className="corex-releases__changes">
						{ outcome.changes.map( ( change ) => (
							<li
								key={ `${ change.site }:${ change.component }` }
							>
								{ changeSentence( change ) }
							</li>
						) ) }
					</ul>
				</>
			) }
			<p className="corex-releases__line corex-releases__line--quiet">
				{ __(
					'The release it replaced is kept until the next one is installed, and the recovery link still puts it back.',
					'corex'
				) }
			</p>
			<div className="corex-releases__actions">
				<button type="button" className="button" onClick={ onClose }>
					{ __( 'Done', 'corex' ) }
				</button>
			</div>
		</section>
	);
}
