/**
 * Corex Team — DYNAMIC block edited INLINE (spec 029). Members are repeatable cards; each name/
 * role/bio is a RichText region and the photo is picked from the media library. The PHP
 * TeamRenderer renders the grid server-side (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import { PanelBody, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-team' } );
		const members = Array.isArray( attributes.members ) ? attributes.members : [];

		const setMember = ( index, key, value ) =>
			setAttributes( {
				members: members.map( ( m, i ) => ( i === index ? { ...m, [ key ]: value } : m ) ),
			} );
		const addMember = () =>
			setAttributes( { members: [ ...members, { name: '', role: '', image: {}, bio: '' } ] } );
		const removeMember = ( index ) =>
			setAttributes( { members: members.filter( ( _m, i ) => i !== index ) } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Team', 'corex' ) }>
						<Button variant="secondary" onClick={ addMember }>
							{ __( 'Add member', 'corex' ) }
						</Button>
					</PanelBody>
				</InspectorControls>

				{ members.map( ( member, index ) => (
					<figure className="corex-team__member" key={ index }>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setMember( index, 'image', {
										id: media.id,
										url: media.url,
										alt: media.alt || '',
									} )
								}
								allowedTypes={ [ 'image' ] }
								value={ member.image && member.image.id }
								render={ ( { open } ) =>
									member.image && member.image.url ? (
										<img
											className="corex-team__photo"
											src={ member.image.url }
											alt={ member.image.alt || '' }
											onClick={ open }
										/>
									) : (
										<Button variant="secondary" onClick={ open }>
											{ __( 'Add photo', 'corex' ) }
										</Button>
									)
								}
							/>
						</MediaUploadCheck>
						<figcaption className="corex-team__caption">
							<RichText
								tagName="span"
								className="corex-team__name"
								value={ member.name }
								onChange={ ( v ) => setMember( index, 'name', v ) }
								placeholder={ __( 'Name', 'corex' ) }
							/>
							<RichText
								tagName="span"
								className="corex-team__role"
								value={ member.role }
								onChange={ ( v ) => setMember( index, 'role', v ) }
								placeholder={ __( 'Role', 'corex' ) }
							/>
							<RichText
								tagName="p"
								className="corex-team__bio"
								value={ member.bio }
								onChange={ ( v ) => setMember( index, 'bio', v ) }
								placeholder={ __( 'Short bio (optional)', 'corex' ) }
							/>
							<Button isDestructive variant="link" onClick={ () => removeMember( index ) }>
								{ __( 'Remove member', 'corex' ) }
							</Button>
						</figcaption>
					</figure>
				) ) }

				<Button variant="secondary" onClick={ addMember }>
					{ __( 'Add member', 'corex' ) }
				</Button>
			</div>
		);
	},
	save: () => null,
} );
