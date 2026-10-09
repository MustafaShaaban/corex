/**
 * ReleasesApp — the CoreX → Releases screen (spec 107, slice 3).
 *
 * What a person is told before anything on the site changes: which release is running, what on
 * this host stands in the way of installing one, the packages the site holds, and for a package
 * that is read, what it is or why it is refused, in the site's own words.
 *
 * A package is sent in parts and its progress is the number of bytes the site says it holds,
 * not the number the browser has let go of. Nothing here installs: that is the next slice, and
 * until it is built the statement says so in a sentence where a button would otherwise be.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import CorexLoadable from '../admin/components/CorexLoadable.js';
import { usePending, workingProps } from '../admin/components/working.js';
import { sizeOf } from '../Submissions/export/exportState.js';
import { ReleaseRefusedError } from './releaseClient.js';
import ReleaseStatement from './ReleaseStatement.js';
import ReleasesSkeleton from './ReleasesSkeleton.js';

/**
 * Which release the site is running, as far as the site can say.
 *
 * @param {Object}      installed         What the site says of itself.
 * @param {boolean}     installed.known   Whether it can say anything.
 * @param {string|null} installed.version The release it is running.
 * @param {string|null} installed.client  The client that release was built for.
 * @return {string} The sentence.
 */
function runningSentence( { known, version, client } ) {
	if ( ! known ) {
		return __(
			'This site does not say which release it is running.',
			'corex'
		);
	}
	if ( client ) {
		return sprintf(
			/* translators: 1: a CoreX version number. 2: the name of the client the release was built for. */
			__( 'This site is running CoreX %1$s, built for %2$s.', 'corex' ),
			version,
			client
		);
	}

	return sprintf(
		/* translators: %s: a CoreX version number. */
		__( 'This site is running CoreX %s, the framework alone.', 'corex' ),
		version
	);
}

/**
 * What the screen shows when it is opened, asked again whenever the site has changed.
 *
 * @param {Object} client The installer's client.
 * @return {Object} `{ status, overview, error, load }`.
 */
function useOverview( client ) {
	const [ state, setState ] = useState( {
		status: 'loading',
		overview: null,
		error: '',
	} );

	// Which asking was the last. The list is asked for after a package arrives and again after
	// one is refused, and the answer that is kept is the later asking's, whichever comes first.
	const latest = useRef( 0 );

	const load = useCallback( async () => {
		const asking = ++latest.current;
		const keep = ( next ) => asking === latest.current && setState( next );

		setState( ( now ) => ( {
			...now,
			status: now.overview ? 'refreshing' : 'loading',
		} ) );
		try {
			const overview = await client.overview();
			keep( { status: 'ready', overview, error: '' } );
		} catch ( failure ) {
			keep( {
				status: 'error',
				overview: null,
				error: failure.message,
			} );
		}
	}, [ client ] );

	useEffect( () => {
		load();
	}, [ load ] );

	return { ...state, load };
}

function Panel( { id, title, children } ) {
	return (
		<section className="corex-releases__panel" aria-labelledby={ id }>
			<h2 id={ id } className="corex-releases__heading">
				{ title }
			</h2>
			{ children }
		</section>
	);
}

function Running( { installed, blockers } ) {
	return (
		<Panel
			id="corex-releases-running"
			title={ __( 'Running now', 'corex' ) }
		>
			<p className="corex-releases__line">
				{ runningSentence( installed ) }
			</p>
			{ blockers.length === 0 ? (
				<p className="corex-releases__line corex-releases__line--quiet">
					{ __(
						'Nothing on this host stands in the way of installing a release.',
						'corex'
					) }
				</p>
			) : (
				<>
					<p className="corex-releases__line">
						{ __(
							'A release cannot be installed on this host yet:',
							'corex'
						) }
					</p>
					<ul className="corex-releases__cautions">
						{ blockers.map( ( blocker ) => (
							<li key={ blocker.message }>{ blocker.message }</li>
						) ) }
					</ul>
				</>
			) }
		</Panel>
	);
}

/**
 * How far a package has got. Until the site has said how much of it it holds, the bar has no
 * value: the file is being read, which for a large package is seconds, not nothing.
 *
 * @param {Object}      props      Component props.
 * @param {number|null} props.held The bytes the site holds, or null before it has said.
 * @param {number}      props.size The package's size.
 * @return {Element} The bar and its sentence.
 */
function SendProgress( { held, size } ) {
	const sentence =
		held === null
			? __( 'Getting the file ready…', 'corex' )
			: sprintf(
					/* translators: 1: how much of a file has been sent, e.g. "12 MB". 2: the file's size. */
					__( '%1$s of %2$s is on the site.', 'corex' ),
					sizeOf( held ) || __( 'Nothing', 'corex' ),
					sizeOf( size )
				);

	return (
		<div className="corex-releases__progress">
			<progress
				max={ size }
				value={ held ?? undefined }
				aria-label={ __( 'Sending the package', 'corex' ) }
			/>
			<p className="corex-releases__line corex-releases__line--quiet">
				{ sentence }
			</p>
		</div>
	);
}

function Send( { file, sending, problem, pending, onChoose, onSend } ) {
	return (
		<Panel
			id="corex-releases-send"
			title={ __( 'Give the site a package', 'corex' ) }
		>
			<p className="corex-releases__line">
				{ __(
					'Choose the package the release was built into, a file named corex-release-….zip. It is sent in parts, and goes on from where it stopped if the connection drops.',
					'corex'
				) }
			</p>
			<div className="corex-releases__send">
				<label
					className="corex-releases__label"
					htmlFor="corex-releases-file"
				>
					{ __( 'Release package', 'corex' ) }
				</label>
				<input
					id="corex-releases-file"
					className="corex-releases__file"
					type="file"
					accept=".zip,application/zip"
					disabled={ pending !== '' }
					onChange={ ( event ) =>
						onChoose( event.target.files[ 0 ] ?? null )
					}
				/>
				<button
					type="button"
					className="button button-primary"
					disabled={ ! file || pending !== '' }
					onClick={ onSend }
					{ ...workingProps( pending === 'send' ) }
				>
					{ __( 'Send to the site', 'corex' ) }
				</button>
			</div>
			{ sending && (
				<SendProgress held={ sending.held } size={ file.size } />
			) }
			{ problem && (
				<CorexErrorState scale="action" message={ problem } />
			) }
		</Panel>
	);
}

function Packages( { packages, pending, onRead } ) {
	return (
		<Panel
			id="corex-releases-packages"
			title={ __( 'Packages on the site', 'corex' ) }
		>
			{ packages.length === 0 ? (
				<p className="corex-releases__line corex-releases__line--quiet">
					{ __( 'The site holds no package yet.', 'corex' ) }
				</p>
			) : (
				<ul className="corex-releases__packages">
					{ packages.map( ( { name, bytes } ) => (
						<li className="corex-releases__held" key={ name }>
							<span className="corex-releases__package">
								{ name }
							</span>
							<span className="corex-releases__size">
								{ sizeOf( bytes ) }
							</span>
							<button
								type="button"
								className="button"
								disabled={ pending !== '' }
								onClick={ () => onRead( name ) }
								{ ...workingProps(
									pending === `read:${ name }`
								) }
							>
								{ __( 'Read this package', 'corex' ) }
							</button>
						</li>
					) ) }
				</ul>
			) }
		</Panel>
	);
}

/**
 * @param {Object} props        Component props.
 * @param {Object} props.client The installer's client (`createReleaseClient`).
 * @return {Element} The screen.
 */
export default function ReleasesApp( { client } ) {
	const { status, overview, error, load } = useOverview( client );
	const [ pending, during ] = usePending();
	const [ file, setFile ] = useState( null );
	// The package on its way: `{ held }`, the bytes the site says it holds, null before it has
	// said. Null when nothing is being sent.
	const [ sending, setSending ] = useState( null );
	const [ sendProblem, setSendProblem ] = useState( '' );
	// What the last package read turned out to be: `{ statement }`, or `{ problem }`, the
	// error that was the answer.
	const [ outcome, setOutcome ] = useState( null );

	const read = async ( name ) => {
		setOutcome( null );
		try {
			setOutcome( { statement: await client.inspect( name ) } );
		} catch ( failure ) {
			setOutcome( { problem: failure } );
			// A package the site refuses, it removes: the list is not what it was.
			if ( failure instanceof ReleaseRefusedError ) {
				load();
			}
		}
	};

	const send = async () => {
		setSendProblem( '' );
		setSending( { held: null } );
		let name;
		try {
			name = await client.upload( file, {
				partBytes: overview.part_bytes,
				onProgress: ( held ) => setSending( { held } ),
			} );
		} catch ( failure ) {
			setSendProblem( failure.message );
			return;
		} finally {
			setSending( null );
		}

		// It is on the site now: the list says so, and the package is read without being asked.
		load();
		await read( name );
	};

	return (
		<>
			<CorexLoadable
				status={ status }
				skeleton={ <ReleasesSkeleton /> }
				loadingLabel={ __( 'Loading this site’s releases…', 'corex' ) }
				errorTitle={ __(
					'This site’s releases could not be read.',
					'corex'
				) }
				errorMessage={ error }
				onRetry={ load }
			>
				{ overview && (
					<>
						<Running
							installed={ overview.installed }
							blockers={ overview.blockers }
						/>
						<Send
							file={ file }
							sending={ sending }
							problem={ sendProblem }
							pending={ pending }
							onChoose={ setFile }
							onSend={ () => during( 'send', send ) }
						/>
						<Packages
							packages={ overview.packages }
							pending={ pending }
							onRead={ ( name ) =>
								during( `read:${ name }`, () => read( name ) )
							}
						/>
					</>
				) }
			</CorexLoadable>
			{ outcome?.statement && (
				<ReleaseStatement statement={ outcome.statement } />
			) }
			{ outcome?.problem && (
				<CorexErrorState
					scale="panel"
					title={
						outcome.problem instanceof ReleaseRefusedError
							? __(
									'The site did not take this package.',
									'corex'
								)
							: __(
									'The site could not be asked about this package.',
									'corex'
								)
					}
					message={ outcome.problem.message }
				/>
			) }
		</>
	);
}
