/**
 * Corex Call to Action — DYNAMIC block edited INLINE (spec 029). Heading + supporting text are
 * RichText regions; the button label is RichText and its link is set with URLInputButton. The
 * PHP CtaRenderer renders server-side (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, URLInputButton } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-cta' } );
		const { title, text, ctaText, ctaUrl } = attributes;

		return (
			<div { ...blockProps }>
				<div className="corex-cta__inner">
					<RichText
						tagName="h2"
						className="corex-cta__title"
						value={ title }
						onChange={ ( v ) => setAttributes( { title: v } ) }
						placeholder={ __( 'Heading', 'corex' ) }
					/>
					<RichText
						tagName="p"
						className="corex-cta__text"
						value={ text }
						onChange={ ( v ) => setAttributes( { text: v } ) }
						placeholder={ __( 'Supporting line (optional)', 'corex' ) }
					/>
					<div className="corex-cta__button-edit">
						<RichText
							tagName="span"
							className="corex-cta__button"
							value={ ctaText }
							onChange={ ( v ) => setAttributes( { ctaText: v } ) }
							placeholder={ __( 'Button label', 'corex' ) }
						/>
						<URLInputButton url={ ctaUrl } onChange={ ( url ) => setAttributes( { ctaUrl: url } ) } />
					</div>
				</div>
			</div>
		);
	},
	save: () => null,
} );
