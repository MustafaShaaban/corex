/**
 * Moving a post through review (spec 075, FR-2).
 *
 * The panel this replaces printed two raw slugs — `ready_for_review`, `publish` — and offered nothing.
 * `POST /blog/editorial/{id}/transition` has existed the whole time with no caller in the product, and
 * `buildTransitionPayload` was shaping a request nobody sent.
 *
 * The states offered come from the server, which is worth stating because it is not what "editorial
 * workflow" implies: {@see EditorialWorkflowService} has **no transition graph**. It accepts any state
 * from any other, so the panel offers every state but the current one rather than inventing a rule the
 * service does not enforce. Its one genuine constraint — `scheduled` needs a timestamp, or the request
 * throws — arrives as `requires_schedule`, so the field is required here instead of failing at the
 * server with a message nobody can act on.
 */
import { useId, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../admin/components/CorexSelect.js';
import { buildTransitionPayload } from './blogProState.js';

export default function EditorialPanel( {
	editorial,
	busy = false,
	onTransition,
} ) {
	const [ target, setTarget ] = useState( '' );
	const [ note, setNote ] = useState( '' );
	const [ scheduledAt, setScheduledAt ] = useState( '' );
	const [ dueAt, setDueAt ] = useState( '' );
	// Each control is named by its own `for`/`id` pair rather than by being wrapped: a
	// wrapping <label> is not announced by every assistive technology WordPress supports.
	const fieldId = useId();

	if ( ! editorial ) {
		return (
			<p className="corex-blog-pro__empty">
				{ __( 'This post has no editorial record yet.', 'corex' ) }
			</p>
		);
	}

	const transitions = Array.isArray( editorial.transitions )
		? editorial.transitions
		: [];
	const chosen =
		transitions.find( ( option ) => option.key === target ) || null;
	const needsSchedule = Boolean( chosen?.requires_schedule );
	const ready = target !== '' && ( ! needsSchedule || scheduledAt !== '' );

	const submit = ( event ) => {
		event.preventDefault();
		if ( ! ready ) {
			return;
		}

		onTransition(
			buildTransitionPayload( {
				state: target,
				note,
				scheduledAt,
				dueAt,
			} )
		);
	};

	return (
		<div className="corex-blog-pro__editorial">
			<dl className="corex-blog-pro__facts">
				<dt>{ __( 'CoreX state', 'corex' ) }</dt>
				<dd>{ editorial.editorial_state_label }</dd>
				{ /* The workflow service maps CoreX states onto native ones, so a divergence —
				     approved here, still a draft in WordPress — is worth being able to see. */ }
				<dt>{ __( 'WordPress status', 'corex' ) }</dt>
				<dd>{ editorial.native_status_label }</dd>
			</dl>

			{ /* Hidden rather than shown and refused, from the same capability the route enforces
			     (DECISIONS #159). */ }
			{ ! editorial.can_transition ? (
				<p className="corex-blog-pro__empty">
					{ __(
						'You do not have permission to move this post.',
						'corex'
					) }
				</p>
			) : (
				<form className="corex-blog-pro__form" onSubmit={ submit }>
					<CorexSelect
						label={ __( 'Move to', 'corex' ) }
						value={ target }
						options={ [
							{
								value: '',
								label: __( 'Choose a state…', 'corex' ),
							},
							...transitions.map( ( option ) => ( {
								value: option.key,
								label: option.label,
							} ) ),
						] }
						onChange={ setTarget }
					/>

					{ needsSchedule ? (
						<label
							className="corex-blog-pro__field"
							htmlFor={ `${ fieldId }-scheduled-at` }
						>
							<span>{ __( 'Publish at', 'corex' ) }</span>
							<input
								id={ `${ fieldId }-scheduled-at` }
								type="datetime-local"
								required
								value={ scheduledAt }
								onChange={ ( event ) =>
									setScheduledAt( event.target.value )
								}
							/>
							<small>
								{ sprintf(
									/* translators: %s: the chosen state, e.g. Scheduled. */
									__(
										'%s needs a date before CoreX can apply it.',
										'corex'
									),
									chosen.label
								) }
							</small>
						</label>
					) : null }

					<label
						className="corex-blog-pro__field"
						htmlFor={ `${ fieldId }-due-at` }
					>
						<span>{ __( 'Due by (optional)', 'corex' ) }</span>
						<input
							id={ `${ fieldId }-due-at` }
							type="date"
							value={ dueAt }
							onChange={ ( event ) =>
								setDueAt( event.target.value )
							}
						/>
					</label>

					<label
						className="corex-blog-pro__field"
						htmlFor={ `${ fieldId }-note` }
					>
						<span>{ __( 'Note (optional)', 'corex' ) }</span>
						<textarea
							id={ `${ fieldId }-note` }
							rows="2"
							value={ note }
							onChange={ ( event ) =>
								setNote( event.target.value )
							}
							placeholder={ __(
								'What still needs doing?',
								'corex'
							) }
						/>
					</label>

					<button
						type="submit"
						className="button button-primary"
						data-corex-blog-transition
						disabled={ ! ready || busy }
					>
						{ busy
							? __( 'Applying…', 'corex' )
							: __( 'Apply', 'corex' ) }
					</button>
				</form>
			) }
		</div>
	);
}
