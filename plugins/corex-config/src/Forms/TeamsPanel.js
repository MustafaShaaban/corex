/**
 * The Teams view (spec 122, US-FLW-03, FR-012): the teams a submission can be routed to, made,
 * renamed, given members and removed here. A member who does not manage submissions sees what is
 * assigned to their team in their inbox (DECISIONS #473).
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexDialog from '../admin/components/CorexDialog.js';

/**
 * @param {Object}   props
 * @param {Object}   props.client   The teams requests (`teamsClient()`).
 * @param {Array}    props.people   The people who can be members: `id`, `name`.
 * @param {Function} props.onChange Called with the teams after each change, so routing offers them.
 * @return {import('react').ReactElement} The view.
 */
export function TeamsPanel( { client, people, onChange } ) {
	const [ teams, setTeams ] = useState( null );
	const [ message, setMessage ] = useState( { kind: '', text: '' } );
	const [ editing, setEditing ] = useState( null );
	const [ removing, setRemoving ] = useState( null );
	const [ working, setWorking ] = useState( false );

	const answered = useCallback(
		( envelope, done ) => {
			setWorking( false );
			if ( ! envelope.ok ) {
				setMessage( { kind: 'error', text: envelope.message } );
				return false;
			}
			setTeams( envelope.data.teams );
			onChange( envelope.data.teams );
			setMessage( { kind: 'success', text: done } );
			return true;
		},
		[ onChange ]
	);

	useEffect( () => {
		client.list().then( ( envelope ) => {
			if ( envelope.ok ) {
				setTeams( envelope.data.teams );
			} else {
				setTeams( [] );
				setMessage( { kind: 'error', text: envelope.message } );
			}
		} );
		// Once, when the view opens.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const save = async ( team ) => {
		setWorking( true );
		const envelope = team.key
			? await client.update( team.key, {
					name: team.name,
					members: team.members,
				} )
			: await client.create( { name: team.name, members: team.members } );
		if ( answered( envelope, __( 'Team saved.', 'corex' ) ) ) {
			setEditing( null );
		}
	};

	const remove = async () => {
		setWorking( true );
		const envelope = await client.remove( removing.key );
		answered( envelope, __( 'Team removed.', 'corex' ) );
		setRemoving( null );
	};

	const nameOf = ( id ) =>
		people.find( ( person ) => person.id === id )?.name ||
		__( 'A former user', 'corex' );

	return (
		<section className="corex-surface corex-flow-teams">
			<header>
				<div>
					<h2>{ __( 'Teams', 'corex' ) }</h2>
					<p>
						{ __(
							'Route submissions to a team instead of one person. Each member sees what is assigned to the team in their inbox.',
							'corex'
						) }
					</p>
				</div>
				<button
					type="button"
					className="button button-primary"
					disabled={ working }
					onClick={ () =>
						setEditing( { key: '', name: '', members: [] } )
					}
				>
					{ __( 'New team', 'corex' ) }
				</button>
			</header>
			{ message.text ? (
				<p
					className={ `corex-flows-app__notice is-${ message.kind }` }
					role={ message.kind === 'error' ? 'alert' : 'status' }
				>
					{ message.text }
				</p>
			) : null }
			{ teams === null ? (
				<p role="status">{ __( 'Loading teams…', 'corex' ) }</p>
			) : null }
			{ teams !== null && teams.length === 0 ? (
				<p>{ __( 'No teams yet.', 'corex' ) }</p>
			) : null }
			{ teams?.length ? (
				<ul className="corex-flow-teams__list">
					{ teams.map( ( team ) => (
						<li key={ team.key }>
							<div>
								<strong>{ team.name }</strong>
								<span>
									{ team.members.length === 0
										? __( 'No members', 'corex' )
										: sprintf(
												/* translators: 1: how many members a team has, 2: their names. */
												_n(
													'%1$d member: %2$s',
													'%1$d members: %2$s',
													team.members.length,
													'corex'
												),
												team.members.length,
												team.members
													.map( nameOf )
													.join( ', ' )
											) }
								</span>
							</div>
							<div className="corex-flow-editor__actions">
								<button
									type="button"
									className="button"
									disabled={ working }
									onClick={ () => setEditing( team ) }
								>
									{ __( 'Edit', 'corex' ) }
								</button>
								<button
									type="button"
									className="button"
									disabled={ working }
									onClick={ () => setRemoving( team ) }
								>
									{ __( 'Remove', 'corex' ) }
								</button>
							</div>
						</li>
					) ) }
				</ul>
			) : null }
			{ editing ? (
				<TeamDialog
					team={ editing }
					people={ people }
					working={ working }
					onSave={ save }
					onCancel={ () => setEditing( null ) }
				/>
			) : null }
			{ removing ? (
				<CorexDialog
					title={ __( 'Remove this team?', 'corex' ) }
					onClose={ () => setRemoving( null ) }
					busy={ working }
					footer={
						<div className="corex-flow-editor__actions">
							<button
								type="button"
								className="button"
								onClick={ () => setRemoving( null ) }
							>
								{ __( 'Cancel', 'corex' ) }
							</button>
							<button
								type="button"
								className="button button-primary"
								onClick={ remove }
							>
								{ __( 'Remove team', 'corex' ) }
							</button>
						</div>
					}
				>
					<p>
						{ sprintf(
							/* translators: %s: a team's name. */
							__(
								'Submissions already assigned to %s stay with it, and managers still see them. New submissions routed to it go to the flow’s fallback.',
								'corex'
							),
							removing.name
						) }
					</p>
				</CorexDialog>
			) : null }
		</section>
	);
}

function TeamDialog( { team, people, working, onSave, onCancel } ) {
	const [ name, setName ] = useState( team.name );
	const [ members, setMembers ] = useState( team.members );
	const toggle = ( id, on ) =>
		setMembers(
			on
				? [ ...members.filter( ( member ) => member !== id ), id ]
				: members.filter( ( member ) => member !== id )
		);

	return (
		<CorexDialog
			title={
				team.key
					? __( 'Edit team', 'corex' )
					: __( 'New team', 'corex' )
			}
			onClose={ onCancel }
			busy={ working }
			footer={
				<div className="corex-flow-editor__actions">
					<button
						type="button"
						className="button"
						disabled={ working }
						onClick={ onCancel }
					>
						{ __( 'Cancel', 'corex' ) }
					</button>
					<button
						type="button"
						className="button button-primary"
						disabled={ working || name.trim() === '' }
						onClick={ () =>
							onSave( { key: team.key, name, members } )
						}
					>
						{ __( 'Save team', 'corex' ) }
					</button>
				</div>
			}
		>
			<div className="corex-flow-teams__form">
				<label htmlFor="corex-team-name">
					{ __( 'Team name', 'corex' ) }
					<input
						id="corex-team-name"
						value={ name }
						maxLength={ 100 }
						onChange={ ( event ) => setName( event.target.value ) }
					/>
				</label>
				<fieldset>
					<legend>{ __( 'Members', 'corex' ) }</legend>
					{ people.map( ( person ) => (
						<label
							key={ person.id }
							htmlFor={ `corex-team-member-${ person.id }` }
							className="corex-flow-editor__check"
						>
							<input
								id={ `corex-team-member-${ person.id }` }
								type="checkbox"
								checked={ members.includes( person.id ) }
								onChange={ ( event ) =>
									toggle( person.id, event.target.checked )
								}
							/>
							{ person.name }
						</label>
					) ) }
				</fieldset>
			</div>
		</CorexDialog>
	);
}
