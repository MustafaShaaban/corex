/**
 * NotificationItem — the shared item shown by both the screen and the header drawer (spec 074,
 * FR-4.4/FR-4.9).
 *
 * The defect these exist to hold down: **read is not resolved**. v0.35.0 filtered "Requires
 * attention" on the actor's unread state, so reading a production-readiness blocker took it off the
 * attention list while the blocker was still true. The server decides the view now
 * ({@see NotificationView}, covered by NotificationViewTest.php); what is asserted here is that the
 * rendered item says the same thing — that looking at something never changes what it says about
 * itself.
 *
 * The rest is the anatomy the old item did not have at all: severity, source, environment, when,
 * how many times, the condition state, and only the controls this actor may actually use.
 *
 * No @testing-library in this repo, so the component is driven through a real jsdom root — the same
 * approach as admin/__tests__/corexSelect.test.js.
 */
import { createRoot } from '@wordpress/element';
// React is supplied by wp-scripts as a peer of @wordpress/element and is deliberately not a direct
// dependency; act() is only re-exported from 'react'.
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import NotificationItem from '../NotificationItem.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../../tests/Support/adminDateTimeConfig.js';

/** Fixed so the relative-time assertions describe a clock rather than the day the suite runs. */
const NOW = Date.parse( '2026-07-27T12:00:00.000Z' );

/**
 * An action exactly as `WpNotificationRepository::present()` serialises one — all four fields,
 * `label_key` and `label` both. Written as a factory so no test can quietly narrow it.
 *
 * @param {Object} overrides Fields to change for one case.
 * @return {Object} One serialised action.
 */
function serverAction( overrides = {} ) {
	return {
		label_key: 'notifications.submission.new.action',
		label: 'Open the Submission Inbox',
		url: 'https://acme.test/wp-admin/admin.php?page=corex-submissions&corex_form=7',
		ability: 'corex_manage_submissions',
		...overrides,
	};
}

function notification( overrides = {} ) {
	const { user_state: userState, rendered, ...rest } = overrides;

	return {
		id: 42,
		category: 'submissions',
		severity: 'warning',
		source_module: 'corex-forms',
		environment: 'production',
		occurrences: 1,
		latest_occurred_at: '2026-07-27T11:30:00.000Z',
		action: null,
		can_resolve: true,
		rendered: {
			title: 'A contact form submission was not delivered',
			body: 'The mail server refused the message.',
			...rendered,
		},
		user_state: {
			read: false,
			status: 'unread',
			view: 'action_needed',
			needs_action: true,
			...userState,
		},
		...rest,
	};
}

let container;
let root;

function mount( props = {} ) {
	act( () => {
		root.render(
			<NotificationItem item={ notification() } { ...props } />
		);
	} );
}

const article = () => container.querySelector( '.corex-notification' );
const textOf = ( selector ) =>
	container.querySelector( selector )?.textContent?.trim() ?? null;
const buttons = () => [ ...container.querySelectorAll( 'button' ) ];
const buttonNamed = ( name ) =>
	buttons().find( ( candidate ) => candidate.textContent.trim() === name ) ??
	null;

function click( element ) {
	act( () => {
		element.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } )
		);
	} );
}

beforeAll( () => {
	// React 18 only treats act() as authoritative when the environment opts in; without this every
	// update warns and @wordpress/jest-console fails the test on the warning.
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	jest.spyOn( Date, 'now' ).mockReturnValue( NOW );
	// The item renders its date through the shared contract now, which reads the boundary payload
	// `CorexAdminAssets` localizes onto every CoreX screen. Without it the component correctly
	// says "not recorded" rather than inventing a date — right behaviour, confusing test failure.
	installDateTimeConfig();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	removeDateTimeConfig();
	act( () => root.unmount() );
	container.remove();
	jest.restoreAllMocks();
} );

describe( 'NotificationItem', () => {
	describe( 'read is not resolved', () => {
		it( 'still says the condition needs action after it has been read', () => {
			// The regression this whole spec turns on. Reading is a fact about the person; needing
			// action is a fact about the condition. Only the unread badge may differ between these.
			mount( {
				item: notification( {
					user_state: {
						read: true,
						status: 'read',
						needs_action: true,
					},
				} ),
			} );

			expect( textOf( '.corex-notification__condition' ) ).toBe(
				'Still needs action'
			);
			expect(
				container.querySelector( '.corex-notification__unread' )
			).toBeNull();
			expect( article().className ).toContain( 'needs-action' );
			expect( article().className ).toContain( 'is-read' );
		} );

		it( 'marks an unread item that needs action as both', () => {
			mount();

			expect( textOf( '.corex-notification__condition' ) ).toBe(
				'Still needs action'
			);
			expect( textOf( '.corex-notification__unread' ) ).toBe( 'Unread' );
			expect( article().className ).toContain( 'is-unread' );
		} );

		it( 'says no action is needed when the record asks nothing, read or not', () => {
			mount( {
				item: notification( {
					severity: 'information',
					user_state: {
						needs_action: false,
						read: false,
						status: 'unread',
					},
				} ),
			} );

			expect( textOf( '.corex-notification__condition' ) ).toBe(
				'No action needed'
			);
			expect( article().className ).not.toContain( 'needs-action' );
		} );
	} );

	describe( 'the condition state wins over the action state', () => {
		it.each( [
			[ 'resolved', 'Resolved' ],
			[ 'dismissed', 'Dismissed' ],
			[ 'expired', 'Expired' ],
			[ 'snoozed', 'Snoozed' ],
		] )(
			'names a %s condition rather than repeating that it needs action',
			( status, label ) => {
				// needs_action is deliberately left true: the server would not send that combination,
				// and if it ever did the closing status is the honest thing to show.
				mount( {
					item: notification( {
						user_state: { status, read: true, needs_action: true },
					} ),
				} );

				expect( textOf( '.corex-notification__condition' ) ).toBe(
					label
				);
			}
		);
	} );

	describe( 'controls', () => {
		it( 'offers no way to change a condition that is already over', () => {
			mount( {
				item: notification( {
					user_state: {
						status: 'resolved',
						read: true,
						needs_action: false,
					},
				} ),
				actions: {
					markRead: jest.fn(),
					markUnread: jest.fn(),
					snooze: jest.fn(),
					dismiss: jest.fn(),
					resolve: jest.fn(),
				},
			} );

			expect( buttons() ).toHaveLength( 0 );
		} );

		it( 'hides resolve from an actor who may not resolve rather than offering it', () => {
			// Hiding beats a control that answers with a permission error (FR-4.6).
			mount( {
				item: notification( { can_resolve: false } ),
				actions: { resolve: jest.fn(), dismiss: jest.fn() },
			} );

			expect( buttonNamed( 'Mark resolved' ) ).toBeNull();
			expect( buttonNamed( 'Dismiss' ) ).not.toBeNull();
		} );

		it( 'shows mark-read only while unread and mark-unread only once read', () => {
			const actions = { markRead: jest.fn(), markUnread: jest.fn() };

			mount( { actions } );
			expect( buttonNamed( 'Mark read' ) ).not.toBeNull();
			expect( buttonNamed( 'Mark unread' ) ).toBeNull();

			act( () =>
				root.render(
					<NotificationItem
						item={ notification( {
							user_state: { read: true, status: 'read' },
						} ) }
						actions={ actions }
					/>
				)
			);
			expect( buttonNamed( 'Mark read' ) ).toBeNull();
			expect( buttonNamed( 'Mark unread' ) ).not.toBeNull();
		} );

		it.each( [
			[ 'Mark read', 'markRead' ],
			[ 'Snooze for a day', 'snooze' ],
			[ 'Dismiss', 'dismiss' ],
			[ 'Mark resolved', 'resolve' ],
		] )(
			'reports %s against the notification it belongs to',
			( label, handler ) => {
				const actions = {
					markRead: jest.fn(),
					snooze: jest.fn(),
					dismiss: jest.fn(),
					resolve: jest.fn(),
				};
				mount( { actions } );

				click( buttonNamed( label ) );

				expect( actions[ handler ] ).toHaveBeenCalledWith( 42 );
			}
		);

		/**
		 * The payload here is the shape `WpNotificationRepository::present()` actually emits —
		 * `label_key` *and* `label`, because the key is a translation key and nothing in the
		 * pipeline resolves one.
		 *
		 * This test used to pass a bare `{ url, label }`, which the server had never sent, so it
		 * went green while every real notification rendered a generic "Open" (spec 087, FR-010).
		 * A fixture invented to match the component is not coverage of the component.
		 */
		it( 'renders the primary action as a link to where the work is done', () => {
			mount( { item: notification( { action: serverAction() } ) } );

			const link = container.querySelector(
				'.corex-notification__actions a'
			);
			expect( link.getAttribute( 'href' ) ).toContain(
				'page=corex-submissions'
			);
			expect( link.textContent.trim() ).toBe(
				'Open the Submission Inbox'
			);
		} );

		it( 'falls back to "Open" only when the action carries no label', () => {
			mount( {
				item: notification( {
					action: { ...serverAction(), label: '' },
				} ),
			} );

			expect(
				container
					.querySelector( '.corex-notification__actions a' )
					.textContent.trim()
			).toBe( 'Open' );
		} );

		/**
		 * The title is the other way to the same place. Deliberately not the whole card: the card
		 * holds Mark read, Snooze and Dismiss, and nesting controls inside a link is a trap for
		 * both a pointer and a screen reader (FR-013).
		 */
		it( 'makes the title a second route to the same destination', () => {
			mount( { item: notification( { action: serverAction() } ) } );

			const titleLink = container.querySelector(
				'.corex-notification__title a'
			);
			expect( titleLink.getAttribute( 'href' ) ).toBe(
				serverAction().url
			);
			expect( titleLink.textContent.trim() ).toBe(
				'A contact form submission was not delivered'
			);
			expect(
				container.querySelectorAll( '.corex-notification__title a' )
			).toHaveLength( 1 );
		} );

		it( 'never nests a control inside the title link', () => {
			mount( {
				item: notification( { action: serverAction() } ),
				actions: { markRead: jest.fn(), dismiss: jest.fn() },
			} );

			expect(
				container.querySelectorAll(
					'.corex-notification__title a button'
				)
			).toHaveLength( 0 );
			expect( buttons().length ).toBeGreaterThan( 0 );
		} );

		/**
		 * A notification the viewer cannot act on arrives with no `action` at all — the server
		 * withholds it rather than trusting the client to hide it. The title must then be plain
		 * text, not a link to nowhere.
		 */
		it( 'leaves the title as text when there is no action', () => {
			mount( { item: notification( { action: null } ) } );

			expect(
				container.querySelector( '.corex-notification__title a' )
			).toBeNull();
			expect(
				container.querySelector( '.corex-notification__actions a' )
			).toBeNull();
			expect( textOf( '.corex-notification__title' ) ).toContain(
				'A contact form submission was not delivered'
			);
		} );
	} );

	describe( 'drawer and screen parity', () => {
		it( 'tells the drawer the same story as the screen, with only the controls dropped', () => {
			// The drawer used to show a shorter, differently-worded version of the same record, so
			// "what does this want from me" depended on where you were looking.
			const actions = {
				markRead: jest.fn(),
				dismiss: jest.fn(),
				resolve: jest.fn(),
			};

			mount( { actions } );
			const full = textOf( '.corex-notification__body' );
			expect( buttons().length ).toBeGreaterThan( 0 );

			act( () =>
				root.render(
					<NotificationItem
						item={ notification() }
						actions={ actions }
						compact
					/>
				)
			);

			expect( textOf( '.corex-notification__body' ) ).toBe( full );
			expect( buttons() ).toHaveLength( 0 );
		} );

		it( 'keeps the primary action in the drawer, because that is the point of glancing', () => {
			mount( {
				item: notification( {
					action: {
						url: 'https://acme.test/wp-admin/',
						label: 'Open the inbox',
					},
				} ),
				actions: { markRead: jest.fn() },
				compact: true,
			} );

			expect(
				container.querySelector( '.corex-notification__actions a' )
			).not.toBeNull();
			expect( buttons() ).toHaveLength( 0 );
		} );
	} );

	describe( 'what the record already knows', () => {
		it( 'states severity, source, and environment in words', () => {
			mount();

			expect( textOf( '.corex-notification__severity' ) ).toBe(
				'Warning'
			);
			expect( textOf( '.corex-notification__source' ) ).toBe( 'Forms' );
			expect( textOf( '.corex-notification__environment' ) ).toBe(
				'production'
			);
		} );

		it( 'falls back to the raw value rather than dropping an unknown severity or source', () => {
			mount( {
				item: notification( {
					severity: 'catastrophe',
					source_module: 'acme-addon',
				} ),
			} );

			expect( textOf( '.corex-notification__severity' ) ).toBe(
				'catastrophe'
			);
			expect( textOf( '.corex-notification__source' ) ).toBe(
				'acme-addon'
			);
		} );

		it.each( [
			[ 1, null ],
			[ 2, '2 times' ],
			[ 9, '9 times' ],
		] )( 'reports %i occurrences as %s', ( occurrences, expected ) => {
			// One occurrence is the unremarkable case and saying "1 time" is noise.
			mount( { item: notification( { occurrences } ) } );

			expect( textOf( '.corex-notification__occurrences' ) ).toBe(
				expected
			);
		} );

		it( 'reuses the navigation glyph for the category and hides it from assistive tech', () => {
			mount();
			const icon = container.querySelector( '.corex-notification__icon' );

			// The same mark that sits next to Submissions in the rail, so a glyph means one thing.
			expect( icon.className ).toContain(
				'corex-admin__nav-icon--submissions'
			);
			expect( icon.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		} );

		it( 'falls back to the generic bell for a category with no glyph of its own', () => {
			mount( { item: notification( { category: 'something-new' } ) } );

			expect(
				container.querySelector( '.corex-notification__icon' ).className
			).toContain( 'corex-admin__nav-icon--notifications' );
		} );

		it( 'carries the exact timestamp alongside the relative one', () => {
			// Relative time is what you want at a glance; the exact value is what you want when
			// working out whether two things happened together.
			mount();
			const time = container.querySelector( '.corex-notification__time' );

			// `+00:00` rather than the `.000Z` this asserted before spec 076. Both name the same
			// instant, but the shared contract emits the same string PHP's `gmdate(DATE_ATOM)`
			// does — the two halves of the product now agree character for character, which is
			// the whole point of the parity fixture.
			expect( time.getAttribute( 'datetime' ) ).toBe(
				'2026-07-27T11:30:00+00:00'
			);
			expect( time.textContent.trim() ).toBe( '30 minutes ago' );
		} );

		it( 'puts the exact date in text, not behind a hover', () => {
			// The exact value used to live only in a `title` attribute, which a touch user cannot
			// open and a screen reader does not reliably announce (spec 076, FR-013).
			mount();

			expect( textOf( '.corex-time__exact' ) ).toMatch(
				/\d{1,2} \w+ \d{4} at \d{1,2}:\d{2} [AP]M/
			);
			expect( container.querySelector( '[title]' ) ).toBeNull();
		} );

		it.each( [
			[ '2026-07-27T11:59:30.000Z', 'Just now' ],
			[ '2026-07-27T11:59:00.000Z', '1 minute ago' ],
			[ '2026-07-27T11:00:00.000Z', '1 hour ago' ],
			[ '2026-07-26T12:00:00.000Z', '1 day ago' ],
			[ '2026-07-24T12:00:00.000Z', '3 days ago' ],
		] )( 'describes %s as %s', ( occurredAt, expected ) => {
			mount( {
				item: notification( { latest_occurred_at: occurredAt } ),
			} );

			expect( textOf( '.corex-notification__time' ) ).toBe( expected );
		} );

		it( 'says so plainly rather than NaN when the timestamp is unusable', () => {
			// This asserted an empty string before spec 076. An empty cell is not an answer — it
			// reads as a rendering bug and collapses the row it sits in (FR-018). The field now
			// says which kind of nothing it means.
			mount( {
				item: notification( { latest_occurred_at: 'not-a-date' } ),
			} );

			expect( textOf( '.corex-notification__time' ) ).toBe(
				'Time not recorded'
			);
			expect( container.querySelector( 'time' ) ).toBeNull();
		} );

		it( 'renders an item whose body the server never filled without inventing one', () => {
			// Built by hand rather than through the fixture: the point is the absence of `rendered`,
			// which a builder that merges defaults into it cannot express.
			const bare = notification();
			delete bare.rendered;

			mount( { item: bare } );

			expect( article() ).not.toBeNull();
			expect( textOf( '.corex-notification__title' ) ).toBe( '' );
			expect( textOf( '.corex-notification__text' ) ).toBe( '' );
		} );
	} );
} );
