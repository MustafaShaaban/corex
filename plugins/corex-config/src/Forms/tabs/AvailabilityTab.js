/**
 * When the flow takes answers, and what a visitor reads when it does not (spec 122, US-VIS-01,
 * US-VIS-02).
 *
 * It belongs to the flow and not to a version, so it has a Save of its own and takes effect at
 * once, without publishing: clearing a date opens an expired flow again (DECISIONS #477).
 */
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';
import { workingProps } from '../../admin/components/working.js';

/**
 * The form's values from the flow's stored availability.
 *
 * @param {Object} availability The flow's `availability`, as the REST client gives it.
 * @return {Object} The values the fields hold.
 */
export function availabilityForm( availability = {} ) {
	const alternative = availability.alternative || {};

	return {
		opens_at: availability.opens_at_local || '',
		closes_at: availability.closes_at_local || '',
		max_responses: availability.max_responses
			? String( availability.max_responses )
			: '',
		closed_message: availability.closed_message || '',
		alternative: {
			type: alternative.type || 'none',
			label: alternative.label || '',
			url: alternative.url || '',
			email: alternative.email || '',
		},
	};
}

/**
 * What is sent: a cap as a number, or nothing.
 *
 * @param {Object} form The fields' values.
 * @return {Object} The request's `availability`.
 */
export function availabilityPayload( form ) {
	const cap = parseInt( form.max_responses, 10 );

	return { ...form, max_responses: cap > 0 ? cap : null };
}

export function AvailabilityTab( {
	flow,
	timezone,
	busy,
	saving,
	onDirtyChange,
	onSave,
} ) {
	const saved = useMemo(
		() => availabilityForm( flow.availability ),
		[ flow.availability ]
	);
	const [ form, setForm ] = useState( saved );
	const dirty = JSON.stringify( form ) !== JSON.stringify( saved );

	// A save, or another copy of the flow read again, starts the form over from what is stored.
	useEffect( () => setForm( saved ), [ saved ] );
	useEffect( () => {
		onDirtyChange( dirty );
		return () => onDirtyChange( false );
	}, [ dirty, onDirtyChange ] );

	const set = ( key, value ) => setForm( { ...form, [ key ]: value } );
	const setAlternative = ( key, value ) =>
		setForm( {
			...form,
			alternative: { ...form.alternative, [ key ]: value },
		} );
	const submit = ( event ) => {
		event.preventDefault();
		onSave( availabilityPayload( form ) );
	};

	return (
		<form
			className="corex-flow-editor__panel corex-flow-availability"
			onSubmit={ submit }
		>
			<header>
				<div>
					<h2>{ __( 'Availability', 'corex' ) }</h2>
					<p>
						{ __(
							'When the form takes answers, and what visitors read when it does not. Saved here, it applies at once, without publishing.',
							'corex'
						) }
					</p>
				</div>
			</header>
			<fieldset>
				<legend>{ __( 'Schedule', 'corex' ) }</legend>
				<p className="corex-flow-editor__note">
					{ timezone
						? sprintf(
								/* translators: %s: the site's time zone, such as Africa/Cairo. */
								__(
									'Each is optional. Dates and times are in the site’s time zone, %s.',
									'corex'
								),
								timezone
							)
						: __(
								'Each is optional. Dates and times are in the site’s time zone.',
								'corex'
							) }
				</p>
				<label htmlFor="corex-flow-opens-at">
					{ __( 'Opens on', 'corex' ) }
					<input
						id="corex-flow-opens-at"
						type="datetime-local"
						value={ form.opens_at }
						onChange={ ( event ) =>
							set( 'opens_at', event.target.value )
						}
					/>
				</label>
				<label htmlFor="corex-flow-closes-at">
					{ __( 'Closes on', 'corex' ) }
					<input
						id="corex-flow-closes-at"
						type="datetime-local"
						value={ form.closes_at }
						onChange={ ( event ) =>
							set( 'closes_at', event.target.value )
						}
					/>
				</label>
				<label htmlFor="corex-flow-max-responses">
					{ __( 'Stop after this many responses', 'corex' ) }
					<input
						id="corex-flow-max-responses"
						type="number"
						min="1"
						inputMode="numeric"
						value={ form.max_responses }
						aria-describedby="corex-flow-max-responses-help"
						onChange={ ( event ) =>
							set( 'max_responses', event.target.value )
						}
					/>
				</label>
				<p
					id="corex-flow-max-responses-help"
					className="corex-flow-editor__note"
				>
					{ __(
						'Marked tests and responses in the trash do not count.',
						'corex'
					) }
				</p>
			</fieldset>
			<fieldset>
				<legend>{ __( 'When the form is closed', 'corex' ) }</legend>
				<label htmlFor="corex-flow-closed-message">
					{ __( 'Message', 'corex' ) }
					<textarea
						id="corex-flow-closed-message"
						maxLength={ 500 }
						placeholder={ __( 'This form is closed.', 'corex' ) }
						value={ form.closed_message }
						onChange={ ( event ) =>
							set( 'closed_message', event.target.value )
						}
					/>
				</label>
				<div className="corex-field">
					<span>
						{ __( 'Another way to get in touch', 'corex' ) }
					</span>
					<CorexSelect
						id="corex-flow-alternative-type"
						label={ __( 'Another way to get in touch', 'corex' ) }
						value={ form.alternative.type }
						options={ [
							{ value: 'none', label: __( 'Nothing', 'corex' ) },
							{ value: 'link', label: __( 'A link', 'corex' ) },
							{
								value: 'email',
								label: __( 'An email address', 'corex' ),
							},
						] }
						onChange={ ( type ) => setAlternative( 'type', type ) }
						block
					/>
				</div>
				{ form.alternative.type !== 'none' ? (
					<label htmlFor="corex-flow-alternative-label">
						{ form.alternative.type === 'link'
							? __( 'Link text', 'corex' )
							: __( 'Link text (optional)', 'corex' ) }
						<input
							id="corex-flow-alternative-label"
							value={ form.alternative.label }
							onChange={ ( event ) =>
								setAlternative( 'label', event.target.value )
							}
						/>
					</label>
				) : null }
				{ form.alternative.type === 'link' ? (
					<label htmlFor="corex-flow-alternative-url">
						{ __( 'Web address', 'corex' ) }
						<input
							id="corex-flow-alternative-url"
							type="url"
							placeholder="https://"
							value={ form.alternative.url }
							onChange={ ( event ) =>
								setAlternative( 'url', event.target.value )
							}
						/>
					</label>
				) : null }
				{ form.alternative.type === 'email' ? (
					<label htmlFor="corex-flow-alternative-email">
						{ __( 'Email address', 'corex' ) }
						<input
							id="corex-flow-alternative-email"
							type="email"
							value={ form.alternative.email }
							onChange={ ( event ) =>
								setAlternative( 'email', event.target.value )
							}
						/>
					</label>
				) : null }
			</fieldset>
			<div className="corex-flow-editor__actions">
				<button
					type="submit"
					className="button button-primary"
					disabled={ busy || ! dirty }
					{ ...workingProps( saving ) }
				>
					{ __( 'Save availability', 'corex' ) }
				</button>
				<span role="status" className="corex-flow-editor__note">
					{ dirty
						? __( 'Availability not saved yet', 'corex' )
						: __( 'Availability saved', 'corex' ) }
				</span>
			</div>
		</form>
	);
}
