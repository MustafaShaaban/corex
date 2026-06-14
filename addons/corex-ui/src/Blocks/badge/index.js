/**
 * Corex Badge — DYNAMIC block (spec 051). The label is an inline RichText region; rendered
 * server-side by BadgeRenderer (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-badge' } );

		return (
			<RichText
				{ ...blockProps }
				tagName="span"
				value={ attributes.label }
				onChange={ ( label ) => setAttributes( { label } ) }
				placeholder={ __( 'Badge', 'corex' ) }
			/>
		);
	},
	save: () => null,
} );
