import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	addField,
	moveField,
	removeField,
	stageStatus,
	updateConfiguration,
	updateField,
} from './flowEditor.js';
import { StageRail } from './StageRail.js';
import { FormTab } from './tabs/FormTab.js';
import { ValidationTab } from './tabs/ValidationTab.js';
import { RoutingTab } from './tabs/RoutingTab.js';
import { EmailsTab } from './tabs/EmailsTab.js';
import { ProtectionTab } from './tabs/ProtectionTab.js';
import { SuccessTab } from './tabs/SuccessTab.js';
import { PreviewTab } from './tabs/PreviewTab.js';
import { TestTab } from './tabs/TestTab.js';

export function FlowEditorPanel( { studio, onBack } ) {
	const [ stage, setStage ] = useState( 'form' );
	const { state } = studio;
	const { draft, extensions } = state;
	const busy = state.status === 'loading' || state.status === 'mutating';
	const changeDraft = ( next ) => studio.dispatch( { type: 'draft_changed', payload: next } );
	const changeConfiguration = ( key, value ) => changeDraft( updateConfiguration( draft, key, value ) );
	const add = ( type ) => {
		const next = addField( draft, type );
		changeDraft( next );
		return next.configuration.schema.at( -1 ).uuid;
	};

	return (
		<div className="corex-flow-editor">
			<header className="corex-surface corex-flow-editor__toolbar">
				<div>
					<button type="button" className="button-link" onClick={ onBack }>{ __( 'Back to flows', 'corex' ) }</button>
					<h2>{ draft.flow.name }</h2><code>{ draft.flow.slug }</code>
				</div>
				<div className="corex-flow-editor__actions">
					<button type="button" className="button button-primary" disabled={ busy } onClick={ studio.saveDraft }>{ __( 'Save draft', 'corex' ) }</button>
					{ draft.flow.state === 'draft' ? <button type="button" className="button" disabled={ busy } onClick={ studio.publish }>{ __( 'Publish', 'corex' ) }</button> : null }
					{ draft.flow.state !== 'draft' ? <button type="button" className="button" disabled={ busy } onClick={ studio.unpublish }>{ __( 'Move to draft', 'corex' ) }</button> : null }
					{ draft.flow.state === 'published' ? <button type="button" className="button" disabled={ busy } onClick={ studio.close }>{ __( 'Close', 'corex' ) }</button> : null }
					<button type="button" className="button" onClick={ () => setStage( 'preview' ) }>{ __( 'Preview', 'corex' ) }</button>
				</div>
			</header>
			<StageRail active={ stage } statuses={ stageStatus( draft ) } onChange={ setStage } />
			<div className="corex-surface corex-flow-editor__workspace">
				{ stage === 'form' ? <FormTab draft={ draft } fieldTypes={ extensions.field_types || [] } busy={ busy } onAdd={ add } onUpdate={ ( uuid, changes ) => changeDraft( updateField( draft, uuid, changes ) ) } onMove={ ( uuid, direction ) => changeDraft( moveField( draft, uuid, direction ) ) } onRemove={ ( uuid ) => changeDraft( removeField( draft, uuid ) ) } /> : null }
				{ stage === 'validation' ? <ValidationTab draft={ draft } rules={ extensions.validation_rules || [] } onChange={ ( value ) => changeConfiguration( 'validation', value ) } /> : null }
				{ stage === 'routing' ? <RoutingTab routing={ draft.configuration.routing } targetTypes={ extensions.routing_targets || [] } onChange={ ( value ) => changeConfiguration( 'routing', value ) } /> : null }
				{ stage === 'emails' ? <EmailsTab routes={ draft.configuration.email_routes || [] } templates={ extensions.email_templates || [] } onChange={ ( value ) => changeConfiguration( 'email_routes', value ) } /> : null }
				{ stage === 'protection' ? <ProtectionTab protection={ draft.configuration.protection || {} } onChange={ ( value ) => changeConfiguration( 'protection', value ) } /> : null }
				{ stage === 'success' ? <SuccessTab success={ draft.configuration.success || {} } stateTypes={ extensions.success_states || [] } onChange={ ( value ) => changeConfiguration( 'success', value ) } /> : null }
				{ stage === 'preview' ? <PreviewTab draft={ draft } /> : null }
				{ stage === 'test' ? <TestTab busy={ busy } result={ state.testResult } onRun={ studio.test } /> : null }
			</div>
		</div>
	);
}
