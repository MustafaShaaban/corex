/**
 * Corex Stat — DYNAMIC block edited INLINE on the canvas (spec 029). The value/label/
 * description are RichText regions: type into them directly, like a modern page builder.
 * The text lives in block attributes; the PHP StatRenderer renders them server-side
 * (save: () => null), so editor and front end share one source of truth.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-stat' } );
		const { value, label, description } = attributes;

		return (
			<div { ...blockProps }>
				<RichText
					tagName="span"
					className="corex-stat__value"
					value={ value }
					onChange={ ( v ) => setAttributes( { value: v } ) }
					placeholder={ __( 'Value (e.g. 98%)', 'corex' ) }
				/>
				<RichText
					tagName="span"
					className="corex-stat__label"
					value={ label }
					onChange={ ( v ) => setAttributes( { label: v } ) }
					placeholder={ __( 'Label', 'corex' ) }
				/>
				<RichText
					tagName="p"
					className="corex-stat__desc"
					value={ description }
					onChange={ ( v ) => setAttributes( { description: v } ) }
					placeholder={ __( 'Description (optional)', 'corex' ) }
				/>
			</div>
		);
	},
	save: () => null,
} );
