import {
	insightsEndpoint,
	gradeTone,
	normalizeResult,
	normalizeRecommendations,
	normalizeWidgets,
} from '../insightsClient.js';

describe( 'insightsEndpoint', () => {
	it( 'joins root and path without duplicate or missing slashes', () => {
		expect(
			insightsEndpoint( 'https://x.test/corex/v1/insights/', '/run' )
		).toBe( 'https://x.test/corex/v1/insights/run' );
		expect( insightsEndpoint( 'https://x.test/corex/v1/insights' ) ).toBe(
			'https://x.test/corex/v1/insights'
		);
	} );
} );

describe( 'gradeTone', () => {
	it( 'maps grades to display tones and stays neutral for unknown grades', () => {
		expect( gradeTone( 'A' ) ).toBe( 'success' );
		expect( gradeTone( 'b' ) ).toBe( 'success' );
		expect( gradeTone( 'C' ) ).toBe( 'warning' );
		expect( gradeTone( 'D' ) ).toBe( 'critical' );
		expect( gradeTone( 'F' ) ).toBe( 'critical' );
		expect( gradeTone( '' ) ).toBe( 'neutral' );
		expect( gradeTone( '?' ) ).toBe( 'neutral' );
	} );
} );

describe( 'normalizeResult', () => {
	it( 'returns an honest not-run shape for an empty payload', () => {
		expect( normalizeResult( null ) ).toMatchObject( {
			ran: false,
			score: 0,
			grade: '',
			tone: 'neutral',
		} );
	} );

	it( 'normalizes a real run result and derives the grade tone', () => {
		const result = normalizeResult( {
			provider: 'performance',
			label: 'Performance',
			score: '50',
			grade: 'D',
			status: 'recommended',
			summary: 'Slow',
			recommendations: [ 'Compress images', 42 ],
			checkedAt: '123',
		} );

		expect( result ).toMatchObject( {
			ran: true,
			provider: 'performance',
			score: 50,
			grade: 'D',
			tone: 'critical',
			recommendations: [ 'Compress images', '42' ],
			checkedAt: 123,
		} );
	} );
} );

describe( 'normalizeRecommendations', () => {
	it( 'keeps only entries that carry recommendation text', () => {
		const out = normalizeRecommendations( [
			{
				provider: 'performance',
				label: 'Performance',
				grade: 'D',
				recommendations: [ 'Enable caching' ],
			},
			{
				provider: 'readiness',
				label: 'Readiness',
				grade: 'A',
				recommendations: [],
			},
		] );

		expect( out ).toHaveLength( 1 );
		expect( out[ 0 ] ).toMatchObject( {
			provider: 'performance',
			tone: 'critical',
			recommendations: [ 'Enable caching' ],
		} );
	} );

	it( 'tolerates a non-array payload', () => {
		expect( normalizeRecommendations( undefined ) ).toEqual( [] );
	} );
} );

describe( 'normalizeWidgets', () => {
	it( 'normalizes a runnable widget with rows and keeps its mount id', () => {
		const [ widget ] = normalizeWidgets( [
			{
				key: 'forms',
				title: 'Forms & Flows analytics',
				sub: 'Submissions · flows · routing',
				state: 'live',
				chip: 'Live',
				chipTone: 'success',
				mount: 'performance',
				rows: [
					{ label: 'Stored submissions', value: 58, tone: 'info' },
				],
			},
		] );

		expect( widget ).toMatchObject( {
			key: 'forms',
			state: 'live',
			mount: 'performance',
			alt: null,
		} );
		expect( widget.rows[ 0 ] ).toEqual( {
			label: 'Stored submissions',
			value: '58',
			tone: 'info',
		} );
	} );

	it( 'normalizes a disconnected widget alt call-to-action and tolerates non-arrays', () => {
		const [ widget ] = normalizeWidgets( [
			{
				key: 'cloudflare',
				state: 'disconnected',
				mount: null,
				alt: {
					title: 'Not connected',
					message: 'Add a token',
					ctaLabel: 'Open settings',
					ctaHref: 'settings',
				},
			},
		] );

		expect( widget.mount ).toBeNull();
		expect( widget.alt ).toMatchObject( {
			ctaHref: 'settings',
			ctaLabel: 'Open settings',
		} );
		expect( normalizeWidgets( undefined ) ).toEqual( [] );
	} );
} );
