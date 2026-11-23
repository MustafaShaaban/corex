/**
 * Corex Copyright: a dynamic block drawn in place (spec 136, FR-018). The editor draws the line
 * CopyrightRenderer prints, the same translated sentence with the year and the site's name, so
 * the canvas shows the footer as published without asking the server to draw it. It has no
 * attributes; `parity.json` is the tree both must draw. CopyrightRenderer renders it on the
 * server (save: () => null).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json';

function Edit() {
	const blockProps = useBlockProps( { className: 'corex-copyright' } );
	// The site's name as anyone who edits may read it, as WordPress's Site Title block reads it.
	// WordPress keeps it escaped (`&#039;`, `&amp;`); the line shows its characters.
	const siteName = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecord( 'root', '__unstableBase' )
				?.name ?? '',
		[]
	);

	return (
		<p { ...blockProps }>
			{ sprintf(
				/* translators: 1: year, 2: site name */
				__( '© %1$s %2$s. All rights reserved.', 'corex' ),
				// The renderer's year is UTC (`gmdate( 'Y' )`).
				new Date().getUTCFullYear(),
				decodeEntities( siteName )
			) }
		</p>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
