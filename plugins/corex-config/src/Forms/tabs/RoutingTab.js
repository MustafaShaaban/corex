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

export function RoutingTab( { routing, targetTypes, onChange } ) {
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
					<h2>{ __( 'Routing', 'corex' ) }</h2>
					<p>
						{ __(
							'Rules evaluate top-down. The first match wins.',
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
						<TargetSelect
							target={ rule.target }
							targetTypes={ targetTypes }
							onChange={ ( target ) =>
								updateRule( index, { target } )
							}
						/>
						<button
							type="button"
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
				<strong>{ __( 'Required fallback', 'corex' ) }</strong>
				<TargetSelect
					target={
						routing.fallback || { type: 'flow_owner', config: {} }
					}
					targetTypes={ targetTypes }
					onChange={ ( fallback ) =>
						onChange( { ...routing, fallback } )
					}
				/>
			</div>
		</section>
	);
}

function TargetSelect( { target, targetTypes, onChange } ) {
	return (
		<div className="corex-flow-editor__target">
			<CorexSelect
				label={ __( 'Routing target type', 'corex' ) }
				value={ target.type }
				options={ targetTypes.map( ( type ) => ( { value: type, label: type } ) ) }
				onChange={ ( type ) => onChange( { type, config: {} } ) }
			/>
			<input
				aria-label={ __( 'Routing target value', 'corex' ) }
				value={ target.config?.value || '' }
				onChange={ ( event ) =>
					onChange( {
						...target,
						config: { value: event.target.value },
					} )
				}
			/>
		</div>
	);
}
