/**
 * Corex Gallery — DYNAMIC block (spec 029). Images are picked from the media library (multi-
 * select); each caption is a RichText region edited inline. The PHP GalleryRenderer renders the
 * grid server-side (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	MediaPlaceholder,
} from '@wordpress/block-editor';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

const toImage = ( media ) => ( {
	id: media.id,
	url: media.url,
	alt: media.alt || '',
	caption: media.caption || '',
} );

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-gallery' } );
		const images = Array.isArray( attributes.images ) ? attributes.images : [];

		const setCaption = ( index, value ) =>
			setAttributes( {
				images: images.map( ( img, i ) => ( i === index ? { ...img, caption: value } : img ) ),
			} );
		const removeImage = ( index ) =>
			setAttributes( { images: images.filter( ( _img, i ) => i !== index ) } );

		if ( images.length === 0 ) {
			return (
				<div { ...blockProps }>
					<MediaPlaceholder
						multiple
						gallery
						allowedTypes={ [ 'image' ] }
						labels={ { title: __( 'Gallery', 'corex' ) } }
						onSelect={ ( media ) => setAttributes( { images: media.map( toImage ) } ) }
					/>
				</div>
			);
		}

		return (
			<div { ...blockProps }>
				{ images.map( ( img, index ) => (
					<figure className="corex-gallery__item" key={ index }>
						<img className="corex-gallery__img" src={ img.url } alt={ img.alt || '' } />
						<RichText
							tagName="figcaption"
							className="corex-gallery__caption"
							value={ img.caption }
							onChange={ ( v ) => setCaption( index, v ) }
							placeholder={ __( 'Caption (optional)', 'corex' ) }
						/>
						<Button isDestructive variant="link" onClick={ () => removeImage( index ) }>
							{ __( 'Remove', 'corex' ) }
						</Button>
					</figure>
				) ) }
				<MediaUploadCheck>
					<MediaUpload
						multiple
						gallery
						allowedTypes={ [ 'image' ] }
						value={ images.map( ( img ) => img.id ) }
						onSelect={ ( media ) => setAttributes( { images: media.map( toImage ) } ) }
						render={ ( { open } ) => (
							<Button variant="secondary" onClick={ open }>
								{ __( 'Edit gallery', 'corex' ) }
							</Button>
						) }
					/>
				</MediaUploadCheck>
			</div>
		);
	},
	save: () => null,
} );
