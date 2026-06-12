/**
 * Corex Accordion — DYNAMIC block edited INLINE (spec 029). Each panel's title and content
 * are RichText regions typed on the canvas; panels are repeatable rows. The PHP
 * AccordionRenderer builds accessible native <details>/<summary> server-side from the
 * `items` array (with a fallback that still renders the legacy delimited-string format).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-accordion' } );
		const items = Array.isArray( attributes.items ) ? attributes.items : [];

		const setItem = ( index, key, value ) => {
			const next = items.map( ( item, i ) =>
				i === index ? { ...item, [ key ]: value } : item
			);
			setAttributes( { items: next } );
		};
		const addItem = () =>
			setAttributes( { items: [ ...items, { title: '', content: '' } ] } );
		const removeItem = ( index ) =>
			setAttributes( { items: items.filter( ( _it, i ) => i !== index ) } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Accordion', 'corex' ) }>
						<Button variant="secondary" onClick={ addItem }>
							{ __( 'Add panel', 'corex' ) }
						</Button>
					</PanelBody>
				</InspectorControls>

				{ items.map( ( item, index ) => (
					<details className="corex-accordion__item" key={ index } open>
						<summary className="corex-accordion__summary">
							<RichText
								tagName="span"
								value={ item.title }
								onChange={ ( v ) => setItem( index, 'title', v ) }
								placeholder={ __( 'Panel title', 'corex' ) }
							/>
						</summary>
						<div className="corex-accordion__content">
							<RichText
								tagName="div"
								value={ item.content }
								onChange={ ( v ) => setItem( index, 'content', v ) }
								placeholder={ __( 'Panel content', 'corex' ) }
							/>
							<Button
								isDestructive
								variant="link"
								onClick={ () => removeItem( index ) }
							>
								{ __( 'Remove panel', 'corex' ) }
							</Button>
						</div>
					</details>
				) ) }

				<Button variant="secondary" onClick={ addItem }>
					{ __( 'Add panel', 'corex' ) }
				</Button>
			</div>
		);
	},
	save: () => null,
} );
