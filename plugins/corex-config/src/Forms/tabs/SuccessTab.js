import { __ } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';

export function SuccessTab( { success, stateTypes, onChange } ) {
	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Success behavior', 'corex' ) }</h2>
			<div className="corex-field">
				<span>{ __( 'Behavior', 'corex' ) }</span>
				<CorexSelect id="corex-flow-success-type" label={ __( 'Behavior', 'corex' ) }
					value={ success.type || 'inline' }
					options={ stateTypes.map( ( type ) => ( { value: type, label: type } ) ) }
					onChange={ ( type ) => onChange( { type } ) } block />
			</div>
			{ success.type === 'inline' ? <label htmlFor="corex-flow-success-message">{ __( 'Message', 'corex' ) }<textarea id="corex-flow-success-message" value={ success.message || '' } onChange={ ( event ) => onChange( { ...success, message: event.target.value } ) } /></label> : null }
			{ success.type === 'page' ? <label htmlFor="corex-flow-success-page">{ __( 'Page ID', 'corex' ) }<input id="corex-flow-success-page" type="number" min="1" value={ success.page_id || '' } onChange={ ( event ) => onChange( { ...success, page_id: Number( event.target.value ) } ) } /></label> : null }
			{ success.type === 'url' ? <label htmlFor="corex-flow-success-url">{ __( 'Redirect URL', 'corex' ) }<input id="corex-flow-success-url" type="url" value={ success.url || '' } onChange={ ( event ) => onChange( { ...success, url: event.target.value } ) } /></label> : null }
		</section>
	);
}
