import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const GENERATED_AT = 'source-controlled';
const INVENTORY_PATH = 'specs/057-brand-tokens-logo-system/inventories';
const SOURCE_ROOTS = [ 'theme', 'plugins', 'addons', 'packages' ];
const SOURCE_EXTENSIONS = /\.(?:css|scss|json|php|js)$/;
const SKIPPED_PATH = /\/(?:node_modules|vendor|build|dist)\//;
const ADDED_DEFINITIONS = new Set( [
	'color.inverse',
	'color.overlay',
	'color.selection',
	'color.selection-text',
	'color.surface-raised',
	'color.surface-strong',
	'font-family.body',
	'font-family.mono',
	'border.width.thin',
	'border.width.strong',
] );
const LEGACY_TARGETS = {
	'--wp--preset--color--background': 'color.surface',
	'--wp--preset--color--foreground': 'color.ink',
	'--wp--preset--color--danger': 'color.error',
	'--wp--preset--color--corex-danger': 'color.error',
	'--wp--preset--color--corex-info': 'color.info',
	'--wp--preset--color--corex-primary': 'color.primary',
	'--wp--preset--color--corex-success': 'color.success',
	'--wp--preset--color--corex-warning': 'color.warning',
	'--wp--preset--color--white': 'color.inverse',
	'--wp--preset--font-size--small': 'font-size.sm',
	'--wp--preset--font-size--medium': 'font-size.base',
};
const ADMIN_FILES = [
	'plugins/corex-config/assets/control-panel.css',
	'plugins/corex-config/assets/data.css',
	'plugins/corex-config/assets/insights.css',
	'addons/corex-captcha/assets/captcha-admin.css',
];
const ADMIN_OWNERS = [
	{
		style: 'corex-control-panel',
		asset: ADMIN_FILES[ 0 ],
		owner: 'plugins/corex-config/src/Settings/AdminDashboard.php',
		scope: '.corex-panel and owned descendants',
	},
	{
		style: 'corex-data',
		asset: ADMIN_FILES[ 1 ],
		owner: 'plugins/corex-config/src/Data/DataAdminScreen.php',
		scope: '.corex-data* owned screen',
	},
	{
		style: 'corex-insights',
		asset: ADMIN_FILES[ 2 ],
		owner: 'plugins/corex-config/src/Insights/InsightsScreen.php',
		scope: '.corex-insights and owned descendants',
	},
	{
		style: 'corex-captcha-admin',
		asset: ADMIN_FILES[ 3 ],
		owner: 'addons/corex-captcha/src/CaptchaServiceProvider.php',
		scope: '.corex-captcha-test on CoreX settings screen',
	},
];

const readJson = ( root, relative ) =>
	JSON.parse( readFileSync( path.join( root, relative ), 'utf8' ) );
const kebab = ( value ) =>
	value.replace( /[A-Z]/g, ( letter ) => `-${ letter.toLowerCase() }` );

export function generatedProperty( group, parts ) {
	if ( group === 'custom' ) {
		return `--wp--custom--${ parts.map( kebab ).join( '--' ) }`;
	}
	if ( group === 'layout' ) {
		return `--wp--style--global--${ parts.map( kebab ).join( '--' ) }`;
	}

	return `--wp--preset--${ group }--${ parts.map( kebab ).join( '--' ) }`;
}

const semanticRole = ( group, slug ) => {
	const roles = {
		ink: 'text',
		'ink-soft': 'text-muted',
		success: 'status-success',
		warning: 'status-warning',
		error: 'status-error',
		info: 'status-info',
	};

	return (
		roles[ slug ] || ( group === 'color' ? slug : `${ group }-${ slug }` )
	);
};

const definitionClassification = ( id, property ) => {
	if ( Object.hasOwn( LEGACY_TARGETS, property ) ) {
		return 'aliased';
	}

	return ADDED_DEFINITIONS.has( id ) ? 'added' : 'retained';
};

const addDefinition = ( definitions, input ) => {
	const classification = definitionClassification(
		input.id,
		input.generatedProperty
	);
	definitions.push( {
		id: input.id,
		group: input.group,
		source_path: input.sourcePath,
		generated_property: input.generatedProperty,
		semantic_role: input.semanticRole,
		default_mapping: String( input.defaultMapping ),
		dark_mapping: input.darkMapping ?? null,
		classification,
		replacement_id:
			classification === 'aliased'
				? LEGACY_TARGETS[ input.generatedProperty ]
				: null,
		introduced_version: classification === 'aliased' ? '0.28.0' : null,
		remove_after_version: classification === 'aliased' ? '0.29.0' : null,
		evidence_status: 'planned',
	} );
};

const customGroup = ( topLevel ) =>
	[ 'radius', 'focus', 'motion', 'z', 'border' ].includes( topLevel )
		? topLevel
		: 'layout';

const collectCustomDefinitions = (
	definitions,
	value,
	darkCustom,
	parts = []
) => {
	for ( const [ key, child ] of Object.entries( value || {} ) ) {
		const next = [ ...parts, key ];
		if ( child && typeof child === 'object' && ! Array.isArray( child ) ) {
			collectCustomDefinitions(
				definitions,
				child,
				darkCustom?.[ key ],
				next
			);
			continue;
		}

		const id = next.join( '.' );
		addDefinition( definitions, {
			id,
			group: customGroup( next[ 0 ] ),
			sourcePath: `theme/theme.json#settings.custom.${ id }`,
			generatedProperty: generatedProperty( 'custom', next ),
			semanticRole: id.replaceAll( '.', '-' ),
			defaultMapping: child,
			darkMapping: darkCustom?.[ key ] ?? null,
		} );
	}
};

const collectDefinitions = ( theme, dark ) => {
	const definitions = [];
	const darkPalette = Object.fromEntries(
		( dark.settings?.color?.palette || [] ).map( ( item ) => [
			item.slug,
			item.color,
		] )
	);
	const darkFamilies = Object.fromEntries(
		( dark.settings?.typography?.fontFamilies || [] ).map( ( item ) => [
			item.slug,
			item.fontFamily,
		] )
	);

	( theme.settings.color.palette || [] ).forEach( ( item, index ) => {
		addDefinition( definitions, {
			id: `color.${ item.slug }`,
			group: 'color',
			sourcePath: `theme/theme.json#settings.color.palette[${ index }]`,
			generatedProperty: generatedProperty( 'color', [ item.slug ] ),
			semanticRole: semanticRole( 'color', item.slug ),
			defaultMapping: item.color,
			darkMapping: darkPalette[ item.slug ] ?? null,
		} );
	} );
	( theme.settings.typography.fontFamilies || [] ).forEach(
		( item, index ) => {
			addDefinition( definitions, {
				id: `font-family.${ item.slug }`,
				group: 'font-family',
				sourcePath: `theme/theme.json#settings.typography.fontFamilies[${ index }]`,
				generatedProperty: generatedProperty( 'font-family', [
					item.slug,
				] ),
				semanticRole: semanticRole( 'font-family', item.slug ),
				defaultMapping: item.fontFamily,
				darkMapping: darkFamilies[ item.slug ] ?? null,
			} );
		}
	);
	( theme.settings.typography.fontSizes || [] ).forEach( ( item, index ) => {
		addDefinition( definitions, {
			id: `font-size.${ item.slug }`,
			group: 'font-size',
			sourcePath: `theme/theme.json#settings.typography.fontSizes[${ index }]`,
			generatedProperty: generatedProperty( 'font-size', [ item.slug ] ),
			semanticRole: semanticRole( 'font-size', item.slug ),
			defaultMapping: item.size,
		} );
	} );
	( theme.settings.spacing.spacingSizes || [] ).forEach( ( item, index ) => {
		addDefinition( definitions, {
			id: `spacing.${ item.slug }`,
			group: 'spacing',
			sourcePath: `theme/theme.json#settings.spacing.spacingSizes[${ index }]`,
			generatedProperty: generatedProperty( 'spacing', [ item.slug ] ),
			semanticRole: semanticRole( 'spacing', item.slug ),
			defaultMapping: item.size,
		} );
	} );
	( theme.settings.shadow.presets || [] ).forEach( ( item, index ) => {
		addDefinition( definitions, {
			id: `shadow.${ item.slug }`,
			group: 'shadow',
			sourcePath: `theme/theme.json#settings.shadow.presets[${ index }]`,
			generatedProperty: generatedProperty( 'shadow', [ item.slug ] ),
			semanticRole: semanticRole( 'shadow', item.slug ),
			defaultMapping: item.shadow,
		} );
	} );
	collectCustomDefinitions(
		definitions,
		theme.settings.custom,
		dark.settings?.custom
	);
	for ( const [ key, value ] of Object.entries(
		theme.settings.layout || {}
	) ) {
		addDefinition( definitions, {
			id: `layout.${ key }`,
			group: 'layout',
			sourcePath: `theme/theme.json#settings.layout.${ key }`,
			generatedProperty: generatedProperty( 'layout', [ key ] ),
			semanticRole: semanticRole( 'layout', key ),
			defaultMapping: value,
		} );
	}

	return definitions.sort( ( a, b ) => a.id.localeCompare( b.id ) );
};

const variableReferences = ( content ) => {
	const references = [];
	content.split( /\r?\n/ ).forEach( ( line, index ) => {
		const pattern =
			/var\((--(?:wp--(?:preset|custom)--|corex-)[a-zA-Z0-9_-]+)/g;
		let match;
		while ( ( match = pattern.exec( line ) ) ) {
			references.push( {
				property: match[ 1 ],
				line: index + 1,
				snippet: line.trim(),
			} );
		}
	} );

	return references;
};

const trackedFiles = ( root, roots = SOURCE_ROOTS ) =>
	execFileSync( 'git', [ '-C', root, 'ls-files', ...roots ], {
		encoding: 'utf8',
	} )
		.split( /\r?\n/ )
		.filter( Boolean )
		.map( ( file ) => file.replaceAll( '\\', '/' ) )
		.filter(
			( file ) =>
				SOURCE_EXTENSIONS.test( file ) && ! SKIPPED_PATH.test( file )
		)
		.sort();

const consumerOwner = ( file ) => {
	if ( file.startsWith( 'theme/' ) ) {
		return 'theme';
	}
	if ( file.startsWith( 'plugins/corex-core/' ) ) {
		return 'core-plugin';
	}
	if ( file.startsWith( 'plugins/corex-config/' ) ) {
		return 'config-plugin';
	}
	if ( file.startsWith( 'plugins/corex-blocks/' ) ) {
		return 'blocks-plugin';
	}
	if ( file.startsWith( 'plugins/corex-forms/' ) ) {
		return 'forms-plugin';
	}
	if ( file.startsWith( 'addons/corex-ui/' ) ) {
		return 'ui-addon';
	}
	if ( file.startsWith( 'addons/' ) ) {
		return 'other-addon';
	}
	if ( file.startsWith( 'packages/cli/' ) ) {
		return 'cli';
	}

	return 'other-addon';
};

const consumerSurface = ( file ) => {
	if (
		file.includes( 'corex-config/assets' ) ||
		file.includes( 'captcha-admin' )
	) {
		return 'admin';
	}

	return 'front-end';
};

const consumerResolution = ( definition ) => {
	if ( ! definition ) {
		return 'invalid';
	}

	return definition.classification === 'aliased' ? 'alias-required' : 'valid';
};

const directionContext = ( snippets ) => {
	if (
		/\b(?:left|right|margin-left|margin-right|padding-left|padding-right)\b/.test(
			snippets
		)
	) {
		return 'ltr-only';
	}
	if ( /\b(?:inline|block)\b/.test( snippets ) ) {
		return 'logical';
	}

	return 'not-applicable';
};

const collectConsumers = ( root, definitions ) => {
	const canonical = new Map(
		definitions.map( ( definition ) => [
			definition.generated_property,
			definition,
		] )
	);
	const consumers = [];

	for ( const file of trackedFiles( root ) ) {
		const grouped = new Map();
		for ( const reference of variableReferences(
			readFileSync( path.join( root, file ), 'utf8' )
		) ) {
			if ( ! grouped.has( reference.property ) ) {
				grouped.set( reference.property, { lines: [], snippets: [] } );
			}
			const group = grouped.get( reference.property );
			group.lines.push( reference.line );
			if (
				group.snippets.length < 3 &&
				! group.snippets.includes( reference.snippet )
			) {
				group.snippets.push( reference.snippet );
			}
		}

		for ( const [ property, group ] of grouped ) {
			const definition = canonical.get( property );
			const target = definition?.replacement_id || definition?.id || null;
			const snippets = group.snippets.join( ' | ' );
			const resolution = consumerResolution( definition );
			consumers.push( {
				path: file,
				selector_or_context: `lines ${ group.lines.join(
					', '
				) }: ${ snippets }`,
				property,
				owner: consumerOwner( file ),
				surface: consumerSurface( file ),
				direction_context: directionContext( snippets ),
				resolution,
				target_definition: target,
			} );
		}
	}

	return consumers.sort(
		( a, b ) =>
			a.path.localeCompare( b.path ) ||
			a.property.localeCompare( b.property )
	);
};

const replacementArray = ( items, required, valueField ) => {
	const declared = items.map( ( item ) => item.slug );
	return {
		declared_slugs: declared,
		missing_required_slugs: required.filter(
			( slug ) => ! declared.includes( slug )
		),
		complete: required.every( ( slug ) => declared.includes( slug ) ),
		mappings: items.map( ( item ) => ( {
			slug: item.slug,
			value: item[ valueField ],
		} ) ),
	};
};

const collectVariations = ( theme, variations ) => {
	const requiredColors = ( theme.settings.color.palette || [] ).map(
		( item ) => item.slug
	);
	const requiredFonts = ( theme.settings.typography.fontFamilies || [] ).map(
		( item ) => item.slug
	);

	return {
		generated_at: GENERATED_AT,
		canonical_source: 'theme/theme.json',
		required_palette_slugs: requiredColors,
		required_font_family_slugs: requiredFonts,
		variations: variations.map( ( { mode, file, json } ) => ( {
			mode,
			source_path: file,
			replacement_arrays: {
				palette: replacementArray(
					json.settings?.color?.palette || [],
					requiredColors,
					'color'
				),
				font_families: replacementArray(
					json.settings?.typography?.fontFamilies || [],
					requiredFonts,
					'fontFamily'
				),
			},
			style_references: [
				...new Set(
					variableReferences( JSON.stringify( json, null, 2 ) ).map(
						( item ) => item.property
					)
				),
			].sort(),
			status: 'planned',
		} ) ),
	};
};

const collectDocumentation = ( root ) => {
	const files = execFileSync(
		'git',
		[
			'-C',
			root,
			'ls-files',
			'theme',
			'plugins',
			'addons',
			'packages',
			'docs-app',
			'tests',
		],
		{ encoding: 'utf8' }
	)
		.split( /\r?\n/ )
		.filter( ( file ) => file.endsWith( '.md' ) )
		.sort();
	const documentation = [];

	for ( const file of files ) {
		const text = readFileSync( path.join( root, file ), 'utf8' );
		const lines = [];
		text.split( /\r?\n/ ).forEach( ( line, index ) => {
			if (
				/brand\.json|--wp--(?:preset|custom)--|theme\.json/.test( line )
			) {
				lines.push( index + 1 );
			}
		} );
		if ( lines.length === 0 ) {
			continue;
		}
		documentation.push( {
			path: file,
			lines,
			topics: [
				...( text.includes( 'brand.json' ) ? [ 'brand.json' ] : [] ),
				...( text.includes( '--wp--' )
					? [ 'generated-properties' ]
					: [] ),
				...( text.includes( 'theme.json' )
					? [ 'theme-authority' ]
					: [] ),
			],
		} );
	}

	return documentation;
};

const collectAdmin = ( root, consumers ) => {
	const fallbackChains = [];
	const rawValues = [];
	for ( const file of ADMIN_FILES ) {
		readFileSync( path.join( root, file ), 'utf8' )
			.split( /\r?\n/ )
			.forEach( ( line, index ) => {
				const fallbackPattern = /var\((--[^,\s)]+),\s*([^)]+)\)/g;
				let match;
				while ( ( match = fallbackPattern.exec( line ) ) ) {
					fallbackChains.push( {
						path: file,
						line: index + 1,
						property: match[ 1 ],
						fallback: match[ 2 ].trim(),
						context: line.trim(),
					} );
				}
				const values =
					line.match(
						/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|(?<![a-zA-Z0-9_-])(?:\d*\.)?\d+(?:px|rem|em|ms|s)\b/g
					) || [];
				for ( const value of values ) {
					const layout =
						/(?:grid-template|minmax|min-inline-size|inline-size|block-size|z-index|inset|line-height)/.test(
							line
						);
					rawValues.push( {
						path: file,
						line: index + 1,
						value,
						context: line.trim(),
						classification: layout
							? 'functional-layout-allowance'
							: 'admin-fallback-migration-candidate',
						target: 'scoped --corex-admin-* adapter or documented allowance',
					} );
				}
			} );
	}

	return {
		generated_at: GENERATED_AT,
		admin_files: ADMIN_FILES,
		enqueue_owners: ADMIN_OWNERS,
		fallback_chains: fallbackChains,
		raw_values: rawValues,
		legacy_references: consumers
			.filter( ( item ) => item.resolution !== 'valid' )
			.map( ( item ) => ( {
				legacy_property: item.property,
				target_definition: item.target_definition,
				resolution: item.resolution,
				path: item.path,
				context: item.selector_or_context,
			} ) ),
		adapter_status:
			'planned; corex-core registers shared handle, screen owners enqueue as dependency, never global or client-brand authority',
	};
};

const collectClassifications = ( definitions, consumers, root ) => {
	// Asset blocker status is derived from the provenance manifests so the
	// inventory reflects reality (and stays idempotent) rather than a hardcoded
	// "planned" string.
	const fontsReady = existsSync(
		path.join( root, 'theme/assets/fonts/manifest.json' )
	);
	const logosReady = existsSync(
		path.join(
			root,
			'plugins/corex-config/assets/brand/logo-manifest.json'
		)
	);
	const byClassification = ( classification ) =>
		definitions.filter(
			( item ) => item.classification === classification
		);
	const retained = byClassification( 'retained' ).map( ( item ) => ( {
		id: item.id,
		generated_property: item.generated_property,
		source_path: item.source_path,
		status: 'retained-pending-contract-tests',
	} ) );
	const presentAdded = byClassification( 'added' );
	const addedIds = new Set( presentAdded.map( ( item ) => item.id ) );
	const added = [
		...presentAdded.map( ( item ) => ( {
			id: item.id,
			rationale:
				'Approved Spec 057 semantic gap; exact value is governed by contract and accessibility evidence',
			status: 'implemented',
		} ) ),
		...[ ...ADDED_DEFINITIONS ]
			.filter( ( id ) => ! addedIds.has( id ) )
			.map( ( id ) => ( {
				id,
				rationale:
					'Approved Spec 057 semantic gap; exact value waits for contract tests and accessibility evidence',
				status: 'planned',
			} ) ),
	].sort( ( a, b ) => a.id.localeCompare( b.id ) );
	const consumersByProperty = new Map();
	for ( const consumer of consumers ) {
		if ( ! consumersByProperty.has( consumer.property ) ) {
			consumersByProperty.set( consumer.property, [] );
		}
		consumersByProperty.get( consumer.property ).push( consumer.path );
	}
	const aliased = Object.entries( LEGACY_TARGETS )
		.map( ( [ legacyProperty, targetDefinition ] ) => ( {
			legacy_property: legacyProperty,
			target_definition: targetDefinition,
			consumer_paths: [
				...new Set( consumersByProperty.get( legacyProperty ) || [] ),
			].sort(),
			introduced_version: '0.28.0',
			remove_after_version: '0.29.0',
			status: definitions.some(
				( item ) => item.generated_property === legacyProperty
			)
				? 'active'
				: 'planned',
		} ) )
		.sort( ( a, b ) =>
			a.legacy_property.localeCompare( b.legacy_property )
		);
	const migrationBatches = new Map();
	for ( const consumer of consumers.filter(
		( item ) => item.resolution !== 'valid'
	) ) {
		if ( ! migrationBatches.has( consumer.owner ) ) {
			migrationBatches.set( consumer.owner, {
				owner: consumer.owner,
				paths: [],
				properties: [],
			} );
		}
		const batch = migrationBatches.get( consumer.owner );
		if ( ! batch.paths.includes( consumer.path ) ) {
			batch.paths.push( consumer.path );
		}
		if ( ! batch.properties.includes( consumer.property ) ) {
			batch.properties.push( consumer.property );
		}
	}
	const migrated = [ ...migrationBatches.values() ].map( ( batch ) => ( {
		...batch,
		paths: batch.paths.sort(),
		properties: batch.properties.sort(),
	} ) );

	return {
		generated_at: GENERATED_AT,
		authority: 'theme/theme.json',
		summary: {
			definitions: definitions.length,
			consumer_records: consumers.length,
			retained: retained.length,
			added: added.length,
			aliased: aliased.length,
			migration_batches: migrated.length,
			deprecated: 0,
		},
		retained,
		added,
		aliased,
		migrated,
		deprecated: [],
		deprecation_policy:
			'Active compatibility aliases remain for at least one minor release and until first-party consumer count is zero.',
		compatibility_windows: {
			client_brand_lists:
				'replace wholesale; complete palette/font arrays required',
			aliases: 'one minor release minimum',
			rollback:
				'restore complete theme/style arrays and active aliases together',
		},
		blockers: {
			fonts: fontsReady
				? 'PASS: approved WOFF2 files and provenance recorded (T047-T049)'
				: 'BLOCKED until approved WOFF2 files and provenance are recorded (T047-T049)',
			logos: logosReady
				? 'PASS: owner-approved vector package and provenance recorded (T059-T064)'
				: 'BLOCKED until owner-approved vector package and provenance are recorded (T059-T064)',
		},
		evidence_status:
			fontsReady && logosReady ? 'implementation-in-progress' : 'planned',
	};
};

export function buildInventory( root ) {
	const theme = readJson( root, 'theme/theme.json' );
	const dark = readJson( root, 'theme/styles/dark.json' );
	const editorial = readJson( root, 'theme/styles/editorial.json' );
	const definitions = collectDefinitions( theme, dark );
	const consumers = collectConsumers( root, definitions );
	const variations = collectVariations( theme, [
		{ mode: 'dark', file: 'theme/styles/dark.json', json: dark },
		{
			mode: 'editorial',
			file: 'theme/styles/editorial.json',
			json: editorial,
		},
	] );

	return {
		'definitions.json': {
			generated_at: GENERATED_AT,
			canonical_source: 'theme/theme.json',
			count: definitions.length,
			definitions,
		},
		'variations.json': variations,
		'generated-properties.json': {
			generated_at: GENERATED_AT,
			count: definitions.length,
			properties: definitions.map( ( item ) => ( {
				id: item.id,
				source_path: item.source_path,
				generated_property: item.generated_property,
				unique: true,
				status: 'observed',
			} ) ),
		},
		'consumers.json': {
			generated_at: GENERATED_AT,
			scan_scope: SOURCE_ROOTS.map( ( item ) => `${ item }/` ),
			extensions: [ 'css', 'scss', 'json', 'php', 'js' ],
			excluded: [
				'untracked files',
				'ignored dependencies/build output',
				'docs-app/ (recorded in docs-and-brand.json)',
				'tests/ (recorded as fixtures)',
			],
			count: consumers.length,
			consumers,
		},
		'docs-and-brand.json': {
			generated_at: GENERATED_AT,
			actual_brand_json_files: trackedFiles( root, [
				'theme',
				'plugins',
				'addons',
				'packages',
			] ).filter(
				( file ) =>
					file === 'brand.json' || file.endsWith( '/brand.json' )
			),
			runtime_contract: {
				resolver: 'plugins/corex-core/src/Theme/BrandResolver.php',
				provider:
					'plugins/corex-core/src/Theme/ThemeServiceProvider.php',
				associative_maps: 'recursive-merge',
				lists: 'replace-wholesale',
				missing_file: 'defaults-intact',
				malformed_file: 'logged-and-defaults-intact',
			},
			documentation_references: collectDocumentation( root ),
			examples: [
				{
					path: 'docs-app/src/content/docs/guides/branding.md',
					lines: [ 22, 30 ],
					shape: 'incomplete-list',
					expected_result_under_spec_057: 'report-and-default',
					status: 'migration-required',
					note: 'The documented one-item palette replaces the full list under current resolver semantics.',
				},
			],
			fixtures: [
				{
					path: 'tests/Unit/Theme/BrandResolverTest.php',
					coverage: [
						'empty override',
						'recursive associative merge',
						'unknown associative key',
						'list replacement',
						'missing file',
						'malformed file',
					],
					status: 'existing',
				},
				{
					path: 'tests/Fixtures/Theme/brand/',
					coverage: [
						'complete-list',
						'incomplete-list',
						'malformed',
						'missing',
					],
					status: 'implemented-T016',
				},
			],
		},
		'admin-and-aliases.json': collectAdmin( root, consumers ),
		'classifications.json': collectClassifications(
			definitions,
			consumers,
			root
		),
	};
}

export function serializeInventory( inventory ) {
	return Object.fromEntries(
		Object.entries( inventory ).map( ( [ filename, value ] ) => [
			filename,
			`${ JSON.stringify( value, null, 2 ) }\n`,
		] )
	);
}

export function writeInventory( root ) {
	const output = serializeInventory( buildInventory( root ) );
	for ( const [ filename, content ] of Object.entries( output ) ) {
		writeFileSync( path.join( root, INVENTORY_PATH, filename ), content );
	}

	return Object.keys( output );
}

const isCli =
	path.basename( process.argv[ 1 ] ?? '' ) === 'generate-token-inventory.mjs';
if ( isCli ) {
	const root = path.resolve( path.dirname( process.argv[ 1 ] ), '..' );
	const files = writeInventory( root );
	process.stdout.write( `${ files.join( '\n' ) }\n` );
}
