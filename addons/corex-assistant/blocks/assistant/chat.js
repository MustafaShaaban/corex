/**
 * The assistant's chat (spec 129), loaded by view.js on first use.
 *
 * The conversation lives in this tab's session storage, so it follows the visitor from page to page
 * and is gone when the tab closes; the server keeps none of it. Replies come from
 * `corex/v1/assistant`; when the assistant cannot help, or the visitor asks for a person, the
 * hand-off form (handoff.js) opens inside the chat, if the site has the forms engine.
 */

import { el, icon, paragraphs, parseJson, sameSite } from './dom';
import { handoffForm } from './handoff';
import './chat.scss';

const MAX_CHARS = 600;
const TRANSCRIPT_CHARS = 6000;
const INPUT_MAX_HEIGHT = 120;

/** What the server reads of a conversation (`Conversation::MAX_MESSAGES`), and its body limit, with room. */
const HISTORY_MESSAGES = 12;
const HISTORY_BYTES = 15000;

/**
 * A message cut to the characters the server keeps, by character as PHP's `mb_substr` counts, so an
 * emoji is never cut in half.
 *
 * @param {string} text
 * @return {string} at most MAX_CHARS characters
 */
function cut( text ) {
	return Array.from( String( text ) ).slice( 0, MAX_CHARS ).join( '' );
}

/**
 * The size of text in UTF-8, as the server counts a request's body.
 *
 * @param {string} text
 * @return {number} bytes
 */
function byteLength( text ) {
	return new window.Blob( [ text ] ).size;
}

/**
 * The conversation as it is sent: the newest messages the server reads, each cut as it cuts them, and
 * the oldest dropped while the request would pass its size limit. A long chat would otherwise grow past
 * the limit and be refused on every question after.
 *
 * @param {Array} items the conversation as the chat keeps it
 * @return {Array} the messages to send
 */
export function historyOf( items ) {
	const messages = items
		.filter( ( item ) => item.role === 'user' || item.role === 'assistant' )
		.slice( -HISTORY_MESSAGES )
		.map( ( { role, content } ) => ( {
			role,
			content: cut( content ),
		} ) );
	while (
		messages.length > 1 &&
		byteLength( JSON.stringify( messages ) ) > HISTORY_BYTES
	) {
		messages.shift();
	}
	return messages;
}

export function readConfig( root ) {
	const data = root.dataset;
	return {
		locale: data.locale || 'en',
		endpoint: data.endpoint || '',
		form: data.form || '',
		nonce: data.nonce || '',
		mark: data.mark || '',
		country: data.country || '',
		countriesFirst: ( data.countriesFirst || '' )
			.split( ',' )
			.filter( Boolean ),
		siteZone: data.siteZone || '',
		strings: parseJson( data.strings, {} ),
	};
}

/**
 * The conversation, kept in session storage per language.
 *
 * @param {string} locale
 * @return {{load: () => Array, save: (items: Array) => void}} the store
 */
function conversationStore( locale ) {
	const key = `corex-assistant-chat-${ locale }`;
	return {
		load() {
			try {
				const saved = JSON.parse(
					window.sessionStorage.getItem( key ) || 'null'
				);
				return saved && Array.isArray( saved.items ) ? saved.items : [];
			} catch {
				return [];
			}
		},
		save( items ) {
			try {
				window.sessionStorage.setItem(
					key,
					JSON.stringify( { items } )
				);
			} catch {
				// Private mode or a full store: the chat still works, it just will not follow the visitor.
			}
		},
	};
}

/**
 * Asks the endpoint.
 *
 * @param {Object} config
 * @param {Array}  messages the visitor's and the assistant's, oldest first
 * @return {Promise<{status: number, reply: ?Object}>} the reply null when nothing usable came back
 */
async function requestReply( config, messages ) {
	try {
		const response = await window.fetch( config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify( { locale: config.locale, messages } ),
		} );
		return { status: response.status, reply: await response.json() };
	} catch {
		return { status: 0, reply: null };
	}
}

function avatar( config ) {
	const image = config.mark
		? el( 'img', { src: config.mark, alt: '', width: '24', height: '24' } )
		: el( 'span', {
				class: 'corex-assistant__glyph',
				html: icon( 'bubble' ),
			} );
	return el(
		'span',
		{ class: 'corex-assistant__avatar', 'aria-hidden': 'true' },
		[ image ]
	);
}

function frame( config, t ) {
	const parts = {
		reset: el( 'button', {
			type: 'button',
			class: 'corex-assistant__icon',
			'aria-label': t( 'newChat' ),
			title: t( 'newChat' ),
			html: icon( 'reset' ),
		} ),
		close: el( 'button', {
			type: 'button',
			class: 'corex-assistant__icon',
			'aria-label': t( 'close' ),
			title: t( 'close' ),
			html: icon( 'close' ),
		} ),
		log: el( 'div', {
			class: 'corex-assistant__log',
			role: 'log',
			'aria-live': 'polite',
			'aria-relevant': 'additions',
			tabindex: '0',
		} ),
		input: el( 'textarea', {
			class: 'corex-assistant__input',
			rows: '1',
			maxlength: String( MAX_CHARS ),
			placeholder: t( 'placeholder' ),
			'aria-label': t( 'placeholder' ),
			dir: 'auto',
		} ),
		send: el( 'button', {
			type: 'submit',
			class: 'corex-assistant__send',
			'aria-label': t( 'send' ),
			html: icon( 'send' ),
		} ),
		human: config.form
			? el( 'button', {
					type: 'button',
					class: 'corex-assistant__human',
					text: t( 'human' ),
				} )
			: null,
		typing: el(
			'div',
			{
				class: 'corex-assistant__typing',
				role: 'status',
				'aria-label': t( 'typing' ),
			},
			[ el( 'i' ), el( 'i' ), el( 'i' ) ]
		),
	};
	parts.composer = el(
		'form',
		{ class: 'corex-assistant__compose', novalidate: true },
		[
			el( 'div', { class: 'corex-assistant__field' }, [
				parts.input,
				parts.send,
			] ),
		]
	);
	parts.head = el( 'div', { class: 'corex-assistant__head' }, [
		avatar( config ),
		el( 'div', { class: 'corex-assistant__who' }, [
			el( 'strong', { text: t( 'name' ) } ),
			el( 'span', { text: t( 'status' ) } ),
		] ),
		parts.reset,
		parts.close,
	] );
	parts.foot = el( 'div', { class: 'corex-assistant__foot' }, [
		parts.composer,
		el( 'p', { class: 'corex-assistant__note', text: t( 'note' ) } ),
		...( parts.human ? [ parts.human ] : [] ),
	] );
	return parts;
}

/**
 * One line of the conversation: the visitor's, the assistant's (with its link chips), a note or the
 * confirmation.
 *
 * @param {Object} item
 * @return {HTMLElement} the bubble
 */
function bubble( item ) {
	if ( item.role === 'user' ) {
		return el(
			'div',
			{
				class: 'corex-assistant__msg corex-assistant__msg--me',
				dir: 'auto',
			},
			paragraphs( item.content )
		);
	}
	if ( item.role === 'done' ) {
		return el(
			'div',
			{
				class: 'corex-assistant__msg corex-assistant__msg--bot corex-assistant__msg--done',
			},
			[
				el( 'span', {
					class: 'corex-assistant__tick',
					html: icon( 'check' ),
				} ),
				el( 'p', { text: item.content } ),
			]
		);
	}
	const kind = item.role === 'note' ? ' corex-assistant__msg--note' : '';
	const node = el(
		'div',
		{
			class: `corex-assistant__msg corex-assistant__msg--bot${ kind }`,
			dir: 'auto',
		},
		paragraphs( item.content )
	);
	const links = ( item.links || [] ).filter( ( link ) =>
		sameSite( link.url )
	);
	if ( links.length ) {
		node.append(
			el(
				'div',
				{ class: 'corex-assistant__links' },
				links.map( ( link ) =>
					el(
						'a',
						{
							class: 'corex-assistant__link',
							href: link.url,
							html: icon( 'link' ),
						},
						[ document.createTextNode( link.label ) ]
					)
				)
			)
		);
	}
	return node;
}

/**
 * The conversation as the team reads it, the newest part when it is long.
 *
 * @param {Array} items
 * @return {string} the transcript
 */
export function transcriptOf( items ) {
	return items
		.filter( ( item ) => item.role === 'user' || item.role === 'assistant' )
		.map(
			( item ) =>
				`${ item.role === 'user' ? 'Visitor' : 'Assistant' }: ${ item.content }`
		)
		.join( '\n' )
		.slice( -TRANSCRIPT_CHARS );
}

export function mountChat( root, panel, { onClose } ) {
	const config = readConfig( root );
	const t = ( key ) => config.strings[ key ] || '';
	const store = conversationStore( config.locale );
	const ui = frame( config, t );
	let items = store.load();
	let waiting = false;

	panel.replaceChildren( ui.head, ui.log, ui.foot );

	const node = ( item ) =>
		item.role === 'handoff'
			? handoffForm( {
					config,
					t,
					transcript: () => transcriptOf( items ),
					onSent: confirmSent,
				} )
			: bubble( item );

	const suggestions = () =>
		el(
			'div',
			{ class: 'corex-assistant__suggest' },
			( config.strings.suggestions || [] ).map( ( question ) => {
				const chip = el( 'button', {
					type: 'button',
					class: 'corex-assistant__chip',
					text: question,
				} );
				chip.addEventListener( 'click', () => ask( question ) );
				return chip;
			} )
		);

	function render() {
		ui.log.replaceChildren(
			bubble( { role: 'assistant', content: t( 'welcome' ) } )
		);
		if ( ! items.some( ( item ) => item.role === 'user' ) ) {
			ui.log.append( suggestions() );
		}
		ui.log.append( ...items.map( node ) );
		ui.log.scrollTop = ui.log.scrollHeight;
	}

	function push( item ) {
		items.push( item );
		store.save( items );
		ui.log.querySelector( '.corex-assistant__suggest' )?.remove();
		const added = node( item );
		ui.log.append( added );
		// A form is read from its title down; everything else follows the latest line.
		ui.log.scrollTop =
			item.role === 'handoff'
				? Math.max( 0, added.offsetTop - ui.log.offsetTop - 12 )
				: ui.log.scrollHeight;
	}

	function offerTeam() {
		if (
			config.form &&
			! items.some(
				( item ) => item.role === 'handoff' || item.role === 'done'
			)
		) {
			push( { role: 'handoff' } );
		}
	}

	function confirmSent( name ) {
		items = items.filter( ( item ) => item.role !== 'handoff' );
		items.push( {
			role: 'done',
			content: t( 'done' ).replace( '%s', name ),
		} );
		store.save( items );
		render();
		ui.input.focus();
	}

	function showReply( status, reply ) {
		if ( status === 403 ) {
			push( { role: 'note', content: t( 'expired' ) } );
		} else if ( status !== 200 || ! reply ) {
			push( { role: 'note', content: t( 'error' ) } );
		} else if ( reply.busy ) {
			push( { role: 'note', content: t( 'busy' ) } );
			offerTeam();
		} else {
			push( {
				role: 'assistant',
				content: reply.reply,
				links: reply.links || [],
			} );
			if ( reply.team ) {
				offerTeam();
			}
		}
	}

	async function ask( question ) {
		const content = cut( String( question ).trim() );
		if ( ! content || waiting ) {
			return;
		}
		waiting = true;
		ui.send.disabled = true;
		push( { role: 'user', content } );
		ui.log.append( ui.typing );
		ui.log.scrollTop = ui.log.scrollHeight;

		const history = historyOf( items );
		const { status, reply } = await requestReply( config, history );

		ui.typing.remove();
		waiting = false;
		ui.send.disabled = false;
		showReply( status, reply );
	}

	// Enter sends, Shift+Enter starts a new line; the box grows to about four lines.
	ui.composer.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		const question = ui.input.value;
		ui.input.value = '';
		ui.input.style.height = '';
		ask( question );
	} );
	ui.input.addEventListener( 'keydown', ( event ) => {
		if (
			event.key === 'Enter' &&
			! event.shiftKey &&
			! event.isComposing
		) {
			event.preventDefault();
			ui.composer.requestSubmit();
		}
	} );
	ui.input.addEventListener( 'input', () => {
		ui.input.style.height = '';
		ui.input.style.height = `${ Math.min( ui.input.scrollHeight, INPUT_MAX_HEIGHT ) }px`;
	} );
	ui.human?.addEventListener( 'click', () => {
		offerTeam();
		ui.log.querySelector( '.corex-assistant__handoff input' )?.focus();
	} );
	ui.reset.addEventListener( 'click', () => {
		items = [];
		store.save( items );
		render();
		ui.input.focus();
	} );
	ui.close.addEventListener( 'click', onClose );
	panel.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			onClose();
		}
	} );

	render();

	return { focus: () => ui.input.focus() };
}
