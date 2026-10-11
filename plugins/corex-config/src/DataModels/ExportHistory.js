/**
 * What was exported from the Data screen before (spec 109, slice 2; FR-012 to FR-014).
 *
 * The history said a scope, a format and a count, of one model at a time, and offered a
 * download. It now lists every export once with the models it covered, how large its file is,
 * who made it and what became of the file, and a file that is still kept can be downloaded
 * again or deleted. An export that was not finished can be taken on from where it stopped.
 *
 * Its words for a file's size and fate are the Submissions export's, so the two histories read
 * alike.
 */
import { useRef, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexTime from '../admin/components/CorexTime.js';
import { usePending, workingProps } from '../admin/components/working.js';
import { formatDateTime } from '../admin/adminDateTime.js';
import {
	fileNoteOf,
	formatName,
	sizeOf,
} from '../Submissions/export/exportState.js';
import { canContinue, entryModels } from './exportSetState.js';

/**
 * A stored date as a person reads it.
 *
 * @param {string} value A stored date.
 * @return {string} The date, or what was stored when it names no date.
 */
function readableDate( value ) {
	return formatDateTime( value, 'date', String( value || '' ) ).human;
}

function PastExport( { entry, sources } ) {
	const size = sizeOf( entry.file_bytes );
	const records = Number( entry.exported_rows || entry.record_count );

	return (
		<div className="corex-export__entry-text">
			<p className="corex-export__entry-what">
				{ entryModels( entry, sources ) }
			</p>
			<p className="corex-export__entry-facts">
				<span>{ formatName( entry.format ) }</span>
				<span>
					{ sprintf(
						/* translators: %s: a number of records. */
						_n( '%s record', '%s records', records, 'corex' ),
						records.toLocaleString()
					) }
				</span>
				{ size && <span>{ size }</span> }
			</p>
			<p className="corex-export__entry-facts">
				{ entry.actor_name && <span>{ entry.actor_name }</span> }
				<CorexTime value={ entry.created_at } />
			</p>
			<p className="corex-export__entry-file" aria-live="polite">
				{ canContinue( entry )
					? __( 'Not finished.', 'corex' )
					: // The Submissions history's words, which read a file's fate from `state`.
						fileNoteOf(
							{ ...entry, state: entry.file_state },
							readableDate
						) }
			</p>
		</div>
	);
}

/**
 * @param {Object}                        props
 * @param {Array<Object>}                 props.entries  The exports, newest first.
 * @param {Array<Object>}                 props.sources  The catalog, for the models' names.
 * @param {(id: number) => Promise<void>} props.download Saves an export's file again, by id.
 * @param {(id: number) => Promise<void>} props.remove   Deletes an export's file, by id.
 * @param {(id: number) => Promise<void>} props.takeOn   Goes on writing an unfinished export, by id.
 * @return {import('react').ReactElement} The list.
 */
export default function ExportHistory( {
	entries,
	sources,
	download,
	remove,
	takeOn,
} ) {
	const [ confirming, setConfirming ] = useState( 0 );
	const [ pending, during ] = usePending();
	const list = useRef( null );
	const busy = pending !== '';

	const deleteFile = async ( id ) => {
		await remove( id );
		setConfirming( 0 );
		// The buttons that had focus are gone with the file.
		list.current?.focus();
	};

	return (
		<div
			className="corex-export__recent corex-data-models__past"
			ref={ list }
			tabIndex={ -1 }
		>
			<ul>
				{ entries.map( ( entry ) => (
					<li
						key={ entry.id }
						className={ `corex-export__entry is-${ entry.file_state }` }
					>
						<PastExport entry={ entry } sources={ sources } />
						{ canContinue( entry ) && (
							<div className="corex-export__entry-actions">
								<Button
									variant="link"
									disabled={ busy }
									onClick={ () =>
										during( `file:${ entry.id }`, () =>
											takeOn( entry.id )
										)
									}
									{ ...workingProps(
										pending === `file:${ entry.id }`
									) }
								>
									{ __( 'Continue', 'corex' ) }
								</Button>
							</div>
						) }
						{ entry.file_state === 'ready' &&
							confirming === entry.id && (
								<div
									className="corex-export__entry-actions is-confirming"
									role="group"
									aria-label={ __(
										'Delete this file?',
										'corex'
									) }
								>
									<span>
										{ __( 'Delete this file?', 'corex' ) }
									</span>
									<Button
										variant="secondary"
										isDestructive
										onClick={ () =>
											during( `file:${ entry.id }`, () =>
												deleteFile( entry.id )
											)
										}
										{ ...workingProps(
											pending === `file:${ entry.id }`
										) }
									>
										{ __( 'Delete file', 'corex' ) }
									</Button>
									{ /* The safe answer is the one focus lands on. */ }
									<Button
										variant="tertiary"
										disabled={ busy }
										onClick={ () => setConfirming( 0 ) }
										// eslint-disable-next-line jsx-a11y/no-autofocus
										autoFocus
									>
										{ __( 'Keep it', 'corex' ) }
									</Button>
								</div>
							) }
						{ entry.file_state === 'ready' &&
							confirming !== entry.id && (
								<div className="corex-export__entry-actions">
									<Button
										variant="link"
										// A second press used to fetch, and save, the file again.
										disabled={ busy }
										onClick={ () =>
											during( `file:${ entry.id }`, () =>
												download( entry.id )
											)
										}
										{ ...workingProps(
											pending === `file:${ entry.id }`
										) }
									>
										{ __( 'Download', 'corex' ) }
									</Button>
									<Button
										variant="link"
										isDestructive
										disabled={ busy }
										onClick={ () =>
											setConfirming( entry.id )
										}
									>
										{ __( 'Delete', 'corex' ) }
									</Button>
								</div>
							) }
					</li>
				) ) }
			</ul>
		</div>
	);
}
