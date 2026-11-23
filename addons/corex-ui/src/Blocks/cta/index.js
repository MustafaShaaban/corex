/**
 * Corex Call to Action: a dynamic block edited in place (spec 029; spec 136, FR-017). The editor
 * draws the elements CtaRenderer prints: the heading, the supporting line and the button typed on
 * the canvas, the button a link as on the page. Its address field is the editor's own
 * (`data-corex-editor-only`). `parity.json` is the tree both must draw.
 * CtaRenderer renders it on the server (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	RichText,
	URLInputButton,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
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
				<RichText
					tagName="a"
					className="corex-cta__button"
					value={ ctaText }
					onChange={ ( v ) => setAttributes( { ctaText: v } ) }
					placeholder={ __( 'Button label', 'corex' ) }
					withoutInteractiveFormatting
				/>
				<div data-corex-editor-only>
					<URLInputButton
						url={ ctaUrl }
						onChange={ ( url ) => setAttributes( { ctaUrl: url } ) }
					/>
				</div>
			</div>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
