/**
 * The parts an export dialog is made of (spec 103): what to export, which columns, the format,
 * the confirmation, what became of the export, and the one row of actions.
 *
 * They were written inside the Submissions export. The Data export is the same dialog over
 * different content, so the parts are here and each export supplies its own words: FR-048 asks
 * that somebody who has used one can use the other without learning it.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../CorexSelect.js';
import CorexSkeleton, { SkeletonBar } from '../CorexSkeleton.js';
import { workingProps } from '../working.js';

/**
 * The scopes, each with its count and what it covers.
 *
 * @param {Object}                    props
 * @param {string}                    props.fieldId    A prefix for the ids of its controls.
 * @param {Array<Object>}             props.options    `{value,label,detail,count,disabled}`, in order.
 * @param {string}                    props.scope      The chosen scope.
 * @param {Function}                  props.setScope   Chooses one.
 * @param {boolean}                   props.disabled   True while an export runs.
 * @param {import('react').ReactNode} [props.children] A choice that belongs with the scopes.
 * @return {import('react').ReactElement} The group.
 */
export function ExportScopes( {
	fieldId,
	options,
	scope,
	setScope,
	disabled,
	children,
} ) {
	return (
		<fieldset className="corex-export__group" disabled={ disabled }>
			<legend>{ __( 'What to export', 'corex' ) }</legend>
			<div className="corex-export__scopes">
				{ options.map( ( option ) => (
					<label
						key={ option.value }
						className={ [
							'corex-export__scope',
							scope === option.value ? 'is-chosen' : '',
							option.disabled ? 'is-disabled' : '',
						]
							.filter( Boolean )
							.join( ' ' ) }
						htmlFor={ `${ fieldId }-scope-${ option.value }` }
					>
						<input
							id={ `${ fieldId }-scope-${ option.value }` }
							type="radio"
							name={ `${ fieldId }-scope` }
							value={ option.value }
							checked={ scope === option.value }
							disabled={ option.disabled }
							onChange={ () => setScope( option.value ) }
							aria-describedby={ `${ fieldId }-scope-${ option.value }-detail` }
						/>
						<span className="corex-export__scope-name">
							{ option.label }
						</span>
						<span className="corex-export__count">
							{ option.count === null ? (
								<CorexSkeleton as="span">
									<SkeletonBar />
								</CorexSkeleton>
							) : (
								option.count.toLocaleString()
							) }
						</span>
						<span
							id={ `${ fieldId }-scope-${ option.value }-detail` }
							className="corex-export__detail"
						>
							{ option.detail }
						</span>
					</label>
				) ) }
			</div>
			{ children }
		</fieldset>
	);
}

/**
 * A choice with a box to tick, on a line of its own.
 *
 * @param {Object}                    props
 * @param {string}                    props.id          The checkbox's id.
 * @param {boolean}                   props.checked     Whether it is ticked.
 * @param {Function}                  props.onChange    Receives whether it is ticked now.
 * @param {boolean}                   [props.disabled]  True while it cannot be changed.
 * @param {string}                    [props.className] An extra class for the row.
 * @param {import('react').ReactNode} props.children    What ticking it means.
 * @return {import('react').ReactElement} The row.
 */
export function ExportCheck( {
	id,
	checked,
	onChange,
	disabled = false,
	className = '',
	children,
} ) {
	return (
		<label
			className={ [ 'corex-export__check', className ]
				.filter( Boolean )
				.join( ' ' ) }
			htmlFor={ id }
		>
			<span className="corex-export__box">
				<input
					id={ id }
					type="checkbox"
					checked={ checked }
					disabled={ disabled }
					onChange={ ( event ) => onChange( event.target.checked ) }
				/>
			</span>
			<span>{ children }</span>
		</label>
	);
}

/**
 * The columns to choose from. A choice's name is its label alone; what it holds, and that it is
 * personal data, describe the checkbox instead of lengthening its name.
 *
 * @param {Object}                    props
 * @param {string}                    props.fieldId    A prefix for the ids of its controls.
 * @param {Array<Object>}             props.choices    `{id,label,hint,personal}`, in order.
 * @param {string[]}                  props.chosen     The ids that are ticked.
 * @param {Function}                  props.toggle     Ticks or unticks one, by id.
 * @param {boolean}                   props.disabled   True while an export runs.
 * @param {import('react').ReactNode} [props.children] A line under the choices.
 * @return {import('react').ReactElement} The group.
 */
export function ExportColumns( {
	fieldId,
	choices,
	chosen,
	toggle,
	disabled,
	children,
} ) {
	return (
		<fieldset className="corex-export__group" disabled={ disabled }>
			<legend>{ __( 'Columns', 'corex' ) }</legend>
			<div className="corex-export__columns">
				{ choices.map( ( choice ) => (
					<div key={ choice.id } className="corex-export__column">
						<span className="corex-export__box">
							<input
								id={ `${ fieldId }-column-${ choice.id }` }
								type="checkbox"
								checked={ chosen.includes( choice.id ) }
								onChange={ () => toggle( choice.id ) }
								aria-describedby={
									[
										choice.personal
											? `${ fieldId }-column-${ choice.id }-tag`
											: '',
										choice.hint
											? `${ fieldId }-column-${ choice.id }-hint`
											: '',
									]
										.filter( Boolean )
										.join( ' ' ) || undefined
								}
							/>
						</span>
						<div>
							<span className="corex-export__column-head">
								<label
									className="corex-export__column-name"
									htmlFor={ `${ fieldId }-column-${ choice.id }` }
								>
									{ choice.label }
								</label>
								{ choice.personal && (
									<span
										id={ `${ fieldId }-column-${ choice.id }-tag` }
										className="corex-export__tag"
									>
										{ __( 'Personal data', 'corex' ) }
									</span>
								) }
							</span>
							{ choice.hint && (
								<span
									id={ `${ fieldId }-column-${ choice.id }-hint` }
									className="corex-export__detail"
								>
									{ choice.hint }
								</span>
							) }
						</div>
					</div>
				) ) }
			</div>
			{ children }
		</fieldset>
	);
}

function separators() {
	return [
		{ value: 'comma', label: __( 'Comma', 'corex' ) },
		{ value: 'semicolon', label: __( 'Semicolon', 'corex' ) },
		{ value: 'tab', label: __( 'Tab', 'corex' ) },
	];
}

/**
 * The file type, and for text the character that parts its values.
 *
 * @param {Object}        props
 * @param {string}        props.format       The chosen format.
 * @param {Function}      props.setFormat    Chooses one.
 * @param {Array<Object>} props.formats      `{value,label}` for each format on offer.
 * @param {string}        props.separator    The chosen separator.
 * @param {Function}      props.setSeparator Chooses one.
 * @param {string}        props.detail       What the chosen format is for, in a line.
 * @param {boolean}       props.disabled     True while an export runs.
 * @return {import('react').ReactElement} The group.
 */
export function ExportFormat( {
	format,
	setFormat,
	formats,
	separator,
	setSeparator,
	detail,
	disabled,
} ) {
	return (
		<fieldset className="corex-export__group" disabled={ disabled }>
			<legend>{ __( 'Format', 'corex' ) }</legend>
			<div className="corex-export__format">
				<div className="corex-field">
					<span>{ __( 'File type', 'corex' ) }</span>
					<CorexSelect
						label={ __( 'File type', 'corex' ) }
						value={ format }
						options={ formats }
						onChange={ setFormat }
					/>
				</div>
				{ /* A separator is a property of text. A workbook has none to choose. */ }
				{ format === 'csv' && (
					<div className="corex-field">
						<span>{ __( 'Separator', 'corex' ) }</span>
						<CorexSelect
							label={ __( 'Separator', 'corex' ) }
							value={ separator }
							options={ separators() }
							onChange={ setSeparator }
						/>
					</div>
				) }
			</div>
			<p className="corex-export__detail">{ detail }</p>
		</fieldset>
	);
}

/**
 * What became of the export that was started: its progress, the file that was saved, that it
 * will finish on its own, or why it stopped.
 *
 * @param {Object}   props
 * @param {Object}   props.run              `{phase, progress?, saved?, message?}`.
 * @param {Function} props.describeProgress Turns the job's progress into `{value,max,text}`.
 * @param {Function} props.onSaveAgain      Saves the file again.
 * @param {string}   props.later            What to say when the export will finish on its own.
 * @return {import('react').ReactElement|null} The outcome, or nothing before an export is started.
 */
export function ExportOutcome( { run, describeProgress, onSaveAgain, later } ) {
	if ( run.phase === 'idle' ) {
		return null;
	}

	if ( run.phase === 'running' ) {
		const progress = describeProgress( run.progress );

		return (
			<div className="corex-export__outcome" role="status">
				<progress
					className="corex-export__progress"
					value={ progress.value }
					max={ progress.max }
					aria-label={ __( 'Export progress', 'corex' ) }
				/>
				<p>{ progress.text }</p>
			</div>
		);
	}

	if ( run.phase === 'done' ) {
		return (
			<div className="corex-export__outcome is-success" role="status">
				<p>
					{ sprintf(
						/* translators: %s: the name of the file that was saved. */
						__( 'Saved %s.', 'corex' ),
						run.saved.filename
					) }
				</p>
				<Button
					variant="link"
					onClick={ () => onSaveAgain( run.saved ) }
				>
					{ __( 'Save it again', 'corex' ) }
				</Button>
			</div>
		);
	}

	if ( run.phase === 'later' ) {
		return (
			<div className="corex-export__outcome" role="status">
				<p>{ later }</p>
			</div>
		);
	}

	return (
		<div className="corex-export__outcome is-error" role="alert">
			<p>{ run.message }</p>
		</div>
	);
}

/**
 * Why the export cannot start, beside the two buttons.
 *
 * @param {Object}   props
 * @param {string}   props.blocked Why the export cannot start; '' when it can.
 * @param {Object}   props.run     `{phase}` of the export that was started, if any.
 * @param {string}   props.label   The primary action, which says how much it will export.
 * @param {Function} props.close   Stops showing the dialog.
 * @param {Function} props.start   Starts the export.
 * @return {import('react').ReactElement} The footer.
 */
export function ExportFooter( { blocked, run, label, close, start } ) {
	const running = run.phase === 'running';

	return (
		<div className="corex-export__footer">
			<p
				className="corex-export__summary"
				role="status"
				aria-live="polite"
			>
				{ blocked }
			</p>
			<div className="corex-export__actions">
				<Button variant="tertiary" onClick={ close }>
					{ run.phase === 'done' || run.phase === 'later'
						? __( 'Close', 'corex' )
						: __( 'Cancel', 'corex' ) }
				</Button>
				<Button
					variant="primary"
					disabled={ blocked !== '' }
					onClick={ start }
					{ ...workingProps( running ) }
				>
					{ label }
				</Button>
			</div>
		</div>
	);
}
