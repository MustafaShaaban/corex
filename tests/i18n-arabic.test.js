/**
 * CoreX's Arabic catalogue tools (spec 121, US-NFR-02, DECISIONS #466).
 *
 * The catalogue is the shipped source of every Arabic string; these hold what the tools promise:
 * a translation that would break `sprintf()` is refused, the compiled `.mo` answers what the `.po`
 * says, and a script's translations carry the strings that script asks for.
 */
const path = require( 'node:path' );

const root = path.resolve( __dirname, '..' );
const tools = require( path.join( root, 'scripts/i18n-arabic.mjs' ) );

const entry = ( fields ) => ( {
	comments: [],
	refs: [],
	flags: [],
	extracted: [],
	ctx: null,
	plural: null,
	...fields,
} );

/** Reads one translation back out of a compiled `.mo`, as gettext would. */
function lookUp( mo, key ) {
	const count = mo.readUInt32LE( 8 );
	const originals = mo.readUInt32LE( 12 );
	const translations = mo.readUInt32LE( 16 );

	for ( let index = 0; index < count; index++ ) {
		const length = mo.readUInt32LE( originals + index * 8 );
		const offset = mo.readUInt32LE( originals + index * 8 + 4 );

		if ( mo.toString( 'utf8', offset, offset + length ).split( '\0' )[ 0 ] === key ) {
			const at = translations + index * 8;
			return mo.toString( 'utf8', mo.readUInt32LE( at + 4 ), mo.readUInt32LE( at + 4 ) + mo.readUInt32LE( at ) );
		}
	}

	return null;
}

describe( 'The Arabic catalogue tools', () => {
	test( 'read a PO file and write it back unchanged in meaning', () => {
		const source =
			'msgid ""\nmsgstr ""\n"Language: ar\\n"\n\n' +
			'#: plugins/a.php\nmsgctxt "verb"\nmsgid "Save \\"draft\\""\nmsgstr "حفظ \\"المسودة\\""\n\n' +
			'msgid "%d item"\nmsgid_plural "%d items"\nmsgstr[0] "لا عناصر"\nmsgstr[1] "عنصر واحد"\n';
		const parsed = tools.parsePo( source );

		expect( parsed.header ).toBe( 'Language: ar\n' );
		expect( parsed.entries[ 0 ] ).toMatchObject( {
			ctx: 'verb',
			id: 'Save "draft"',
			str: [ 'حفظ "المسودة"' ],
			refs: [ 'plugins/a.php' ],
		} );
		expect( tools.parsePo( tools.serializePo( parsed ) ) ).toEqual( parsed );
	} );

	test.each( [
		[ 'a placeholder dropped', 'Saved %s.', 'تم الحفظ.' ],
		[ 'a placeholder added', 'Saved.', 'تم حفظ %s.' ],
		[ 'a tag renamed', 'Read <a>the guide</a>.', 'اقرأ <b>الدليل</b>.' ],
		[ 'a named slot renamed', 'Hello {name}', 'مرحبًا {user}' ],
	] )( 'refuse a translation with %s', ( _, id, translation ) => {
		expect(
			tools.problemsOf( entry( { id, str: [ translation ] } ) )
		).not.toEqual( [] );
	} );

	test( 'accept a translation that moves placeholders and keeps the literal percent sign', () => {
		expect(
			tools.problemsOf(
				entry( {
					id: '%1$s of %2$s, %3$s%% done',
					str: [ '%3$s%% منجز، %1$s من %2$s' ],
				} )
			)
		).toEqual( [] );
	} );

	test( 'let a plural form for one leave the number out, never add one', () => {
		const plural = ( forms ) =>
			tools.problemsOf(
				entry( { id: '%d file', plural: '%d files', str: forms } )
			);

		expect( plural( [ 'لا ملفات', 'ملف واحد', 'ملفان', '%d ملفات', '%d ملفًا', '%d ملف' ] ) ).toEqual( [] );
		expect( plural( [ 'لا ملفات', 'ملف %s', 'ملفان', '%d ملفات', '%d ملفًا', '%d ملف' ] ) ).not.toEqual( [] );
	} );

	test( 'compile a .mo that answers each original, with its context and plural forms', () => {
		const mo = tools.compileMo( {
			header: 'Language: ar\n',
			entries: [
				entry( { id: 'Settings', str: [ 'الإعدادات' ] } ),
				entry( { ctx: 'verb', id: 'Post', str: [ 'نشر' ] } ),
				entry( { id: '%d file', plural: '%d files', str: [ 'a', 'b', 'c', 'd', 'e', 'f' ] } ),
				entry( { id: 'Untranslated', str: [ '' ] } ),
			],
		} );

		expect( mo.readUInt32LE( 0 ) ).toBe( 0x950412de );
		expect( lookUp( mo, 'Settings' ) ).toBe( 'الإعدادات' );
		expect( lookUp( mo, 'verb\u0004Post' ) ).toBe( 'نشر' );
		expect( lookUp( mo, '%d file' ) ).toBe( 'a\0b\0c\0d\0e\0f' );
		expect( lookUp( mo, 'Untranslated' ) ).toBeNull();
	} );

	test( 'give a script the translations of the strings it asks for, and none for a script that asks for none', () => {
		const entries = [
			entry( { id: 'Save', str: [ 'حفظ' ] } ),
			entry( { id: 'Delete "%s"', str: [ 'حذف "%s"' ] } ),
			entry( { id: 'Not here', str: [ 'ليس هنا' ] } ),
		];
		const data = tools.scriptTranslationsOf(
			'(0,r.__)("Save","corex");(0,r.__)("Delete \\"%s\\"","corex")',
			entries,
			'Plural-Forms: nplurals=6; plural=(n == 0 ? 0 : 5);\n'
		);

		expect( Object.keys( data.locale_data.messages ) ).toEqual( [ '', 'Save', 'Delete "%s"' ] );
		expect( data.locale_data.messages[ '' ][ 'plural-forms' ] ).toBe( 'nplurals=6; plural=(n == 0 ? 0 : 5);' );
		expect( tools.scriptTranslationsOf( 'console.log("Save")', entries, '' ) ).toBeNull();
		expect( tools.scriptFileName( 'build/inbox.js' ) ).toMatch( /^corex-ar-[0-9a-f]{32}\.json$/ );
	} );

	test( 'find no broken translation in the shipped catalogue, and a .mo compiled from it', () => {
		expect( tools.checkCatalogue( root, null ).problems ).toEqual( [] );
	} );
} );
