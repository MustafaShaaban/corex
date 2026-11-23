/**
 * Corex Breadcrumbs: a records block (spec 136, FR-019). The trail is made from the page being
 * read, so the editor previews the server render (BreadcrumbsRenderer) inside the block's
 * wrapper, inert: a link in the preview leads nowhere. While it loads, and when the server draws
 * nothing, it says so in WordPress's own Placeholder. It has no attributes of its own.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { recordsStates } from '../shared/records-states';

const STATES = recordsStates( {
	label: __( 'Breadcrumbs', 'corex' ),
	loading: __( 'Loading the trail…', 'corex' ),
	empty: __( 'No trail to show.', 'corex' ),
} );

function Edit( { attributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Disabled>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					{ ...STATES }
				/>
			</Disabled>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
