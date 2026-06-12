/**
 * Corex Hero — DYNAMIC block edited INLINE on the canvas (spec 029). The eyebrow/title/subtitle
 * are RichText regions; the CTA and the background image are set in the block toolbar/inspector.
 * Text lives in attributes; the PHP HeroRenderer renders server-side (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	URLInputButton,
} from '@wordpress/block-editor';
import { PanelBody, Button, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-hero' } );
		const { eyebrow, title, subtitle, level, ctaText, ctaUrl, image } = attributes;
		const tag = `h${ level || 2 }`;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Hero', 'corex' ) }>
						<RangeControl
							label={ __( 'Heading level', 'corex' ) }
							value={ level || 2 }
							onChange={ ( v ) => setAttributes( { level: v } ) }
							min={ 1 }
							max={ 6 }
						/>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setAttributes( {
										image: { id: media.id, url: media.url, alt: media.alt || '' },
									} )
								}
								allowedTypes={ [ 'image' ] }
								value={ image && image.id }
								render={ ( { open } ) => (
									<Button variant="secondary" onClick={ open }>
										{ image && image.url
											? __( 'Replace background', 'corex' )
											: __( 'Add background image', 'corex' ) }
									</Button>
								) }
							/>
						</MediaUploadCheck>
						{ image && image.url && (
							<Button isDestructive variant="link" onClick={ () => setAttributes( { image: {} } ) }>
								{ __( 'Remove background', 'corex' ) }
							</Button>
						) }
					</PanelBody>
				</InspectorControls>

				{ image && image.url && (
					<img className="corex-hero__bg" src={ image.url } alt={ image.alt || '' } />
				) }
				<div className="corex-hero__inner">
					<RichText
						tagName="p"
						className="corex-hero__eyebrow"
						value={ eyebrow }
						onChange={ ( v ) => setAttributes( { eyebrow: v } ) }
						placeholder={ __( 'Eyebrow (optional)', 'corex' ) }
					/>
					<RichText
						tagName={ tag }
						className="corex-hero__title"
						value={ title }
						onChange={ ( v ) => setAttributes( { title: v } ) }
						placeholder={ __( 'Headline', 'corex' ) }
					/>
					<RichText
						tagName="p"
						className="corex-hero__subtitle"
						value={ subtitle }
						onChange={ ( v ) => setAttributes( { subtitle: v } ) }
						placeholder={ __( 'Subheadline (optional)', 'corex' ) }
					/>
					<div className="corex-hero__cta-edit">
						<RichText
							tagName="span"
							className="corex-hero__cta"
							value={ ctaText }
							onChange={ ( v ) => setAttributes( { ctaText: v } ) }
							placeholder={ __( 'Button label', 'corex' ) }
						/>
						<URLInputButton
							url={ ctaUrl }
							onChange={ ( url ) => setAttributes( { ctaUrl: url } ) }
						/>
					</div>
				</div>
			</div>
		);
	},
	save: () => null,
} );
