/**
 * What happened to this site's release, newest first (spec 107, US5; FR-051).
 *
 * Each installation, refusal and return: when, what, and who. It is read from the installer's
 * own log, which is a file beside the releases and so is still there after a release whose
 * database change failed.
 */
import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../admin/components/CorexTime.js';

/** In the record, the version of a release the site could not name (`ReleaseReturn::UNNAMED`). */
const UNNAMED = 'unknown';

/**
 * What an entry of the record says happened.
 *
 * @param {Object} entry One event, as the site kept it.
 * @return {string} The sentence.
 */
function sentenceOf( entry ) {
	switch ( entry.event ) {
		case 'installed':
			return entry.from
				? sprintf(
						/* translators: 1: the version installed. 2: the version it replaced. */
						__(
							'CoreX %1$s was installed, replacing %2$s.',
							'corex'
						),
						entry.version,
						entry.from
					)
				: sprintf(
						/* translators: %s: the version installed. */
						__( 'CoreX %s was installed.', 'corex' ),
						entry.version
					);
		case 'returned':
			return entry.version === UNNAMED
				? sprintf(
						/* translators: %s: the version the site left. */
						__(
							'The site went back from CoreX %s to the release before it.',
							'corex'
						),
						entry.from
					)
				: sprintf(
						/* translators: 1: the version the site went back to. 2: the version it left. */
						__(
							'The site went back to CoreX %1$s from %2$s.',
							'corex'
						),
						entry.version,
						entry.from
					);
		case 'failed':
			return sprintf(
				/* translators: %s: the version that was being installed. */
				__( 'An installation of CoreX %s did not complete.', 'corex' ),
				entry.version
			);
		case 'cancelled':
			return sprintf(
				/* translators: %s: the version that was being installed. */
				__( 'An installation of CoreX %s was given up.', 'corex' ),
				entry.version
			);
		default:
			return sprintf(
				/* translators: %s: a package's file name. */
				__( 'A package was refused: %s', 'corex' ),
				entry.package
			);
	}
}

/**
 * Who did it, as far as the site knows.
 *
 * @param {Object} entry One event, as the site kept it.
 * @return {string} A name, or how it was done when nobody is known to have.
 */
function doneBy( entry ) {
	if ( entry.through === 'recovery_link' ) {
		return __( 'The recovery link', 'corex' );
	}

	const who =
		entry.by_name ||
		sprintf(
			/* translators: %d: a user's number on the site. */
			__( 'User %d', 'corex' ),
			entry.by
		);

	// Installed from a command on somebody's machine (spec 123, FR-014a).
	return entry.signed_in_with
		? sprintf(
				/* translators: 1: who installed a release. 2: the name of the Application Password used. */
				__( '%1$s, with the Application Password "%2$s"', 'corex' ),
				who,
				entry.signed_in_with
			)
		: who;
}

/**
 * @param {Object} props        Component props.
 * @param {Array}  props.record The events, newest first.
 * @return {Element} The panel.
 */
export default function ReleaseHistory( { record } ) {
	return (
		<section
			className="corex-releases__panel corex-releases__history"
			aria-labelledby="corex-releases-history"
		>
			<h2 id="corex-releases-history" className="corex-releases__heading">
				{ __( 'What happened here', 'corex' ) }
			</h2>
			{ record.length === 0 ? (
				<p className="corex-releases__line corex-releases__line--quiet">
					{ __(
						'Nothing has been installed, refused or put back from this screen yet.',
						'corex'
					) }
				</p>
			) : (
				<ol className="corex-releases__record">
					{ record.map( ( entry ) => (
						<li
							className="corex-releases__entry"
							key={ `${ entry.at }:${ entry.event }` }
						>
							<span className="corex-releases__what">
								{ sentenceOf( entry ) }
								{ entry.reason && (
									<code className="corex-releases__reason">
										{ entry.reason }
									</code>
								) }
							</span>
							<span className="corex-releases__who">
								{ doneBy( entry ) }
							</span>
							<CorexTime
								className="corex-releases__when"
								value={ entry.at }
							/>
						</li>
					) ) }
				</ol>
			) }
		</section>
	);
}
