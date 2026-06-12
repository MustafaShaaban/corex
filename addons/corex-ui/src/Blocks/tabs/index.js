/**
 * Corex Tabs — DYNAMIC block edited INLINE (spec 029). Tabs are repeatable rows; each label and
 * content is a RichText region. The PHP TabsRenderer renders an accessible, CSS-only (no view JS)
 * tabs widget server-side (save: () => null). In the editor each tab is shown stacked for editing.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-tabs is-editing' } );
		const tabs = Array.isArray( attributes.tabs ) ? attributes.tabs : [];

		const setTab = ( index, key, value ) =>
			setAttributes( {
				tabs: tabs.map( ( t, i ) => ( i === index ? { ...t, [ key ]: value } : t ) ),
			} );
		const addTab = () => setAttributes( { tabs: [ ...tabs, { label: '', content: '' } ] } );
		const removeTab = ( index ) =>
			setAttributes( { tabs: tabs.filter( ( _t, i ) => i !== index ) } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Tabs', 'corex' ) }>
						<Button variant="secondary" onClick={ addTab }>
							{ __( 'Add tab', 'corex' ) }
						</Button>
					</PanelBody>
				</InspectorControls>

				{ tabs.map( ( tab, index ) => (
					<div className="corex-tabs__edit-row" key={ index }>
						<RichText
							tagName="span"
							className="corex-tabs__label"
							value={ tab.label }
							onChange={ ( v ) => setTab( index, 'label', v ) }
							placeholder={ __( 'Tab label', 'corex' ) }
						/>
						<div className="corex-tabs__panel">
							<RichText
								tagName="div"
								value={ tab.content }
								onChange={ ( v ) => setTab( index, 'content', v ) }
								placeholder={ __( 'Tab content', 'corex' ) }
							/>
							<Button isDestructive variant="link" onClick={ () => removeTab( index ) }>
								{ __( 'Remove tab', 'corex' ) }
							</Button>
						</div>
					</div>
				) ) }

				<Button variant="secondary" onClick={ addTab }>
					{ __( 'Add tab', 'corex' ) }
				</Button>
			</div>
		);
	},
	save: () => null,
} );
