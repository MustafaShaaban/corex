/**
 * Jest: Newsletter Signup is drawn in place (spec 136, FR-018, User Story 2 scenario 2). The editor
 * draws the form NewsletterSignupRenderer prints; the heading, the button label and the consent
 * text are typed on the canvas, each showing the page's own default while empty; and the form
 * never submits in the editor.
 */
import { registerBlockType } from '@wordpress/blocks';
// React comes from the root's devDependencies; the Node build, as the parity helper uses.
// eslint-disable-next-line import/no-extraneous-dependencies
import { renderToStaticMarkup } from 'react-dom/server.node';
// eslint-disable-next-line import/no-extraneous-dependencies
import { createElement } from 'react';
import metadata from './block.json';

const RichTextMock = ( { tagName: Tag = 'div', className, value } ) =>
	createElement( Tag, { className }, value );

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: ( props ) => props || {},
		RichText: RichTextMock,
	} ),
	{ virtual: true }
);
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

import './index.js';

/**
 * Every element of a type in a React element tree, function components left unexpanded.
 *
 * @param {Object|Array<Object>}                         node A React element, or a list of them.
 * @param {string|import('react').ComponentType<Object>} type The tag or component to find.
 * @param {Array<Object>}                                out  What has been found.
 * @return {Array<Object>} The elements found.
 */
function collect( node, type, out = [] ) {
	if ( Array.isArray( node ) ) {
		node.forEach( ( child ) => collect( child, type, out ) );
		return out;
	}
	if ( ! node || typeof node !== 'object' ) {
		return out;
	}
	if ( node.type === type ) {
		out.push( node );
	}
	collect( node.props && node.props.children, type, out );
	return out;
}

const field = ( element, identifier ) =>
	collect( element, RichTextMock ).find(
		( rich ) => rich.props.identifier === identifier
	);

describe( 'newsletter signup block: drawn in place (FR-018)', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];
	const edit = ( attributes, setAttributes = jest.fn() ) =>
		config.edit( {
			attributes: {
				heading: '',
				buttonLabel: '',
				consentText: '',
				...attributes,
			},
			setAttributes,
		} );

	test( 'registers and saves nothing (dynamic)', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'FR-018: the heading, button label and consent text are typed on the canvas', () => {
		const setAttributes = jest.fn();
		const element = edit(
			{
				heading: 'Letters',
				buttonLabel: 'Join',
				consentText: 'Yes, write to me.',
			},
			setAttributes
		);
		// Each field as the page draws it: its tag and its class (the consent text has none).
		const typed = {
			heading: [ 'h2', 'corex-newsletter-signup__heading', 'Letters' ],
			// Inside the page's button: a browser types into no button that is itself editable.
			buttonLabel: [ 'span', undefined, 'Join' ],
			consentText: [ 'span', undefined, 'Yes, write to me.' ],
		};

		Object.entries( typed ).forEach(
			( [ attribute, [ tagName, className, value ] ] ) => {
				const rich = field( element, attribute );
				expect( rich.props.tagName ).toBe( tagName );
				expect( rich.props.className ).toBe( className );
				expect( rich.props.value ).toBe( value );
				rich.props.onChange( 'Typed' );
				expect( setAttributes ).toHaveBeenCalledWith( {
					[ attribute ]: 'Typed',
				} );
			}
		);
	} );

	test( 'FR-018: an empty field shows the words the page prints in its place', () => {
		const element = edit( {} );

		expect( field( element, 'heading' ).props.placeholder ).toBe(
			'Subscribe to our newsletter'
		);
		expect( field( element, 'buttonLabel' ).props.placeholder ).toBe(
			'Subscribe'
		);
		expect( field( element, 'consentText' ).props.placeholder ).toBe(
			'I agree to receive emails and can unsubscribe at any time.'
		);
	} );

	test( 'FR-018: the text is plain, kept as typed, since the page escapes it', () => {
		const setAttributes = jest.fn();
		const consent = field(
			edit( { consentText: 'Terms & conditions' }, setAttributes ),
			'consentText'
		);

		expect( consent.props.value ).toBe( 'Terms &amp; conditions' );
		expect( consent.props.allowedFormats ).toEqual( [] );
		consent.props.onChange( 'News &amp; offers' );
		expect( setAttributes ).toHaveBeenCalledWith( {
			consentText: 'News & offers',
		} );
	} );

	test( 'FR-018: the form does not submit in the editor', () => {
		const element = edit( {} );
		const [ form ] = collect( element, 'form' );
		const [ submit ] = collect( element, 'button' );
		const event = { preventDefault: jest.fn() };

		form.props.onSubmit( event );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect( submit.props.className ).toBe(
			'corex-newsletter-signup__submit'
		);
		expect( submit.props.type ).toBe( 'button' );
		expect( collect( submit, RichTextMock )[ 0 ].props.identifier ).toBe(
			'buttonLabel'
		);
		expect( form.props.action ).toBeUndefined();
	} );

	test( 'FR-018: a click on the consent text places the cursor and does not tick the box', () => {
		const element = edit( {} );
		const [ consent ] = collect( element, 'label' ).filter(
			( label ) =>
				label.props.className === 'corex-newsletter-signup__consent'
		);
		const event = { preventDefault: jest.fn() };

		consent.props.onClick( event );

		expect( event.preventDefault ).toHaveBeenCalled();
	} );

	test( 'draws without the server', () => {
		const markup = renderToStaticMarkup(
			createElement( config.edit, {
				attributes: { heading: 'Letters' },
				setAttributes: () => {},
			} )
		);

		expect( markup ).toContain( 'class="corex-newsletter-signup__form"' );
		expect( markup ).toContain( 'Letters' );
	} );
} );
