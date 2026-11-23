/**
 * Language switcher: editor registration (spec 137, FR-021, FR-031, FR-032a; DECISIONS #656, #702).
 *
 * The block shows records it does not hold, the site's languages from the bound language source, so
 * the editor previews the server render of `LanguageSwitcherRenderer` inside the block's own
 * wrapper, inert. With fewer than two languages the render is empty, and the editor shows
 * WordPress's placeholder in its place. The one setting is the form, as the owner approved it
 * (step 2a): Menu, List and Toggle, with one line of help.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
/*
 * ToggleGroupControl is the control the owner approved for the form (step 2a). WordPress still
 * exports it under its experimental name only, so it is imported as that, here and nowhere else.
 */
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	Disabled,
	PanelBody,
	Placeholder,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

/** The preview while the site has one language: the block prints nothing on the page then. */
function NoSecondLanguage() {
	return (
		<Placeholder
			icon="translation"
			label={ __( 'Language switcher', 'corex' ) }
			instructions={ __(
				'The switcher appears here once the site has a second language.',
				'corex'
			) }
		/>
	);
}

function Edit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'corex' ) }>
					<ToggleGroupControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						isBlock
						label={ __( 'Form', 'corex' ) }
						help={ __(
							'With three or more languages, the toggle shows as the list.',
							'corex'
						) }
						value={ attributes.form }
						onChange={ ( form ) => setAttributes( { form } ) }
					>
						<ToggleGroupControlOption
							value="menu"
							label={ __( 'Menu', 'corex' ) }
						/>
						<ToggleGroupControlOption
							value="list"
							label={ __( 'List', 'corex' ) }
						/>
						<ToggleGroupControlOption
							value="toggle"
							label={ __( 'Toggle', 'corex' ) }
						/>
					</ToggleGroupControl>
				</PanelBody>
			</InspectorControls>
			<Disabled>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					EmptyResponsePlaceholder={ NoSecondLanguage }
				/>
			</Disabled>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: nothing is saved to post content; the server renders it.
	save: () => null,
} );
