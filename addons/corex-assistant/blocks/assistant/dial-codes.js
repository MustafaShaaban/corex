/**
 * The country codes offered beside the hand-off's phone number (spec 129, User Story 2).
 *
 * Data only: ISO 3166 code to dial code, and a timezone to the country it belongs to. Names come from
 * the browser in the page's language (`Intl.DisplayNames`), so nothing here is translated, and the
 * list arrives with the chat, never with the page. A site chooses its default country and the
 * countries listed first (the `corex_assistant_phone_countries` filter); the rest follow by name.
 */

const DIAL = {
	AE: '+971',
	SA: '+966',
	EG: '+20',
	KW: '+965',
	QA: '+974',
	BH: '+973',
	OM: '+968',
	JO: '+962',
	LB: '+961',
	IQ: '+964',
	SY: '+963',
	PS: '+970',
	YE: '+967',
	SD: '+249',
	LY: '+218',
	TN: '+216',
	DZ: '+213',
	MA: '+212',
	MR: '+222',
	SO: '+252',
	DJ: '+253',
	KM: '+269',
	AU: '+61',
	AT: '+43',
	BE: '+32',
	BR: '+55',
	CA: '+1',
	CN: '+86',
	CY: '+357',
	CZ: '+420',
	DK: '+45',
	ET: '+251',
	FI: '+358',
	FR: '+33',
	DE: '+49',
	GR: '+30',
	HK: '+852',
	IN: '+91',
	ID: '+62',
	IE: '+353',
	IT: '+39',
	JP: '+81',
	KE: '+254',
	MY: '+60',
	MT: '+356',
	MX: '+52',
	NL: '+31',
	NZ: '+64',
	NG: '+234',
	NO: '+47',
	PK: '+92',
	PH: '+63',
	PL: '+48',
	PT: '+351',
	RO: '+40',
	RU: '+7',
	SG: '+65',
	ZA: '+27',
	KR: '+82',
	ES: '+34',
	SE: '+46',
	CH: '+41',
	TH: '+66',
	TR: '+90',
	UA: '+380',
	GB: '+44',
	US: '+1',
	VN: '+84',
	AR: '+54',
	BD: '+880',
	CL: '+56',
	CO: '+57',
	HU: '+36',
	IL: '+972',
	IR: '+98',
	LK: '+94',
	NP: '+977',
	PE: '+51',
	TW: '+886',
	AF: '+93',
};

/** One zone per country, two where a country spans zones or a legacy name is still reported. */
const ZONES = {
	'Asia/Dubai': 'AE',
	'Asia/Riyadh': 'SA',
	'Africa/Cairo': 'EG',
	'Asia/Kuwait': 'KW',
	'Asia/Qatar': 'QA',
	'Asia/Bahrain': 'BH',
	'Asia/Muscat': 'OM',
	'Asia/Amman': 'JO',
	'Asia/Beirut': 'LB',
	'Asia/Baghdad': 'IQ',
	'Asia/Damascus': 'SY',
	'Asia/Gaza': 'PS',
	'Asia/Hebron': 'PS',
	'Asia/Aden': 'YE',
	'Africa/Khartoum': 'SD',
	'Africa/Tripoli': 'LY',
	'Africa/Tunis': 'TN',
	'Africa/Algiers': 'DZ',
	'Africa/Casablanca': 'MA',
	'Africa/Nouakchott': 'MR',
	'Africa/Mogadishu': 'SO',
	'Africa/Djibouti': 'DJ',
	'Indian/Comoro': 'KM',
	'Europe/London': 'GB',
	'Europe/Dublin': 'IE',
	'Europe/Paris': 'FR',
	'Europe/Berlin': 'DE',
	'Europe/Madrid': 'ES',
	'Europe/Lisbon': 'PT',
	'Europe/Rome': 'IT',
	'Europe/Malta': 'MT',
	'Europe/Amsterdam': 'NL',
	'Europe/Brussels': 'BE',
	'Europe/Zurich': 'CH',
	'Europe/Vienna': 'AT',
	'Europe/Prague': 'CZ',
	'Europe/Warsaw': 'PL',
	'Europe/Bucharest': 'RO',
	'Europe/Athens': 'GR',
	'Europe/Stockholm': 'SE',
	'Europe/Oslo': 'NO',
	'Europe/Copenhagen': 'DK',
	'Europe/Helsinki': 'FI',
	'Europe/Kyiv': 'UA',
	'Europe/Kiev': 'UA',
	'Europe/Istanbul': 'TR',
	'Europe/Moscow': 'RU',
	'Europe/Budapest': 'HU',
	'Asia/Nicosia': 'CY',
	'America/New_York': 'US',
	'America/Chicago': 'US',
	'America/Denver': 'US',
	'America/Los_Angeles': 'US',
	'America/Toronto': 'CA',
	'America/Vancouver': 'CA',
	'America/Mexico_City': 'MX',
	'America/Sao_Paulo': 'BR',
	'America/Argentina/Buenos_Aires': 'AR',
	'America/Bogota': 'CO',
	'America/Lima': 'PE',
	'America/Santiago': 'CL',
	'Asia/Karachi': 'PK',
	'Asia/Kolkata': 'IN',
	'Asia/Calcutta': 'IN',
	'Asia/Dhaka': 'BD',
	'Asia/Colombo': 'LK',
	'Asia/Kathmandu': 'NP',
	'Asia/Kabul': 'AF',
	'Asia/Tehran': 'IR',
	'Asia/Jerusalem': 'IL',
	'Asia/Shanghai': 'CN',
	'Asia/Hong_Kong': 'HK',
	'Asia/Taipei': 'TW',
	'Asia/Tokyo': 'JP',
	'Asia/Seoul': 'KR',
	'Asia/Singapore': 'SG',
	'Asia/Kuala_Lumpur': 'MY',
	'Asia/Jakarta': 'ID',
	'Asia/Bangkok': 'TH',
	'Asia/Manila': 'PH',
	'Asia/Ho_Chi_Minh': 'VN',
	'Australia/Sydney': 'AU',
	'Australia/Melbourne': 'AU',
	'Pacific/Auckland': 'NZ',
	'Africa/Lagos': 'NG',
	'Africa/Nairobi': 'KE',
	'Africa/Addis_Ababa': 'ET',
	'Africa/Johannesburg': 'ZA',
};

function regionNames( locale ) {
	try {
		return new Intl.DisplayNames( [ locale ], { type: 'region' } );
	} catch {
		return null;
	}
}

/**
 * The options in the order the picker shows them: the site's first countries, then the rest by name.
 *
 * @param {string}   locale the page's language
 * @param {string[]} first  ISO codes the site lists first
 * @return {{iso: string, dial: string, label: string}[]} the options
 */
export function countryOptions( locale, first = [] ) {
	const names = regionNames( locale );
	const option = ( iso ) => {
		const name = names?.of( iso ) || iso;
		return {
			iso,
			dial: DIAL[ iso ],
			label: `${ name } (${ DIAL[ iso ] })`,
		};
	};
	const lead = first.filter( ( iso ) => DIAL[ iso ] );
	const rest = Object.keys( DIAL )
		.filter( ( iso ) => ! lead.includes( iso ) )
		.map( option )
		.sort( ( a, b ) => a.label.localeCompare( b.label, locale ) );
	return [ ...lead.map( option ), ...rest ];
}

/**
 * The country to preset: the visitor's (from their clock, then their browser's language), else the
 * site's default, else the country of the site's own timezone, else the first listed.
 *
 * Timezone first: it is the one country signal a browser gives away for free (no network call, no
 * permission), and it is right on any device that set its own clock. Language is the fallback because
 * it so often is not a place: someone in Dubai on an English laptop reports `en-US`.
 *
 * @param {Object}   site
 * @param {string}   site.country  the site's default ISO code, or ''
 * @param {string}   site.siteZone the site's timezone setting
 * @param {string[]} site.first    the countries the site lists first
 * @return {string} an ISO code that has a dial code
 */
export function presetCountry( {
	country = '',
	siteZone = '',
	first = [],
} = {} ) {
	const known = ( iso ) => ( iso && DIAL[ iso ] ? iso : '' );

	try {
		const fromZone = known(
			ZONES[ Intl.DateTimeFormat().resolvedOptions().timeZone ]
		);
		if ( fromZone ) {
			return fromZone;
		}
	} catch {
		// Intl is unavailable or refused; the language hint below is still worth trying.
	}

	const tags = window.navigator?.languages?.length
		? window.navigator.languages
		: [ window.navigator?.language ];
	for ( const tag of tags ) {
		// The region subtag of e.g. `ar-AE`. A bare `ar` names no country and is skipped.
		const region = known(
			String( tag || '' )
				.split( '-' )[ 1 ]
				?.toUpperCase()
		);
		if ( region ) {
			return region;
		}
	}

	return (
		known( country ) ||
		known( ZONES[ siteZone ] ) ||
		known( first[ 0 ] ) ||
		'US'
	);
}
