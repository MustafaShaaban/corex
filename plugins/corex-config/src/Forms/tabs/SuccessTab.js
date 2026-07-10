import { __ } from '@wordpress/i18n';

export function SuccessTab( { success, stateTypes, onChange } ) {
	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Success behavior', 'corex' ) }</h2>
			<label htmlFor="corex-flow-success-type">{ __( 'Behavior', 'corex' ) }
				<select id="corex-flow-success-type" value={ success.type || 'inline' } onChange={ ( event ) => onChange( { type: event.target.value } ) }>
					{ stateTypes.map( ( type ) => <option key={ type } value={ type }>{ type }</option> ) }
				</select>
			</label>
			{ success.type === 'inline' ? <label htmlFor="corex-flow-success-message">{ __( 'Message', 'corex' ) }<textarea id="corex-flow-success-message" value={ success.message || '' } onChange={ ( event ) => onChange( { ...success, message: event.target.value } ) } /></label> : null }
			{ success.type === 'page' ? <label htmlFor="corex-flow-success-page">{ __( 'Page ID', 'corex' ) }<input id="corex-flow-success-page" type="number" min="1" value={ success.page_id || '' } onChange={ ( event ) => onChange( { ...success, page_id: Number( event.target.value ) } ) } /></label> : null }
			{ success.type === 'url' ? <label htmlFor="corex-flow-success-url">{ __( 'Redirect URL', 'corex' ) }<input id="corex-flow-success-url" type="url" value={ success.url || '' } onChange={ ( event ) => onChange( { ...success, url: event.target.value } ) } /></label> : null }
		</section>
	);
}
