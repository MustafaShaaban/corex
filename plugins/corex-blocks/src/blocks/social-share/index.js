/**
 * Social Share: a dynamic block drawn in place (spec 063; spec 136, FR-018).
 *
 * The editor draws the bar SocialShareRenderer prints: its label, one link per chosen network in
 * the renderer's order, and the copy-link button that a browser with a clipboard shows. The
 * device's own share button stays hidden, as it does wherever the device cannot share. A link
 * leads nowhere in the editor: the address it shares is the post's, known only on the page. The
 * networks are chosen in the side panel. `parity.json` is the tree both must draw. The renderer
 * draws it on the server from the post's address (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { ShareIcon } from './icons';

/** The networks, in the order the renderer prints them, with the name a screen reader hears. */
const NETWORKS = [
	{ id: 'x', label: __( 'X', 'corex' ), share: __( 'Share on X', 'corex' ) },
	{
		id: 'facebook',
		label: __( 'Facebook', 'corex' ),
		share: __( 'Share on Facebook', 'corex' ),
	},
	{
		id: 'linkedin',
		label: __( 'LinkedIn', 'corex' ),
		share: __( 'Share on LinkedIn', 'corex' ),
	},
	{
		id: 'whatsapp',
		label: __( 'WhatsApp', 'corex' ),
		share: __( 'Share on WhatsApp', 'corex' ),
	},
	{
		id: 'email',
		label: __( 'Email', 'corex' ),
		share: __( 'Share by email', 'corex' ),
	},
];

/**
 * The networks the bar shows: the chosen ones in the renderer's order, or all when none is.
 *
 * @param {Array<string>} selected The block's `networks`.
 * @return {Array<Object>} The networks to draw.
 */
function shown( selected ) {
	const chosen = NETWORKS.filter( ( network ) =>
		selected.includes( network.id )
	);

	return chosen.length > 0 ? chosen : NETWORKS;
}

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'corex-social-share',
		role: 'group',
		'aria-label': __( 'Share this post', 'corex' ),
	} );
	const selected = attributes.networks || [];

	const toggle = ( id ) => ( checked ) => {
		const next = checked
			? [ ...selected, id ]
			: selected.filter( ( value ) => value !== id );
		setAttributes( { networks: next } );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Networks', 'corex' ) }>
					{ NETWORKS.map( ( network ) => (
						<CheckboxControl
							key={ network.id }
							__nextHasNoMarginBottom
							label={ network.label }
							checked={ selected.includes( network.id ) }
							onChange={ toggle( network.id ) }
						/>
					) ) }
				</PanelBody>
			</InspectorControls>
			<span className="corex-social-share__label">
				{ __( 'Share', 'corex' ) }
			</span>
			{ shown( selected ).map( ( network ) => (
				// The page's link shares the post's address; in the editor it leads nowhere.
				// eslint-disable-next-line jsx-a11y/anchor-is-valid
				<a
					key={ network.id }
					className={ `corex-social-share__link corex-social-share__link--${ network.id }` }
				>
					<ShareIcon name={ network.id } />
					<span className="screen-reader-text">
						{ network.share }
					</span>
				</a>
			) ) }
			<button
				type="button"
				className="corex-social-share__copy"
				tabIndex={ -1 }
			>
				<ShareIcon name="copy" />
				<span className="corex-social-share__copy-label">
					{ __( 'Copy link', 'corex' ) }
				</span>
			</button>
			<button
				type="button"
				className="corex-social-share__native"
				tabIndex={ -1 }
				hidden
			>
				<ShareIcon name="share" />
				<span className="screen-reader-text">
					{ __( 'Share via your device', 'corex' ) }
				</span>
			</button>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: nothing is saved to post content; the server renders it.
	save: () => null,
} );
