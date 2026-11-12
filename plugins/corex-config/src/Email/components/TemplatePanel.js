import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';
import CorexTime from '../../admin/components/CorexTime.js';
import { useContext, useState } from '@wordpress/element';
import CorexDialog from '../../admin/components/CorexDialog.js';
import {
	PendingControl,
	workingProps,
} from '../../admin/components/working.js';
import CorexSkeleton, {
	SkeletonBar,
} from '../../admin/components/CorexSkeleton.js';
import { Field, Notice } from './shared.js';

function TemplateRail( {
	templates,
	selectedId,
	selecting = null,
	busy,
	onCreate,
	onSelect,
} ) {
	const pending = useContext( PendingControl );
	return (
		<section className="corex-surface corex-email-app__rail-card">
			<h2>{ __( 'Templates', 'corex' ) }</h2>
			<form
				className="corex-email-app__compact-form"
				onSubmit={ onCreate }
			>
				<label htmlFor="corex-email-template-slug">
					{ __( 'New template slug', 'corex' ) }
					<input
						id="corex-email-template-slug"
						name="slug"
						required
						pattern="[a-z][a-z0-9\-]*"
					/>
				</label>
				<label htmlFor="corex-email-template-name">
					{ __( 'Name', 'corex' ) }
					<input
						id="corex-email-template-name"
						name="name"
						required
					/>
				</label>
				<button
					className="button button-primary"
					disabled={ busy }
					{ ...workingProps( pending === 'templates' ) }
				>
					{ __( 'Create', 'corex' ) }
				</button>
			</form>
			<ul className="corex-email-app__list">
				{ templates.map( ( template ) => (
					<li key={ template.id }>
						<button
							type="button"
							className={
								( selecting ?? selectedId ) === template.id
									? 'is-active'
									: ''
							}
							// One at a time: a second press asked again, and the slower
							// answer was the one left in the editor.
							disabled={ selecting !== null }
							onClick={ () => onSelect( template ) }
						>
							<strong>{ template.name }</strong>
							<code>{ template.slug }</code>
							<span>
								{ template.subject ||
									__( 'No draft subject', 'corex' ) }
							</span>
							{ /* "Never edited" rather than the generic "Not recorded": a template
							     with no update time has not been touched since it was created,
							     which is a fact about the template, not a gap in the record. */ }
							<CorexTime
								value={ template.updated_at }
								absent={ __( 'Never edited', 'corex' ) }
							/>
							<span>{ template.status }</span>
						</button>
					</li>
				) ) }
			</ul>
		</section>
	);
}

/**
 * The shared draft handler is event-shaped (`event.target.name` / `.value`, useEmailStudio.js),
 * and CorexSelect reports a plain value. Rather than reshape a handler five other fields depend
 * on, the two selection controls here hand it the shape it expects.
 *
 * @param {string}   name     The draft field this control writes to.
 * @param {Function} onChange The shared event-shaped draft handler.
 * @return {Function} A CorexSelect onChange that reports an event-shaped value.
 */
function asFieldEvent( name, onChange ) {
	return ( value ) => onChange( { target: { name, value } } );
}

function LayoutSelector( { layouts, draft, error, onChange } ) {
	const label = __( 'Layout revision', 'corex' );
	const options = [
		// Kept as a real option rather than a disabled one: validateDraftForm() already reports
		// "Choose a layout version." for it, so leaving it selectable keeps that the single place
		// the rule lives.
		{ value: '0:0', label: __( 'Choose a layout', 'corex' ) },
		...layouts.map( ( layout ) => ( {
			value: `${ layout.id }:${ layout.version }`,
			label: sprintf(
				/* translators: 1: Email layout name. 2: Layout version number. */
				__( '%1$s — version %2$d', 'corex' ),
				layout.name,
				layout.version
			),
		} ) ),
	];

	return (
		<div className="corex-field">
			<span id="corex-email-layout-selection-label">{ label }</span>
			<CorexSelect
				id="corex-email-layout-selection"
				label={ label }
				value={ `${ draft.layout_id }:${ draft.layout_version }` }
				options={ options }
				onChange={ asFieldEvent( 'layout_selection', onChange ) }
				describedBy={
					error ? 'corex-email-layout-selection-error' : undefined
				}
				block
			/>
			{ error && (
				<span
					id="corex-email-layout-selection-error"
					className="corex-email-app__field-error"
				>
					{ error }
				</span>
			) }
		</div>
	);
}

function DraftFields( { layouts, draft, errors, onChange } ) {
	return (
		<div className="corex-email-app__form-grid">
			<Field
				label={ __( 'Subject', 'corex' ) }
				name="subject"
				value={ draft.subject }
				error={ errors.subject }
				onChange={ onChange }
			/>
			<Field
				label={ __( 'From name (optional)', 'corex' ) }
				name="from_name"
				value={ draft.from_name }
				error={ errors.from_name }
				onChange={ onChange }
			/>
			<Field
				label={ __( 'From address (optional)', 'corex' ) }
				name="from_address"
				type="email"
				value={ draft.from_address }
				error={ errors.from_address }
				onChange={ onChange }
			/>
			<p className="corex-email-app__field-note is-wide">
				{ __(
					'Left empty, the email leaves from the sender in Settings → Mail. The variables a template uses are read from its subject and body.',
					'corex'
				) }
			</p>
			<Field
				label={ __( 'HTML body', 'corex' ) }
				name="html_body"
				value={ draft.html_body }
				error={ errors.html_body }
				onChange={ onChange }
				textarea
				wide
			/>
			<div className="corex-field">
				<span id="corex-email-plain-text-mode-label">
					{ __( 'Plain-text mode', 'corex' ) }
				</span>
				<CorexSelect
					id="corex-email-plain-text-mode"
					label={ __( 'Plain-text mode', 'corex' ) }
					value={ draft.plain_text_mode }
					options={ [
						{
							value: 'auto',
							label: __( 'Generate automatically', 'corex' ),
						},
						{ value: 'manual', label: __( 'Manual', 'corex' ) },
					] }
					onChange={ asFieldEvent( 'plain_text_mode', onChange ) }
					block
				/>
			</div>
			<Field
				label={ __( 'Plain text', 'corex' ) }
				name="plain_text"
				value={ draft.plain_text }
				error={ errors.plain_text }
				onChange={ onChange }
				textarea
				wide
			/>
			<LayoutSelector
				layouts={ layouts }
				draft={ draft }
				error={ errors.layout_id }
				onChange={ onChange }
			/>
		</div>
	);
}

const EDITOR_FIELDS = 4;

/**
 * The editor, for a template that was chosen and has not arrived: its heading, and a label
 * over a field for each of its fields.
 *
 * @return {Element} The editor's placeholder.
 */
function TemplateEditorSkeleton() {
	return (
		<CorexSkeleton>
			<header>
				<div>
					<h2>
						<SkeletonBar width="medium" />
					</h2>
					<SkeletonBar width="short" />
				</div>
			</header>
			{ Array.from( { length: EDITOR_FIELDS }, ( _, field ) => (
				<p key={ field }>
					<SkeletonBar width="short" />
					<SkeletonBar width="full" />
				</p>
			) ) }
		</CorexSkeleton>
	);
}

/**
 * Asks before a template CoreX ships is put back as it was shipped (spec 120, story 4).
 *
 * @param {Object}   props
 * @param {string}   props.name      The template's name.
 * @param {boolean}  props.busy      Whether a request is out.
 * @param {Function} props.onConfirm Resets it.
 * @param {Function} props.onClose   Leaves it as it is.
 * @return {Element} The dialog.
 */
function ResetDialog( { name, busy, onConfirm, onClose } ) {
	return (
		<CorexDialog
			title={ __( 'Reset to the shipped version?', 'corex' ) }
			onClose={ onClose }
			busy={ busy }
			footer={
				<>
					<button
						type="button"
						className="button button-primary"
						disabled={ busy }
						onClick={ onConfirm }
					>
						{ __( 'Reset to shipped version', 'corex' ) }
					</button>
					<button
						type="button"
						className="button"
						onClick={ onClose }
					>
						{ __( 'Keep my version', 'corex' ) }
					</button>
				</>
			}
		>
			<p>
				{ sprintf(
					/* translators: %s: an email template's name. */
					__(
						'%s goes back to the words CoreX ships, as a new version. Your edited versions stay in its history.',
						'corex'
					),
					name
				) }
			</p>
		</CorexDialog>
	);
}

function SaveWarnings( { warnings } ) {
	const said = Object.values( warnings || {} ).filter( Boolean );
	if ( said.length === 0 ) {
		return null;
	}

	return (
		<Notice>
			<strong>{ __( 'Saved, with something to check', 'corex' ) }</strong>
			<ul>
				{ said.map( ( warning ) => (
					<li key={ warning }>{ warning }</li>
				) ) }
			</ul>
		</Notice>
	);
}

function TemplateEditor( {
	layouts,
	detail,
	draft,
	errors,
	warnings,
	selecting = null,
	busy,
	onChange,
	onSave,
	onActivate,
	onReset,
} ) {
	const pending = useContext( PendingControl );
	const [ resetting, setResetting ] = useState( false );
	if ( selecting !== null ) {
		return <TemplateEditorSkeleton />;
	}

	if ( ! detail ) {
		return (
			<p>{ __( 'Select or create a template to edit it.', 'corex' ) }</p>
		);
	}

	return (
		<form onSubmit={ onSave }>
			<header>
				<div>
					<h2>{ detail.template.name }</h2>
					<code>{ detail.template.slug }</code>
				</div>
				<span className="corex-email-app__badge">
					{ detail.template.status }
				</span>
			</header>
			<SaveWarnings warnings={ warnings } />
			<DraftFields
				layouts={ layouts }
				draft={ draft }
				errors={ errors }
				onChange={ onChange }
			/>
			<div className="corex-email-app__actions">
				<button
					className="button button-primary"
					disabled={ busy }
					{ ...workingProps( pending === 'draft' ) }
				>
					{ __( 'Save immutable draft', 'corex' ) }
				</button>
				{ detail.template.draft_version > 0 && (
					<button
						type="button"
						className="button"
						disabled={ busy }
						onClick={ () =>
							onActivate( detail.template.draft_version )
						}
						{ ...workingProps( pending === 'activate' ) }
					>
						{ __( 'Activate latest draft', 'corex' ) }
					</button>
				) }
				{ detail.template.is_default && (
					<button
						type="button"
						className="button"
						disabled={ busy }
						onClick={ () => setResetting( true ) }
						{ ...workingProps( pending === 'reset' ) }
					>
						{ __( 'Reset to shipped version', 'corex' ) }
					</button>
				) }
			</div>
			{ resetting && (
				<ResetDialog
					name={ detail.template.name }
					busy={ busy }
					onClose={ () => setResetting( false ) }
					onConfirm={ async () => {
						await onReset();
						setResetting( false );
					} }
				/>
			) }
		</form>
	);
}

export function TemplatePanel( props ) {
	return (
		<div className="corex-email-app__split">
			<TemplateRail
				templates={ props.templates }
				selectedId={ props.detail?.template?.id }
				selecting={ props.selecting }
				busy={ props.busy }
				onCreate={ props.onCreate }
				onSelect={ props.onSelect }
			/>
			<section className="corex-surface corex-email-app__editor">
				<TemplateEditor
					layouts={ props.layouts }
					detail={ props.detail }
					draft={ props.draft }
					errors={ props.errors }
					warnings={ props.warnings }
					selecting={ props.selecting }
					busy={ props.busy }
					onChange={ props.onDraftChange }
					onSave={ props.onSaveDraft }
					onActivate={ props.onActivate }
					onReset={ props.onReset }
				/>
			</section>
		</div>
	);
}
