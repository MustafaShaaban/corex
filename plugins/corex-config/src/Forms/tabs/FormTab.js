import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { FieldSettings } from '../components/FieldSettings.js';
import CorexSelect from '../../admin/components/CorexSelect.js';

export function FormTab( { draft, fieldTypes, busy, onAdd, onUpdate, onMove, onRemove } ) {
	const [ selected, setSelected ] = useState( draft.configuration.schema[ 0 ]?.uuid || null );
	const [ type, setType ] = useState( fieldTypes[ 0 ]?.key || 'text' );
	const field = draft.configuration.schema.find( ( candidate ) => candidate.uuid === selected );

	return (
		<div className="corex-flow-editor__split">
			<section className="corex-flow-editor__field-list">
				<header><div><h2>{ __( 'Form fields', 'corex' ) }</h2><p>{ __( 'Fields keep stable identities when reordered.', 'corex' ) }</p></div></header>
				<div className="corex-flow-editor__add-field">
					<div className="corex-field">
						<span>{ __( 'Field type', 'corex' ) }</span>
						<CorexSelect id="corex-flow-new-field-type" label={ __( 'Field type', 'corex' ) }
							value={ type }
							options={ fieldTypes.map( ( definition ) => ( { value: definition.key, label: definition.label } ) ) }
							onChange={ setType } />
					</div>
					<button type="button" className="button" disabled={ busy } onClick={ () => setSelected( onAdd( type ) ) }>{ __( 'Add field', 'corex' ) }</button>
				</div>
				{ draft.configuration.schema.length === 0 ? <p>{ __( 'Add the first field to configure this form.', 'corex' ) }</p> : null }
				<ol>
					{ draft.configuration.schema.map( ( item, index ) => (
						<li key={ item.uuid } className={ selected === item.uuid ? 'is-active' : '' }>
							<button type="button" onClick={ () => setSelected( item.uuid ) }><strong>{ item.label }</strong><code>{ item.key }</code><span>{ item.type }</span></button>
							<div>
								<button type="button" disabled={ busy || index === 0 } aria-label={ sprintf( /* translators: %s: Field label. */ __( 'Move %s up', 'corex' ), item.label ) } onClick={ () => onMove( item.uuid, 'up' ) }>{ __( 'Up', 'corex' ) }</button>
								<button type="button" disabled={ busy || index === draft.configuration.schema.length - 1 } aria-label={ sprintf( /* translators: %s: Field label. */ __( 'Move %s down', 'corex' ), item.label ) } onClick={ () => onMove( item.uuid, 'down' ) }>{ __( 'Down', 'corex' ) }</button>
								<button type="button" disabled={ busy } aria-label={ sprintf( /* translators: %s: Field label. */ __( 'Remove %s', 'corex' ), item.label ) } onClick={ () => { onRemove( item.uuid ); setSelected( null ); } }>{ __( 'Remove', 'corex' ) }</button>
							</div>
						</li>
					) ) }
				</ol>
			</section>
			{ field ? <FieldSettings field={ field } onChange={ ( changes ) => onUpdate( field.uuid, changes ) } /> : <section className="corex-flow-editor__field-settings"><p>{ __( 'Select a field to edit its settings.', 'corex' ) }</p></section> }
		</div>
	);
}
