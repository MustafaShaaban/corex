/**
 * The mega menu item's panel, chosen in the block's settings (spec 137, FR-013; Edge Cases).
 *
 * A panel is a template part of the `mega-menu` area. These controls list the area's parts, open the
 * chosen one for editing, start a new one from a panel pattern (a pattern made for the area) and
 * choose it at once, and say when the item has no panel or its panel is gone, since such an item is
 * not shown on the page. They are WordPress's own components, as they come (FR-013).
 */
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import {
	Button,
	Notice,
	PanelBody,
	SelectControl,
} from '@wordpress/components';

export const AREA = 'mega-menu';
export const PATTERN_BLOCK_TYPE = 'core/template-part/mega-menu';
const PART = 'wp_template_part';

/**
 * A slug for a new panel from a pattern's name: the name without its namespace, numbered past the
 * slugs already taken.
 *
 * @param {string}        patternName The pattern's name (`corex/megamenu-docs`).
 * @param {Array<string>} taken       The slugs of the theme's parts.
 * @return {string} A slug no part has.
 */
export function freeSlug( patternName, taken ) {
	const base = patternName.split( '/' ).pop();
	let slug = base;
	for ( let number = 2; taken.includes( slug ); number++ ) {
		slug = `${ base }-${ number }`;
	}

	return slug;
}

/**
 * Where the Site Editor opens a part for editing, for an editor that cannot open it itself.
 *
 * @param {string} id The part's id (`theme//slug`).
 * @return {string} The address, relative to the admin.
 */
function siteEditorAddress( id ) {
	return `site-editor.php?p=${ encodeURIComponent(
		`/${ PART }/${ id }`
	) }&canvas=edit`;
}

function useSources() {
	return useSelect( ( select ) => {
		const core = select( 'core' );

		return {
			parts: core.getEntityRecords( 'postType', PART, {
				area: AREA,
				per_page: -1,
			} ),
			patterns: core.getBlockPatterns() || [],
			navigate:
				select( 'core/block-editor' ).getSettings()
					.onNavigateToEntityRecord,
		};
	}, [] );
}

function EditPanelButton( { part, navigate } ) {
	if ( typeof navigate === 'function' ) {
		return (
			<Button
				variant="secondary"
				onClick={ () =>
					navigate( { postId: part.id, postType: PART } )
				}
			>
				{ __( 'Edit panel', 'corex' ) }
			</Button>
		);
	}

	return (
		<Button variant="secondary" href={ siteEditorAddress( part.id ) }>
			{ __( 'Edit panel', 'corex' ) }
		</Button>
	);
}

function NewPanel( { parts, patterns, setPanel } ) {
	const { saveEntityRecord } = useDispatch( 'core' );
	const [ patternName, setPatternName ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ failed, setFailed ] = useState( false );
	const panelPatterns = patterns.filter( ( pattern ) =>
		( pattern.blockTypes || [] ).includes( PATTERN_BLOCK_TYPE )
	);

	if ( panelPatterns.length === 0 ) {
		return <p>{ __( 'No panel pattern is available.', 'corex' ) }</p>;
	}

	async function create() {
		const pattern = panelPatterns.find(
			( candidate ) => candidate.name === patternName
		);
		setBusy( true );
		setFailed( false );
		try {
			const record = await saveEntityRecord(
				'postType',
				PART,
				{
					slug: freeSlug(
						pattern.name,
						( parts || [] ).map( ( part ) => part.slug )
					),
					title: pattern.title,
					area: AREA,
					content: pattern.content,
				},
				{ throwOnError: true }
			);
			setPanel( record.slug );
		} catch {
			setFailed( true );
		}
		setBusy( false );
	}

	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Start from', 'corex' ) }
				value={ patternName }
				options={ [
					{ value: '', label: __( 'Choose a pattern', 'corex' ) },
					...panelPatterns.map( ( pattern ) => ( {
						value: pattern.name,
						label: pattern.title,
					} ) ),
				] }
				onChange={ setPatternName }
			/>
			<Button
				variant="primary"
				disabled={ patternName === '' || busy }
				isBusy={ busy }
				onClick={ create }
			>
				{ __( 'Create panel', 'corex' ) }
			</Button>
			{ failed && (
				<Notice status="error" isDismissible={ false }>
					{ __( 'The panel could not be created.', 'corex' ) }
				</Notice>
			) }
		</>
	);
}

/**
 * @param {Object}                 props
 * @param {string}                 props.panel    The slug of the item's panel, or empty.
 * @param {(slug: string) => void} props.setPanel Sets it.
 * @return {Element} The controls.
 */
export default function PanelControls( { panel, setPanel } ) {
	const { parts, patterns, navigate } = useSources();
	const panels = ( parts || [] ).filter( ( part ) => part.area === AREA );
	const chosen = panels.find( ( part ) => part.slug === panel );
	let warning = null;
	if ( ! panel ) {
		warning = __(
			'Choose a panel. Until one is chosen, this item is not shown on the page.',
			'corex'
		);
	} else if ( parts && ! chosen ) {
		warning = __(
			'The chosen panel no longer exists. Choose another; until then this item is not shown on the page.',
			'corex'
		);
	}

	return (
		<>
			<PanelBody title={ __( 'Panel', 'corex' ) }>
				{ warning && (
					<Notice status="warning" isDismissible={ false }>
						{ warning }
					</Notice>
				) }
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Panel', 'corex' ) }
					value={ chosen ? panel : '' }
					options={ [
						{ value: '', label: __( 'Choose a panel', 'corex' ) },
						...panels.map( ( part ) => ( {
							value: part.slug,
							label: part.title?.rendered || part.slug,
						} ) ),
					] }
					onChange={ setPanel }
					help={ __(
						'Panels are template parts of the Mega menu panels area.',
						'corex'
					) }
				/>
				{ chosen && (
					<EditPanelButton part={ chosen } navigate={ navigate } />
				) }
			</PanelBody>
			<PanelBody
				title={ __( 'New panel', 'corex' ) }
				initialOpen={ ! chosen }
			>
				<NewPanel
					parts={ parts }
					patterns={ patterns }
					setPanel={ setPanel }
				/>
			</PanelBody>
		</>
	);
}
