/**
 * The release before, and the way back to it (spec 107, US4; FR-040, FR-041, FR-043, FR-044).
 *
 * Which release is kept and which one replaced it; the copies of CoreX's tables made before
 * that installation, and when they go; and going back, which is one confirmed action. What
 * going back does not undo is said before the confirmation is asked for.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { formatDateTime } from '../admin/adminDateTime.js';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import { usePending, workingProps } from '../admin/components/working.js';

/**
 * What is kept, in a sentence.
 *
 * @param {Object} previous The site's account of the kept release.
 * @return {string} The sentence.
 */
function keptSentence( previous ) {
	const when = formatDateTime( previous.at ).human;

	return previous.version
		? sprintf(
				/* translators: 1: the version of the release that is kept. 2: the version of the release that replaced it. 3: a date and time. */
				__(
					'CoreX %1$s is kept. CoreX %2$s replaced it on %3$s.',
					'corex'
				),
				previous.version,
				previous.replaced_by,
				when
			)
		: sprintf(
				/* translators: 1: the version of the release that replaced the one kept. 2: a date and time. */
				__(
					'The release this site ran before is kept. It did not say which it was. CoreX %1$s replaced it on %2$s.',
					'corex'
				),
				previous.replaced_by,
				when
			);
}

function Copies( { copies, days } ) {
	if ( copies.length === 0 ) {
		return (
			<p className="corex-releases__line corex-releases__line--quiet">
				{ __(
					'No copy of CoreX’s tables is kept from before that installation.',
					'corex'
				) }
			</p>
		);
	}

	return (
		<p className="corex-releases__line corex-releases__line--quiet">
			{ sprintf(
				/* translators: 1: a number of database tables. 2: a number of days. 3: the name of one of the copies. 4: a date. */
				_n(
					'A copy of %1$d of CoreX’s tables as it was before that installation is in the database, for a person to read from. It is named %3$s, is kept %2$d days, and is removed on %4$s.',
					'A copy of each of %1$d of CoreX’s tables as it was before that installation is in the database, for a person to read from. They are named like %3$s, are kept %2$d days, and are removed on %4$s.',
					copies.length,
					'corex'
				),
				copies.length,
				days,
				copies[ 0 ].copy,
				formatDateTime( copies[ 0 ].goes_at, 'date' ).human
			) }
		</p>
	);
}

/**
 * @param {Object}                     props            Component props.
 * @param {Object}                     props.previous   The site's account of the kept release.
 * @param {Object}                     props.client     The installer's client.
 * @param {(returned: Object) => void} props.onReturned Called with the site's answer once it has gone back.
 * @return {Element} The panel.
 */
export default function ReleaseKept( { previous, client, onReturned } ) {
	const [ pending, during ] = usePending();
	const [ asking, setAsking ] = useState( false );
	const [ typed, setTyped ] = useState( '' );
	const [ refusal, setRefusal ] = useState( '' );
	const name = previous.version
		? /* translators: %s: a CoreX version number. */
			sprintf( __( 'CoreX %s', 'corex' ), previous.version )
		: __( 'the release before', 'corex' );

	const goBack = async () => {
		setRefusal( '' );
		try {
			onReturned( await client.goBack( typed.trim() ) );
		} catch ( failure ) {
			setRefusal( failure.message );
		}
	};

	return (
		<section
			className="corex-releases__panel corex-releases__kept"
			aria-labelledby="corex-releases-kept"
		>
			<h2 id="corex-releases-kept" className="corex-releases__heading">
				{ __( 'The release before', 'corex' ) }
			</h2>
			<p className="corex-releases__line">{ keptSentence( previous ) }</p>
			{ previous.status === 'stuck' && (
				<ul className="corex-releases__cautions">
					<li>
						{ __(
							'An earlier attempt to go back left a folder where it does not belong: the site is part one release and part the other. Going back again tries the rest; so does the recovery link.',
							'corex'
						) }
					</li>
				</ul>
			) }
			<Copies copies={ previous.copies } days={ previous.copies_days } />
			{ ! asking ? (
				<div className="corex-releases__actions">
					<button
						type="button"
						className="button"
						onClick={ () => setAsking( true ) }
					>
						{ sprintf(
							/* translators: %s: the release to go back to, e.g. "CoreX 0.44.0". */
							__( 'Go back to %s…', 'corex' ),
							name
						) }
					</button>
				</div>
			) : (
				<form
					className="corex-releases__group"
					onSubmit={ ( event ) => {
						event.preventDefault();
						if (
							typed.trim() === previous.phrase &&
							pending === ''
						) {
							during( 'return', goBack );
						}
					} }
				>
					<p className="corex-releases__line">
						{ __(
							'Going back puts the previous release’s files in place at once. It reverses no change to the database: a table or column the newer release added stays, and the previous release runs on the tables as they are now. The newer release’s package is not kept: to install it again, send it again.',
							'corex'
						) }
					</p>
					<label
						className="corex-releases__label"
						htmlFor="corex-releases-return"
					>
						{ sprintf(
							/* translators: %s: what has to be typed, a version number. */
							__( 'Type %s to confirm', 'corex' ),
							previous.phrase
						) }
					</label>
					<input
						id="corex-releases-return"
						className="corex-releases__field"
						type="text"
						dir="ltr"
						autoComplete="off"
						value={ typed }
						disabled={ pending !== '' }
						onChange={ ( event ) => setTyped( event.target.value ) }
					/>
					{ refusal && (
						<CorexErrorState scale="action" message={ refusal } />
					) }
					<div className="corex-releases__actions">
						<button
							type="submit"
							className="button button-primary"
							disabled={
								typed.trim() !== previous.phrase ||
								pending !== ''
							}
							{ ...workingProps( pending === 'return' ) }
						>
							{ sprintf(
								/* translators: %s: the release to go back to, e.g. "CoreX 0.44.0". */
								__( 'Go back to %s', 'corex' ),
								name
							) }
						</button>
						<button
							type="button"
							className="button"
							disabled={ pending !== '' }
							onClick={ () => setAsking( false ) }
						>
							{ __( 'Stay on this release', 'corex' ) }
						</button>
					</div>
				</form>
			) }
		</section>
	);
}
