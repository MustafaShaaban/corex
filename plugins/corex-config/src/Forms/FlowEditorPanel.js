import { useContext, useEffect, useRef, useState } from '@wordpress/element';
import { PendingControl, workingProps } from '../admin/components/working.js';
import { __ } from '@wordpress/i18n';
import {
	addField,
	isDirty,
	moveField,
	publishState,
	removeField,
	stageStatus,
	updateConfiguration,
	updateField,
} from './flowEditor.js';
import { StageRail } from './StageRail.js';
import { FlowStateChip, LiveLine } from './FlowState.js';
import { FormTab } from './tabs/FormTab.js';
import { ValidationTab } from './tabs/ValidationTab.js';
import { RoutingTab } from './tabs/RoutingTab.js';
import { EmailsTab } from './tabs/EmailsTab.js';
import { ProtectionTab } from './tabs/ProtectionTab.js';
import { SuccessTab } from './tabs/SuccessTab.js';
import { AvailabilityTab } from './tabs/AvailabilityTab.js';
import { PreviewTab } from './tabs/PreviewTab.js';
import { TestTab } from './tabs/TestTab.js';
import { VersionsTab } from './tabs/VersionsTab.js';
import { UnsavedChangesDialog } from './components/UnsavedChangesDialog.js';
import { UnpublishDialog } from './components/UnpublishDialog.js';

/**
 * The browser's own "Leave site?" warning, while there is something to lose (FR-024).
 *
 * @param {boolean} dirty Whether the flow on screen has unsaved changes.
 */
function useLeaveWarning( dirty ) {
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			// For browsers that show the warning only when this is set.
			event.returnValue = true;
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );
}

/**
 * Commands that would lose the unsaved changes, or act on a version without them, asked about
 * first (FR-023, FR-025).
 *
 * `guard( run, allowDiscard )` gives the click handler for a control: with nothing unsaved it
 * runs `run` at once, and otherwise it asks. Save saves and runs `run` only when the save
 * succeeded; a failed save leaves the edits on screen, and the screen's notice says why.
 *
 * @param {boolean}                    dirty     Whether the flow on screen has unsaved changes.
 * @param {() => Promise<Object|null>} saveDraft Saves the draft; resolves to the saved draft, or null.
 * @return {Object} `{ guard, dialog }`: the handler maker, and the question when one is open.
 */
function useUnsavedGuard( dirty, saveDraft ) {
	const [ question, setQuestion ] = useState( null );
	// The control that asked, for the focus to go back to when the question closes.
	const opener = useRef( null );
	const pending = useContext( PendingControl );

	useEffect( () => {
		if ( question || ! opener.current ) {
			return;
		}
		if ( opener.current.isConnected ) {
			opener.current.focus();
		}
		opener.current = null;
	}, [ question ] );

	const guard =
		( run, allowDiscard = false ) =>
		( event ) => {
			if ( ! dirty ) {
				run( event );
				return;
			}
			opener.current = event?.currentTarget || null;
			setQuestion( { run, allowDiscard } );
		};

	const answer = ( save ) => async () => {
		const { run } = question;
		const saved = save ? await saveDraft() : true;
		setQuestion( null );
		if ( saved ) {
			run();
		}
	};

	const dialog = question ? (
		<UnsavedChangesDialog
			allowDiscard={ question.allowDiscard }
			saving={ pending === 'saveDraft' }
			onSave={ answer( true ) }
			onDiscard={ answer( false ) }
			onCancel={ () => setQuestion( null ) }
		/>
	) : null;

	return { guard, dialog };
}

/**
 * "Unpublish", asked first: the form leaves every page that shows it (spec 122, FR-006).
 *
 * @param {Function} unpublish Unpublishes the flow.
 * @return {Object} `{ ask, dialog }`.
 */
function useUnpublishQuestion( unpublish ) {
	const [ open, setOpen ] = useState( false );
	const opener = useRef( null );
	const pending = useContext( PendingControl );
	const close = () => {
		setOpen( false );
		if ( opener.current?.isConnected ) {
			opener.current.focus();
		}
	};

	return {
		ask: ( event ) => {
			opener.current = event?.currentTarget || null;
			setOpen( true );
		},
		dialog: open ? (
			<UnpublishDialog
				working={ pending === 'unpublish' }
				onConfirm={ async () => {
					await unpublish();
					setOpen( false );
				} }
				onCancel={ close }
			/>
		) : null,
	};
}

export function FlowEditorPanel( { studio, timezone = '', onBack } ) {
	const [ stage, setStage ] = useState( 'form' );
	const [ availabilityDirty, setAvailabilityDirty ] = useState( false );
	const { state } = studio;
	const { draft, extensions } = state;
	const busy = state.status === 'loading' || state.status === 'mutating';
	const pending = useContext( PendingControl );
	const dirty = isDirty( state );
	const { guard, dialog } = useUnsavedGuard( dirty, studio.saveDraft );
	const unpublishQuestion = useUnpublishQuestion( studio.unpublish );
	const publishing = publishState( draft );
	useLeaveWarning( dirty || availabilityDirty );
	const changeDraft = ( next ) =>
		studio.dispatch( { type: 'draft_changed', payload: next } );
	const changeConfiguration = ( key, value ) =>
		changeDraft( updateConfiguration( draft, key, value ) );
	const add = ( type ) => {
		const next = addField( draft, type );
		changeDraft( next );
		return next.configuration.schema.at( -1 ).uuid;
	};

	return (
		<div className="corex-flow-editor">
			<header className="corex-surface corex-flow-editor__toolbar">
				<div className="corex-flow-editor__identity">
					<button
						type="button"
						className="button-link"
						onClick={ guard( onBack, true ) }
					>
						{ __( 'Back to flows', 'corex' ) }
					</button>
					<p className="corex-flow-editor__eyebrow">
						{ __( 'Editing flow', 'corex' ) }
					</p>
					<div className="corex-flow-editor__title">
						<h2>{ draft.flow.name }</h2>
						<FlowStateChip flow={ draft.flow } />
					</div>
					<code>{ draft.flow.slug }</code>
					<LiveLine flow={ draft.flow } />
					<p className="description" role="status">
						{ dirty
							? __( 'Unsaved changes', 'corex' )
							: __( 'All changes saved', 'corex' ) }
					</p>
				</div>
				<div className="corex-flow-editor__actions">
					<button
						type="button"
						className="button"
						disabled={ busy }
						onClick={ studio.saveDraft }
						{ ...workingProps( pending === 'saveDraft' ) }
					>
						{ __( 'Save draft', 'corex' ) }
					</button>
					{ publishing === 'publish' || publishing === 'changes' ? (
						<button
							type="button"
							className="button button-primary"
							disabled={ busy }
							onClick={ guard( studio.publish ) }
							{ ...workingProps( pending === 'publish' ) }
						>
							{ publishing === 'publish'
								? __( 'Publish', 'corex' )
								: __( 'Publish changes', 'corex' ) }
						</button>
					) : null }
					{ publishing === 'current' && ! dirty ? (
						<span className="corex-flow-editor__up-to-date">
							{ __( 'Live version is up to date', 'corex' ) }
						</span>
					) : null }
					{ draft.flow.state !== 'draft' ? (
						<button
							type="button"
							className="button"
							disabled={ busy }
							onClick={ guard( unpublishQuestion.ask ) }
							{ ...workingProps( pending === 'unpublish' ) }
						>
							{ __( 'Unpublish', 'corex' ) }
						</button>
					) : null }
					{ draft.flow.state === 'published' ? (
						<button
							type="button"
							className="button"
							disabled={ busy }
							onClick={ guard( studio.close ) }
							{ ...workingProps( pending === 'close' ) }
						>
							{ __( 'Close', 'corex' ) }
						</button>
					) : null }
					<button
						type="button"
						className="button"
						onClick={ () => setStage( 'preview' ) }
					>
						{ __( 'Preview', 'corex' ) }
					</button>
				</div>
			</header>
			<StageRail
				active={ stage }
				statuses={ stageStatus( draft ) }
				onChange={ setStage }
			/>
			<div className="corex-surface corex-flow-editor__workspace">
				{ stage === 'form' ? (
					<FormTab
						draft={ draft }
						fieldTypes={ extensions.field_types || [] }
						busy={ busy }
						onAdd={ add }
						onUpdate={ ( uuid, changes ) =>
							changeDraft( updateField( draft, uuid, changes ) )
						}
						onMove={ ( uuid, direction ) =>
							changeDraft( moveField( draft, uuid, direction ) )
						}
						onRemove={ ( uuid ) =>
							changeDraft( removeField( draft, uuid ) )
						}
					/>
				) : null }
				{ stage === 'validation' ? (
					<ValidationTab
						draft={ draft }
						rules={ extensions.validation_rules || [] }
						onChange={ ( value ) =>
							changeConfiguration( 'validation', value )
						}
					/>
				) : null }
				{ stage === 'routing' ? (
					<RoutingTab
						routing={ draft.configuration.routing }
						schema={ draft.configuration.schema || [] }
						catalog={ extensions }
						onChange={ ( value ) =>
							changeConfiguration( 'routing', value )
						}
					/>
				) : null }
				{ stage === 'emails' ? (
					<EmailsTab
						routes={ draft.configuration.email_routes || [] }
						routing={ draft.configuration.routing || {} }
						schema={ draft.configuration.schema || [] }
						templates={ extensions.email_templates || [] }
						onPreview={ studio.previewRouting }
						onChange={ ( value ) =>
							changeConfiguration( 'email_routes', value )
						}
						onRoutingChange={ ( value ) =>
							changeConfiguration( 'routing', value )
						}
					/>
				) : null }
				{ stage === 'protection' ? (
					<ProtectionTab
						protection={ draft.configuration.protection || {} }
						onChange={ ( value ) =>
							changeConfiguration( 'protection', value )
						}
					/>
				) : null }
				{ stage === 'success' ? (
					<SuccessTab
						success={ draft.configuration.success || {} }
						stateTypes={ extensions.success_states || [] }
						actions={ draft.configuration.actions || [] }
						registeredActions={ extensions.flow_actions || [] }
						onChange={ ( value ) =>
							changeConfiguration( 'success', value )
						}
						onActionsChange={ ( value ) =>
							changeConfiguration( 'actions', value )
						}
					/>
				) : null }
				{ stage === 'availability' ? (
					<AvailabilityTab
						flow={ draft.flow }
						timezone={ timezone }
						busy={ busy }
						saving={ pending === 'availability' }
						onDirtyChange={ setAvailabilityDirty }
						onSave={ studio.saveAvailability }
					/>
				) : null }
				{ stage === 'preview' ? <PreviewTab draft={ draft } /> : null }
				{ stage === 'test' ? (
					<TestTab
						busy={ busy }
						result={ state.testResult }
						version={ draft.version.version_number }
						onRun={ guard( studio.test ) }
					/>
				) : null }
				{ stage === 'versions' ? (
					<VersionsTab
						draft={ draft }
						busy={ busy }
						pending={ pending }
						onCopy={ ( versionNumber ) =>
							guard( () => studio.copyVersion( versionNumber ) )
						}
					/>
				) : null }
			</div>
			{ dialog }
			{ unpublishQuestion.dialog }
		</div>
	);
}
