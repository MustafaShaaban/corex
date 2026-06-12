/**
 * Corex Testimonial — DYNAMIC block edited INLINE (spec 029). Quote/author/role are
 * RichText regions typed directly on the canvas; the PHP TestimonialRenderer builds the
 * accessible figure/blockquote/figcaption server-side from the attributes.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-testimonial' } );
		const { quote, author, role } = attributes;

		return (
			<figure { ...blockProps }>
				<RichText
					tagName="blockquote"
					className="corex-testimonial__quote"
					value={ quote }
					onChange={ ( v ) => setAttributes( { quote: v } ) }
					placeholder={ __( 'The testimonial quote…', 'corex' ) }
				/>
				<figcaption className="corex-testimonial__cite">
					<RichText
						tagName="span"
						value={ author }
						onChange={ ( v ) => setAttributes( { author: v } ) }
						placeholder={ __( 'Author', 'corex' ) }
					/>
					<RichText
						tagName="span"
						value={ role }
						onChange={ ( v ) => setAttributes( { role: v } ) }
						placeholder={ __( 'Role (optional)', 'corex' ) }
					/>
				</figcaption>
			</figure>
		);
	},
	save: () => null,
} );
