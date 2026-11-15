import { __ } from '@wordpress/i18n';
import { generateUuid } from '../uuid.js';
import CorexSelect from '../../admin/components/CorexSelect.js';

const CONDITION_OPERATORS = [
	{ value: 'equals', label: __( 'Equals', 'corex' ) },
	{ value: 'not_equals', label: __( 'Does not equal', 'corex' ) },
	{ value: 'contains', label: __( 'Contains', 'corex' ) },
	{ value: 'exists', label: __( 'Exists', 'corex' ) },
	{ value: 'empty', label: __( 'Is empty', 'corex' ) },
];

/** The owners a value-to-owner row can name. */
const OWNER_TYPES = [
	{ value: 'user', label: __( 'User', 'corex' ) },
	{ value: 'team', label: __( 'Team', 'corex' ) },
	{ value: 'role', label: __( 'Role', 'corex' ) },
];

/**
 * The target picker's choice for a stored target: its type, or `extension:<key>` for a routing
 * extension, so two extensions are two choices.
 *
 * @param {Object} target A stored target.
 * @return {string} The choice.
 */
export function targetChoice( target ) {
	return target?.type === 'extension'
		? `extension:${ target.config?.value || '' }`
		: target?.type || '';
}

/**
 * The stored target a choice makes. Extension targets carry their key as the value.
 *
 * @param {string} choice A choice from `targetChoice()`.
 * @return {Object} The target, with an empty config for the owner to fill in.
 */
export function targetFromChoice( choice ) {
	if ( choice.startsWith( 'extension:' ) ) {
		return {
			type: 'extension',
			config: { value: choice.slice( 'extension:'.length ) },
		};
	}
	return { type: choice, config: {} };
}

/**
 * Every target the editor offers, by name (spec 122, FR-016). A stored target the editor no longer
 * offers (department, email, post author) stays visible, so the owner sees what to replace.
 *
 * @param {Array}  targetTypes The catalog's `routing_targets`.
 * @param {Object} current     The target being edited.
 * @return {Array} The picker's options.
 */
export function targetOptions( targetTypes, current ) {
	const options = targetTypes.map( ( target ) => ( {
		value: target.extension
			? `extension:${ target.extension }`
			: target.key,
		label: target.label,
	} ) );
	const chosen = targetChoice( current );
	if ( chosen && ! options.some( ( option ) => option.value === chosen ) ) {
		options.push( {
			value: chosen,
			label: __( 'No longer offered: choose another', 'corex' ),
		} );
	}
	return options;
}

export function RoutingTab( { routing, schema, catalog, onChange } ) {
	const rules = Array.isArray( routing.rules ) ? routing.rules : [];
	const updateRule = ( index, changes ) => {
		onChange( {
			...routing,
			rules: rules.map( ( rule, position ) =>
				position === index ? { ...rule, ...changes } : rule
			),
		} );
	};
	const addRule = () =>
		onChange( {
			...routing,
			rules: [
				...rules,
				{
					uuid: generateUuid(),
					position: rules.length * 10 + 10,
					condition: { field: '', operator: 'equals', value: '' },
					target: { type: 'flow_owner', config: {} },
					enabled: true,
				},
			],
		} );

	return (
		<section className="corex-flow-editor__panel">
			<header>
				<div>
					<h2>{ __( 'Who handles a submission', 'corex' ) }</h2>
					<p>
						{ __(
							'Route each submission to a responsible person or team. Rules are evaluated top-down; the first match wins. When a rule’s target names nobody, the fallback takes it, then the flow’s owner.',
							'corex'
						) }
					</p>
				</div>
				<button type="button" className="button" onClick={ addRule }>
					{ __( 'Add rule', 'corex' ) }
				</button>
			</header>
			<ol className="corex-flow-editor__rules">
				{ rules.map( ( rule, index ) => (
					<li key={ rule.uuid }>
						<strong>{ `${ index + 1 }.` }</strong>
						<input
							aria-label={ __( 'Condition field key', 'corex' ) }
							value={ rule.condition?.field || '' }
							onChange={ ( event ) =>
								updateRule( index, {
									condition: {
										...rule.condition,
										field: event.target.value,
									},
								} )
							}
						/>
						<CorexSelect
							label={ __( 'Condition operator', 'corex' ) }
							value={ rule.condition?.operator || 'equals' }
							options={ CONDITION_OPERATORS }
							onChange={ ( operator ) =>
								updateRule( index, {
									condition: { ...rule.condition, operator },
								} )
							}
						/>
						<input
							aria-label={ __( 'Condition value', 'corex' ) }
							value={ rule.condition?.value || '' }
							onChange={ ( event ) =>
								updateRule( index, {
									condition: {
										...rule.condition,
										value: event.target.value,
									},
								} )
							}
						/>
						<TargetPicker
							idPrefix={ `corex-flow-rule-${ rule.uuid }` }
							target={ rule.target }
							schema={ schema }
							catalog={ catalog }
							onChange={ ( target ) =>
								updateRule( index, { target } )
							}
						/>
						<button
							type="button"
							className="button"
							onClick={ () =>
								onChange( {
									...routing,
									rules: rules.filter(
										( candidate ) =>
											candidate.uuid !== rule.uuid
									),
								} )
							}
						>
							{ __( 'Remove', 'corex' ) }
						</button>
					</li>
				) ) }
			</ol>
			<div className="corex-flow-editor__fallback">
				<strong>
					{ __( 'Fallback: all other submissions', 'corex' ) }
				</strong>
				<TargetPicker
					idPrefix="corex-flow-fallback"
					target={
						routing.fallback || { type: 'flow_owner', config: {} }
					}
					schema={ schema }
					catalog={ catalog }
					onChange={ ( fallback ) =>
						onChange( { ...routing, fallback } )
					}
				/>
			</div>
		</section>
	);
}

/**
 * The target, and the person, team, role or question it names, each picked by name.
 *
 * @param {Object}   props
 * @param {string}   props.idPrefix Unique start of this picker's control ids.
 * @param {Object}   props.target   The stored target.
 * @param {Array}    props.schema   The flow's fields.
 * @param {Object}   props.catalog  The extension catalog: targets, people, teams, roles.
 * @param {Function} props.onChange Given the changed target.
 * @return {import('react').ReactElement} The picker.
 */
export function TargetPicker( {
	idPrefix,
	target,
	schema,
	catalog,
	onChange,
} ) {
	const value = target.config?.value ?? '';
	const setValue = ( next ) =>
		onChange( { ...target, config: { ...target.config, value: next } } );

	return (
		<div className="corex-flow-editor__target">
			<CorexSelect
				id={ `${ idPrefix }-type` }
				label={ __( 'Routing target', 'corex' ) }
				value={ targetChoice( target ) }
				options={ targetOptions(
					catalog.routing_targets || [],
					target
				) }
				onChange={ ( choice ) =>
					onChange( targetFromChoice( choice ) )
				}
			/>
			{ target.type === 'user' ? (
				<CorexSelect
					id={ `${ idPrefix }-user` }
					label={ __( 'User', 'corex' ) }
					value={ String( value ) }
					options={ ownerOptions( 'user', catalog ) }
					onChange={ setValue }
				/>
			) : null }
			{ target.type === 'team' ? (
				<CorexSelect
					id={ `${ idPrefix }-team` }
					label={ __( 'Team', 'corex' ) }
					value={ String( value ) }
					options={ ownerOptions( 'team', catalog ) }
					emptyLabel={ __(
						'No teams yet: create one on the Teams view',
						'corex'
					) }
					onChange={ setValue }
				/>
			) : null }
			{ target.type === 'role' ? (
				<CorexSelect
					id={ `${ idPrefix }-role` }
					label={ __( 'Role', 'corex' ) }
					value={ String( value ) }
					options={ ownerOptions( 'role', catalog ) }
					onChange={ setValue }
				/>
			) : null }
			{ target.type === 'field_value' ? (
				<FieldValueOwners
					idPrefix={ idPrefix }
					target={ target }
					schema={ schema }
					catalog={ catalog }
					onChange={ onChange }
				/>
			) : null }
		</div>
	);
}

/**
 * The people, teams or roles a target can name, as picker options.
 *
 * @param {string} type    `user`, `team` or `role`.
 * @param {Object} catalog The extension catalog.
 * @return {Array} Options, with a first "choose" option.
 */
export function ownerOptions( type, catalog ) {
	const choose = { value: '', label: __( 'Choose…', 'corex' ) };
	if ( type === 'user' ) {
		return [
			choose,
			...( catalog.people || [] ).map( ( person ) => ( {
				value: String( person.id ),
				label: person.name,
			} ) ),
		];
	}
	if ( type === 'team' ) {
		return [
			choose,
			...( catalog.teams || [] ).map( ( team ) => ( {
				value: team.key,
				label: team.name,
			} ) ),
		];
	}
	return [
		choose,
		...( catalog.roles || [] ).map( ( role ) => ( {
			value: role.key,
			label: role.name,
		} ) ),
	];
}

/**
 * "Field value": the question whose answer picks the owner, and the value-to-owner table (spec
 * 122, FR-011). A question with options lists them; any other takes typed values.
 *
 * @param {Object}   props          The props `TargetPicker` is given.
 * @param {string}   props.idPrefix Unique start of the control ids.
 * @param {Object}   props.target   The stored `field_value` target.
 * @param {Array}    props.schema   The flow's fields.
 * @param {Object}   props.catalog  The extension catalog.
 * @param {Function} props.onChange Given the changed target.
 * @return {import('react').ReactElement} The question picker and its table.
 */
function FieldValueOwners( { idPrefix, target, schema, catalog, onChange } ) {
	const fieldKey = target.config?.value || '';
	const field = schema.find( ( candidate ) => candidate.key === fieldKey );
	const owners = Array.isArray( target.config?.owners )
		? target.config.owners
		: [];
	const values = ( field?.options || [] )
		.map( ( option ) => String( option.value ?? option ) )
		.concat(
			owners
				.map( ( row ) => row.value )
				.filter(
					( value ) =>
						! ( field?.options || [] ).some(
							( option ) =>
								String( option.value ?? option ) === value
						)
				)
		);
	const setOwners = ( next ) =>
		onChange( { ...target, config: { ...target.config, owners: next } } );
	const ownerOf = ( value ) =>
		owners.find( ( row ) => row.value === value )?.target || {
			type: 'user',
			value: '',
		};
	const setOwner = ( value, owner ) =>
		setOwners( [
			...owners.filter( ( row ) => row.value !== value ),
			{ value, target: owner },
		] );

	return (
		<div className="corex-flow-editor__value-owners">
			<CorexSelect
				id={ `${ idPrefix }-field` }
				label={ __(
					'Question whose answer chooses the owner',
					'corex'
				) }
				value={ fieldKey }
				options={ [
					{ value: '', label: __( 'Choose a question…', 'corex' ) },
					...schema.map( ( candidate ) => ( {
						value: candidate.key,
						label: candidate.label || candidate.key,
					} ) ),
				] }
				onChange={ ( key ) =>
					onChange( {
						...target,
						config: { value: key, owners: [] },
					} )
				}
			/>
			{ fieldKey ? (
				<table className="corex-flow-editor__owners-table">
					<caption>{ __( 'Who gets each answer', 'corex' ) }</caption>
					<thead>
						<tr>
							<th scope="col">{ __( 'Answer', 'corex' ) }</th>
							<th scope="col">{ __( 'Owner', 'corex' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ values.map( ( value ) => {
							const owner = ownerOf( value );
							return (
								<tr key={ value }>
									<th scope="row">{ value }</th>
									<td>
										<CorexSelect
											label={ __(
												'Owner type',
												'corex'
											) }
											value={ owner.type }
											options={ OWNER_TYPES }
											onChange={ ( type ) =>
												setOwner( value, {
													type,
													value: '',
												} )
											}
										/>
										<CorexSelect
											label={ __( 'Owner', 'corex' ) }
											value={ String(
												owner.value || ''
											) }
											options={ ownerOptions(
												owner.type,
												catalog
											) }
											onChange={ ( chosen ) =>
												setOwner( value, {
													...owner,
													value: chosen,
												} )
											}
										/>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			) : null }
			{ fieldKey && ! field?.options?.length ? (
				<AddAnswer
					id={ `${ idPrefix }-answer` }
					onAdd={ ( value ) =>
						setOwners( [
							...owners,
							{ value, target: { type: 'user', value: '' } },
						] )
					}
				/>
			) : null }
		</div>
	);
}

function AddAnswer( { id, onAdd } ) {
	return (
		<form
			className="corex-flow-editor__add-answer"
			onSubmit={ ( event ) => {
				event.preventDefault();
				const input =
					event.currentTarget.elements.namedItem( 'answer' );
				if ( input.value.trim() ) {
					onAdd( input.value.trim() );
					input.value = '';
				}
			} }
		>
			<label htmlFor={ id }>
				{ __( 'An answer', 'corex' ) }
				<input id={ id } name="answer" />
			</label>
			<button type="submit" className="button">
				{ __( 'Add answer', 'corex' ) }
			</button>
		</form>
	);
}
