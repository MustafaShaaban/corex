/**
 * Approving one access request, read before it is confirmed (spec 112, FR-002 to FR-004).
 *
 * The approver sees each ability the request grants with its risk, the area it came from and the
 * requester's reason; writes a note; chooses when the grant ends; and, for a dangerous ability,
 * types its name. The button is offered only once all of that is done, and the server checks it
 * all again: a refusal is shown here, and the dialog stays open with what was entered.
 */
import { useId, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexDialog from '../admin/components/CorexDialog.js';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import { usePending, workingProps } from '../admin/components/working.js';
import { approveRequest, refusalOf } from './accessClient.js';
import { blockerReason } from './AccessChangeDialog.js';

/**
 * An ability's risk, in words.
 *
 * @param {string} risk The catalogue's risk level.
 * @return {string} The level, translated.
 */
function riskLabel( risk ) {
	return (
		{
			normal: __( 'Normal', 'corex' ),
			sensitive: __( 'Sensitive', 'corex' ),
			dangerous: __( 'Dangerous', 'corex' ),
			critical: __( 'Critical', 'corex' ),
		}[ risk ] || risk
	);
}

export default function AccessApprovalDialog( {
	config,
	preview,
	onApproved,
	onClose,
} ) {
	const id = useId();
	const [ note, setNote ] = useState( '' );
	const [ expiryDays, setExpiryDays ] = useState( preview.expiry_default );
	const [ typed, setTyped ] = useState( '' );
	const [ refusal, setRefusal ] = useState( '' );
	const [ pending, during ] = usePending();

	const ready =
		note.trim() !== '' &&
		preview.blockers.length === 0 &&
		( ! preview.requires_typed_name || typed === preview.typed_name );

	const approve = async () => {
		setRefusal( '' );
		try {
			const result = await approveRequest( config, preview, {
				note,
				expiryDays,
				typed,
			} );
			if ( result.state === 'completed' ) {
				onApproved();
				return;
			}
			setRefusal( refusalOf( result ) );
		} catch ( failure ) {
			setRefusal( failure.message );
		}
	};

	return (
		<CorexDialog
			title={ sprintf(
				/* translators: %s: the name of the person who asked for access. */
				__( 'Approve access for %s', 'corex' ),
				preview.requester.name
			) }
			onClose={ onClose }
			busy={ pending !== '' }
			footer={
				<div className="corex-access__dialog-actions">
					<button
						type="button"
						className="button"
						disabled={ pending !== '' }
						onClick={ onClose }
					>
						{ __( 'Cancel', 'corex' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						disabled={ ! ready }
						onClick={ () => during( 'approve', approve ) }
						{ ...workingProps( pending === 'approve' ) }
					>
						{ __( 'Approve access', 'corex' ) }
					</button>
				</div>
			}
		>
			{ preview.area && (
				<p>
					{ sprintf(
						/* translators: %s: the name of a CoreX area, e.g. "Forms & Flows". */
						__(
							'They asked for the %s area, which grants:',
							'corex'
						),
						preview.area_label || preview.area
					) }
				</p>
			) }
			<ul className="corex-access__changes">
				{ preview.abilities.map( ( ability ) => (
					<li key={ ability.key }>
						<strong>{ ability.label }</strong>
						<span
							className={ `corex-access__risk is-${ ability.risk }` }
						>
							{ riskLabel( ability.risk ) }
						</span>
					</li>
				) ) }
			</ul>
			<p className="corex-access__approval-reason">
				<span>{ __( 'Their reason:', 'corex' ) }</span>{ ' ' }
				{ preview.reason }
			</p>
			{ preview.blockers.length > 0 && (
				<CorexErrorState
					scale="action"
					message={ __(
						'The access safety policy refuses this approval.',
						'corex'
					) }
				>
					<ul>
						{ preview.blockers.map( ( blocker ) => (
							<li
								key={ `${ blocker.code }:${ blocker.ability }` }
							>
								{ sprintf(
									/* translators: 1: an ability's name, 2: why it cannot be granted. */
									__( '%1$s: %2$s', 'corex' ),
									preview.abilities.find(
										( ability ) =>
											ability.key === blocker.ability
									)?.label || blocker.ability,
									blockerReason( blocker.code )
								) }
							</li>
						) ) }
					</ul>
				</CorexErrorState>
			) }
			<div className="corex-access__approval-field">
				<label htmlFor={ `${ id }-note` }>
					{ __( 'Note (required): why access is approved', 'corex' ) }
				</label>
				<textarea
					id={ `${ id }-note` }
					rows={ 3 }
					value={ note }
					required
					onChange={ ( event ) => setNote( event.target.value ) }
				/>
			</div>
			<fieldset className="corex-access__approval-field">
				<legend>{ __( 'Access ends after', 'corex' ) }</legend>
				{ preview.expiry_choices.map( ( days ) => (
					<label
						key={ days }
						className="corex-access__expiry"
						htmlFor={ `${ id }-expiry-${ days }` }
					>
						<input
							id={ `${ id }-expiry-${ days }` }
							type="radio"
							name={ `${ id }-expiry` }
							value={ days }
							checked={ expiryDays === days }
							onChange={ () => setExpiryDays( days ) }
						/>
						{ sprintf(
							/* translators: %d: a number of days. */
							__( '%d days', 'corex' ),
							days
						) }
					</label>
				) ) }
			</fieldset>
			{ preview.requires_typed_name && (
				<div className="corex-access__approval-field">
					<label htmlFor={ `${ id }-typed` }>
						{ sprintf(
							/* translators: %s: a CoreX ability's key, e.g. "corex_manage_data". */
							__(
								'Type %s to approve a dangerous ability',
								'corex'
							),
							preview.typed_name
						) }
					</label>
					<input
						id={ `${ id }-typed` }
						type="text"
						dir="ltr"
						autoComplete="off"
						spellCheck={ false }
						value={ typed }
						onChange={ ( event ) => setTyped( event.target.value ) }
					/>
				</div>
			) }
			{ refusal && (
				<CorexErrorState scale="action" message={ refusal } />
			) }
		</CorexDialog>
	);
}
