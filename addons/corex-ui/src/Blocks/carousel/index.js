/**
 * Editor registration for corex/carousel (server-rendered). Sidebar controls drive the
 * slides-per-view, opt-in autoplay, and accessible label; slides are edited as a simple
 * repeatable list of RichText bodies. The preview is the live server render.
 */
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	TextControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';
import './style.scss';

const updateSlide = ( slides, index, content ) =>
	slides.map( ( slide, i ) =>
		i === index ? { ...slide, content } : slide
	);

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const slides = attributes.slides || [];

		const addSlide = () =>
			setAttributes( { slides: [ ...slides, { content: '' } ] } );
		const removeSlide = ( index ) =>
			setAttributes( {
				slides: slides.filter( ( _slide, i ) => i !== index ),
			} );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Carousel', 'corex' ) }>
						<RangeControl
							label={ __( 'Slides per view', 'corex' ) }
							value={ attributes.perView }
							min={ 1 }
							max={ 6 }
							onChange={ ( perView ) =>
								setAttributes( { perView } )
							}
						/>
						<ToggleControl
							label={ __(
								'Autoplay (pauses on hover/focus)',
								'corex'
							) }
							checked={ attributes.autoplay }
							onChange={ ( autoplay ) =>
								setAttributes( { autoplay } )
							}
						/>
						<TextControl
							label={ __( 'Accessible label', 'corex' ) }
							value={ attributes.label }
							onChange={ ( label ) => setAttributes( { label } ) }
						/>
					</PanelBody>
					<PanelBody
						title={ __( 'Slides', 'corex' ) }
						initialOpen={ false }
					>
						{ slides.map( ( slide, index ) => (
							<div
								key={ index }
								className="corex-carousel-editor__slide"
							>
								<RichText
									tagName="p"
									value={ slide.content }
									onChange={ ( content ) =>
										setAttributes( {
											slides: updateSlide(
												slides,
												index,
												content
											),
										} )
									}
									placeholder={ __(
										'Slide content…',
										'corex'
									) }
								/>
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => removeSlide( index ) }
								>
									{ __( 'Remove slide', 'corex' ) }
								</Button>
							</div>
						) ) }
						<Button variant="secondary" onClick={ addSlide }>
							{ __( 'Add slide', 'corex' ) }
						</Button>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			</div>
		);
	},
	save: () => null,
} );
