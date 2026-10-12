/**
 * Keeps the reply somebody is writing as their draft (spec 106, FR-006).
 *
 * Written down on the server after a pause in writing, so that it is there on another visit
 * and on another machine; and at once when the pane is left with something not yet written
 * down. It asks for nothing while nothing has changed, so opening a submission and reading it
 * writes nothing.
 *
 * A reply that has gone, or was discarded, is no longer a draft, and nothing here may make it
 * one again. Three things see to that:
 *
 * - its requests go one at a time, in the order they were made, so a discard reaches the
 *   server after the save that was already on its way, and not before it;
 * - nothing is written down while the reply is being sent, on leaving the pane either;
 * - an answer that comes back after the draft ended is about a draft that is over, and is
 *   not read.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { hasWords } from '../../admin/components/replyHtml.js';

/** How long writing has to stop before what is written is written down, in milliseconds. */
export const PAUSE_BEFORE_SAVING = 1500;

export const NOTHING_WRITTEN = { subject: '', body: '' };

const same = ( one, other ) =>
	one.subject === other.subject && one.body === other.body;

const saysNothing = ( reply ) =>
	reply.subject.trim() === '' && ! hasWords( reply.body );

/**
 * @param {?Object} stored The submission's `reply_draft`, or null.
 * @return {{subject:string,body:string}} What the box starts with.
 */
export function draftOf( stored ) {
	return stored
		? { subject: stored.subject || '', body: stored.body || '' }
		: NOTHING_WRITTEN;
}

/**
 * @param {Object}  options
 * @param {number}  options.id     The submission.
 * @param {?Object} options.stored Its `reply_draft` as it was when the pane opened, or null.
 * @param {Object}  options.reply  What is in the box now: `{ subject, body }`.
 * @param {Object}  options.inbox  The inbox's requests.
 * @param {boolean} options.held   True while the reply is being sent.
 * @return {{state:string,hold:Function,forget:Function,discard:Function}} `state` is `none`, `restored`, `saved`, `unsaved` (it could not be written down) or `undiscarded` (it could not be removed).
 */
export function useReplyDraft( { id, stored, reply, inbox, held } ) {
	const [ state, setState ] = useState( stored ? 'restored' : 'none' );
	// What the server holds, as far as is known; and what is on its way to it.
	const kept = useRef( draftOf( stored ) );
	const asked = useRef( null );
	// Goes up each time the draft ends: an answer from before that is not about this one.
	const turn = useRef( 0 );
	const requests = useRef( Promise.resolve() );
	const waiting = useRef( null );
	// A save was still out when the reply was sent, and may reach the server after it.
	const crossed = useRef( false );
	// Read when a timer fires or the pane is left, which is later than the render that set them.
	const latest = useRef( { reply, inbox, held } );
	latest.current = { reply, inbox, held };

	const writtenDown = ( written ) =>
		same( written, kept.current ) ||
		( asked.current !== null && same( written, asked.current ) );

	const write = ( written ) => {
		const mine = turn.current;
		asked.current = written;
		requests.current = requests.current.then( async () => {
			const over = () => mine !== turn.current;
			if ( over() || latest.current.held ) {
				asked.current = null;
				return;
			}
			// A request that could not be made at all is one that was not kept.
			const answer = await latest.current.inbox
				.keepReplyDraft( id, written )
				.catch( () => ( { ok: false, draft: null } ) );
			if ( asked.current === written ) {
				asked.current = null;
			}
			if ( over() ) {
				return;
			}
			if ( ! answer.ok ) {
				setState( 'unsaved' );
				return;
			}
			kept.current = written;
			// The server says what it kept. Emptied, there is no draft of nothing; with
			// words, nothing kept is a draft that was not saved.
			if ( answer.draft ) {
				setState( 'saved' );
			} else {
				setState( saysNothing( written ) ? 'none' : 'unsaved' );
			}
		} );
	};

	useEffect( () => {
		if ( held || writtenDown( reply ) ) {
			return undefined;
		}
		waiting.current = setTimeout(
			() => write( latest.current.reply ),
			PAUSE_BEFORE_SAVING
		);

		return () => clearTimeout( waiting.current );
		// `write` and `writtenDown` read refs only: what is written, and whether a reply
		// is out, are what this follows.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ id, reply, held ] );

	// Left with something not yet written down: the pane closed, or another submission opened.
	useEffect(
		() => () => {
			const { reply: written, held: sending } = latest.current;
			if ( ! sending && ! writtenDown( written ) ) {
				write( written );
			}
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ id ]
	);

	const ended = () => {
		clearTimeout( waiting.current );
		turn.current += 1;
	};

	return {
		state,
		// The reply is about to be sent. Told here and not learned from the next render: its
		// answer can be back before there is one.
		hold: () => {
			clearTimeout( waiting.current );
			crossed.current = asked.current !== null;
		},
		// The reply has gone, and the server discarded its draft as it went.
		forget: () => {
			ended();
			kept.current = NOTHING_WRITTEN;
			setState( 'none' );
			if ( crossed.current ) {
				crossed.current = false;
				requests.current = requests.current.then( () =>
					latest.current.inbox
						.discardReplyDraft( id )
						.catch( () => false )
				);
			}
		},
		discard: async () => {
			ended();
			let done = false;
			requests.current = requests.current.then( async () => {
				done = await latest.current.inbox
					.discardReplyDraft( id )
					.catch( () => false );
			} );
			await requests.current;
			if ( done ) {
				kept.current = NOTHING_WRITTEN;
			}
			setState( done ? 'none' : 'undiscarded' );

			return done;
		},
	};
}
