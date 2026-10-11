/**
 * What a package is, stated before anything on the site changes (spec 107, FR-010, FR-015,
 * FR-017, FR-026): its release, when it was built and for whom, what it would replace, and
 * whatever on this host or about this package a person has to know before installing it. Where
 * nothing stands in the way, the way on to installing it is here.
 *
 * The heading takes the focus when the statement arrives. It is the answer to a button that
 * was pressed somewhere above it, and a screen reader is otherwise told nothing.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../admin/components/CorexTime.js';
import { workingProps } from '../admin/components/working.js';
import { sizeOf } from '../Submissions/export/exportState.js';

/**
 * The lines of the statement, each a name and what the package says for it.
 *
 * @param {Object} statement The site's answer for the package.
 * @return {Array} `[ name, value ]` pairs, in the order they are read.
 */
function factsOf( statement ) {
	return [
		[
			__( 'Release', 'corex' ),
			/* translators: %s: a CoreX version number. */
			sprintf( __( 'CoreX %s', 'corex' ), statement.version ),
		],
		[
			__( 'Built', 'corex' ),
			<CorexTime key="built" value={ statement.built_at } />,
		],
		[
			__( 'Built for', 'corex' ),
			statement.client ?? __( 'The framework alone', 'corex' ),
		],
		[
			__( 'Replaces', 'corex' ),
			statement.replaces
				? /* translators: %s: a CoreX version number. */
					sprintf( __( 'CoreX %s', 'corex' ), statement.replaces )
				: __( 'A release this site cannot name', 'corex' ),
		],
		[ __( 'Files', 'corex' ), Number( statement.files ).toLocaleString() ],
		[ __( 'Size on the site', 'corex' ), sizeOf( statement.bytes ) ],
	];
}

/**
 * What a person has to know about this package before installing it, beyond what it is.
 *
 * @param {Object} statement The site's answer for the package.
 * @return {string[]} Sentences; none when there is nothing to add.
 */
function cautionsOf( statement ) {
	const cautions = [];

	if ( statement.older ) {
		cautions.push(
			__(
				'This is an older release than the one this site is running. Installing it will need confirming a second time.',
				'corex'
			)
		);
	}
	if ( statement.client_unconfirmed ) {
		cautions.push(
			__(
				'This site cannot say whose it is, so nothing was compared. Check the package is built for this site before installing it.',
				'corex'
			)
		);
	}
	const { package: builtWith, site: runs } = statement.wordpress ?? {};
	if ( builtWith && runs && builtWith !== runs ) {
		cautions.push(
			sprintf(
				/* translators: 1: the WordPress version a package was built with. 2: the WordPress version the site runs. */
				__(
					'This package was built with WordPress %1$s and this site runs %2$s. Installing a release does not replace WordPress.',
					'corex'
				),
				builtWith,
				runs
			)
		);
	}

	return [
		...cautions,
		...statement.blockers.map( ( blocker ) => blocker.message ),
	];
}

/**
 * @param {Object}     props           Component props.
 * @param {Object}     props.statement The site's answer for the package.
 * @param {string}     props.pending   The action under way on the screen, if any.
 * @param {() => void} props.onInstall Called when the administrator asks to install it.
 * @return {Element} The statement.
 */
export default function ReleaseStatement( { statement, pending, onInstall } ) {
	const heading = useRef( null );
	const cautions = cautionsOf( statement );

	useEffect( () => {
		heading.current.focus();
	}, [ statement ] );

	return (
		<section
			className="corex-releases__panel corex-releases__statement"
			aria-labelledby="corex-releases-statement"
		>
			<h2
				id="corex-releases-statement"
				className="corex-releases__heading"
				ref={ heading }
				tabIndex={ -1 }
			>
				{ __( 'What this package is', 'corex' ) }
			</h2>
			<p className="corex-releases__package">{ statement.package }</p>
			<dl className="corex-releases__facts">
				{ factsOf( statement ).map( ( [ name, value ] ) => (
					<div className="corex-releases__fact" key={ name }>
						<dt>{ name }</dt>
						<dd>{ value }</dd>
					</div>
				) ) }
			</dl>
			{ cautions.length > 0 && (
				<ul className="corex-releases__cautions">
					{ cautions.map( ( caution ) => (
						<li key={ caution }>{ caution }</li>
					) ) }
				</ul>
			) }
			{ statement.blockers.length > 0 ? (
				<p className="corex-releases__line">
					{ __(
						'This package cannot be installed on this host until what is listed above is dealt with. Reading it changed nothing on the site.',
						'corex'
					) }
				</p>
			) : (
				<>
					<p className="corex-releases__line">
						{ __(
							'Reading a package changes nothing on the site. Neither does the next step: it says what installing does and gives you the way back, and asks you to confirm.',
							'corex'
						) }
					</p>
					<div className="corex-releases__actions">
						<button
							type="button"
							className="button button-primary"
							disabled={ pending !== '' }
							onClick={ onInstall }
							{ ...workingProps( pending === 'prepare' ) }
						>
							{ __( 'Install this release…', 'corex' ) }
						</button>
					</div>
				</>
			) }
		</section>
	);
}
