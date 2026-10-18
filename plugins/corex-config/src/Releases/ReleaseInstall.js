/**
 * An installation on the Releases screen, from the confirmation to how it ended (spec 107, US1
 * and US3; plan D8; FR-022, FR-035).
 *
 * It begins one of two ways. With what the site answered to `prepare`: the administrator has
 * just asked to install a package, and is shown the confirmation. Or with an installation the
 * site says is standing: somebody's browser went away from it, and this one goes on with it.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import { usePending, workingProps } from '../admin/components/working.js';
import { runInstallation } from './installRun.js';
import ReleaseConfirm from './ReleaseConfirm.js';
import {
	ArrivingHeading,
	ReleaseInstalled,
	ReleaseProgress,
} from './ReleaseRun.js';

/**
 * What is said of an installation that is standing, and what can be done with it.
 *
 * @param {Object} standing The site's account of it.
 * @return {{says: string, go: string|null, canGiveUp: boolean}} The sentence, the button that goes on with it (none when nothing here can), and whether it can be given up.
 */
function accountOf( standing ) {
	const version = standing.version;

	if ( standing.step === 'stuck' ) {
		return {
			says: sprintf(
				/* translators: %s: a CoreX version number. */
				__(
					'An installation of CoreX %s could be neither finished nor undone: a folder would not move. The site is part one release and part the other. The recovery link given when it was confirmed puts the previous release back.',
					'corex'
				),
				version
			),
			go: null,
			canGiveUp: false,
		};
	}
	if ( standing.step === 'finish' ) {
		return {
			says: standing.failed
				? sprintf(
						/* translators: 1: a CoreX version number. 2: why the database step stopped, as whole sentences. */
						__(
							'The files of CoreX %1$s are in place, and its database step stopped. %2$s',
							'corex'
						),
						version,
						standing.failed
					)
				: sprintf(
						/* translators: %s: a CoreX version number. */
						__(
							'The files of CoreX %s are in place, and the installation was not finished: its database step has not been run from here. Finishing records the release and removes what the installation left.',
							'corex'
						),
						version
					),
			go: __( 'Finish the installation', 'corex' ),
			canGiveUp: false,
		};
	}
	if ( standing.step === 'swap' && standing.moved_nothing ) {
		return {
			says: sprintf(
				/* translators: %s: a CoreX version number. */
				__(
					'An installation of CoreX %s stopped as the release was about to be put in place, before any folder moved. Visitors may be held off until it is gone on with or given up. Giving it up lets them in, and the site stays the release it is.',
					'corex'
				),
				version
			),
			go: __( 'Go on with it', 'corex' ),
			canGiveUp: true,
		};
	}
	if ( standing.step === 'swap' ) {
		return {
			says: sprintf(
				/* translators: %s: a CoreX version number. */
				__(
					'An installation of CoreX %s stopped as the release was being put in place. Going on finishes it, or puts every folder back, and says which.',
					'corex'
				),
				version
			),
			go: __( 'Go on with it', 'corex' ),
			canGiveUp: false,
		};
	}

	return {
		says: sprintf(
			/* translators: %s: a CoreX version number. */
			__(
				'An installation of CoreX %s was started and has not finished. Nothing of the site has changed yet.',
				'corex'
			),
			version
		),
		go: __( 'Go on with it', 'corex' ),
		canGiveUp: true,
	};
}

function Standing( { standing, pending, onGo, onGiveUp } ) {
	const { says, go, canGiveUp } = accountOf( standing );

	return (
		<section
			className="corex-releases__panel corex-releases__standing"
			aria-labelledby="corex-releases-standing"
		>
			<h2
				id="corex-releases-standing"
				className="corex-releases__heading"
			>
				{ __( 'An installation is in progress', 'corex' ) }
			</h2>
			<p className="corex-releases__line">{ says }</p>
			<div className="corex-releases__actions">
				{ go && (
					<button
						type="button"
						className="button button-primary"
						disabled={ pending !== '' }
						onClick={ onGo }
					>
						{ go }
					</button>
				) }
				{ canGiveUp && (
					<button
						type="button"
						className="button"
						disabled={ pending !== '' }
						onClick={ onGiveUp }
						{ ...workingProps( pending === 'give-up' ) }
					>
						{ __( 'Give it up', 'corex' ) }
					</button>
				) }
			</div>
		</section>
	);
}

function Stopped( { failure, recoveryLink, onClose } ) {
	return (
		<section
			className="corex-releases__panel corex-releases__stopped"
			aria-labelledby="corex-releases-stopped"
		>
			<ArrivingHeading id="corex-releases-stopped">
				{ failure.filesInPlace
					? __( 'The installation did not finish', 'corex' )
					: __( 'The release was not installed', 'corex' ) }
			</ArrivingHeading>
			<CorexErrorState scale="panel" message={ failure.message } />
			{ failure.filesInPlace ? (
				<>
					<p className="corex-releases__line">
						{ __(
							'The files of the new release may be in place. Open Releases again to see where the installation stands and to finish it.',
							'corex'
						) }
					</p>
					{ recoveryLink && (
						<p className="corex-releases__line">
							{ __(
								'If the site does not load, this puts the previous release back:',
								'corex'
							) }{ ' ' }
							<a href={ recoveryLink } dir="ltr">
								{ recoveryLink }
							</a>
						</p>
					) }
				</>
			) : (
				<p className="corex-releases__line">
					{ __( 'The site is the release it was.', 'corex' ) }
				</p>
			) }
			<div className="corex-releases__actions">
				<button type="button" className="button" onClick={ onClose }>
					{ __( 'Back to Releases', 'corex' ) }
				</button>
			</div>
		</section>
	);
}

/**
 * @param {Object}      props          Component props.
 * @param {Object}      props.client   The installer's client.
 * @param {Object|null} props.prepared The site's answer to `prepare`, for an installation just asked for.
 * @param {Object|null} props.standing The site's account of one already standing, when `prepared` is null.
 * @param {() => void}  props.onClose  Called when it is over, or given up: the screen asks the site what is there now.
 * @return {Element} The panel for where the installation is.
 */
export default function ReleaseInstall( {
	client,
	prepared,
	standing,
	onClose,
} ) {
	const [ pending, during ] = usePending();
	const [ stage, setStage ] = useState( prepared ? 'confirm' : 'standing' );
	const [ stand, setStand ] = useState( standing );
	const [ refusal, setRefusal ] = useState( '' );
	const [ ended, setEnded ] = useState( null );
	const version = prepared?.version ?? standing?.version;

	const run = async ( from ) => {
		setStage( 'running' );
		try {
			setEnded( {
				outcome: await runInstallation( client, {
					from,
					onStand: setStand,
					// Until the next answer: a stand the site gives says nothing of waiting.
					onWait: () =>
						setStand( ( last ) => ( { ...last, waiting: true } ) ),
				} ),
			} );
		} catch ( failure ) {
			setEnded( { failure } );
		}
		setStage( 'ended' );
	};

	const confirm = async ( typed, more ) => {
		setRefusal( '' );
		try {
			setStand( await client.confirm( typed, more ) );
		} catch ( failure ) {
			setRefusal( failure.message );
			return;
		}
		await run( 'unpack' );
	};

	const giveUp = async () => {
		try {
			await client.cancel();
		} catch {
			// One that is already gone is given up. The screen asks the site what is there.
		}
		onClose();
	};

	if ( stage === 'confirm' ) {
		return (
			<ReleaseConfirm
				prepared={ prepared }
				working={ pending === 'confirm' }
				problem={ refusal }
				onConfirm={ ( typed, more ) =>
					during( 'confirm', () => confirm( typed, more ) )
				}
				onCancel={ giveUp }
			/>
		);
	}
	if ( stage === 'standing' ) {
		return (
			<Standing
				standing={ standing }
				pending={ pending }
				onGo={ () => run( standing.step ) }
				onGiveUp={ () => during( 'give-up', giveUp ) }
			/>
		);
	}
	if ( stage === 'running' ) {
		return <ReleaseProgress stand={ stand } version={ version } />;
	}

	return ended.outcome ? (
		<ReleaseInstalled outcome={ ended.outcome } onClose={ onClose } />
	) : (
		<Stopped
			failure={ ended.failure }
			recoveryLink={ prepared?.recovery_link }
			onClose={ onClose }
		/>
	);
}
