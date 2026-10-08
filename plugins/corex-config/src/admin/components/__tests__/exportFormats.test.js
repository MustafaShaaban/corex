/**
 * The formats an export dialog offers (spec 103, FR-015, FR-019a).
 *
 * The server says which it can write where it runs. A dialog offers those, by the names a person
 * knows them by, and says before the request is sent when a PDF is asked for with too many records.
 */
import { offeredFormats, tooManyForPdf } from '../export/exportFormats.js';

describe( 'the formats offered', () => {
	it( 'are the ones the server can write, in its order, by name', () => {
		expect( offeredFormats( [ 'xlsx', 'csv', 'pdf' ] ) ).toEqual( [
			{ value: 'xlsx', label: 'Excel workbook (.xlsx)' },
			{ value: 'csv', label: 'CSV (.csv)' },
			{ value: 'pdf', label: 'PDF document (.pdf)' },
		] );
	} );

	it.each( [
		[ 'the server has not answered yet', undefined ],
		[ 'the server names none', [] ],
	] )( 'are a workbook and CSV when %s', ( _name, available ) => {
		expect(
			offeredFormats( available ).map( ( format ) => format.value )
		).toEqual( [ 'xlsx', 'csv' ] );
	} );
} );

describe( 'whether a PDF is asked for with too many records', () => {
	it.each( [
		[ 'yes for a PDF over the limit', 'pdf', 501, 500, true ],
		[ 'no for a PDF at the limit', 'pdf', 500, 500, false ],
		[ 'no for a workbook over the limit', 'xlsx', 501, 500, false ],
		[ 'no while the count is unknown', 'pdf', null, 500, false ],
		[ 'no where the server names no limit', 'pdf', 501, 0, false ],
	] )( '%s', ( _name, format, count, most, expected ) => {
		expect( tooManyForPdf( format, count, most ) ).toBe( expected );
	} );
} );
