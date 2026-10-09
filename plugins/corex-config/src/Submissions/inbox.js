import { __, _n, sprintf } from '@wordpress/i18n';

/** The two views of the inbox (spec 105): what is in it, and what was moved to the trash. */
export const VIEW_INBOX = 'inbox';
export const VIEW_TRASH = 'trash';

export const initialInboxState = {
	status: 'idle',
	// Whether the view shown has been answered at all. Until it has, nothing is known of it:
	// not that it is empty, not how many it holds.
	hasPage: false,
	// Whether the read that is out is for other rows than these (a filter, a page), and not
	// the same rows read again behind a change.
	asked: false,
	items: [],
	total: 0,
	page: 1,
	perPage: 25,
	selectedIds: [],
	error: '',
	message: '',
	// The submissions the last action moved to the trash: what "Undo" puts back.
	undo: [],
	// Whether this person may delete a submission for good, as the server says.
	mayDelete: false,
	// How many days the trash keeps a submission; 0 for until somebody deletes it.
	trashDays: 0,
	drawer: {
		open: false,
		id: 0,
		status: 'idle',
		record: null,
		error: '',
	},
};

export function inboxReducer( state, action ) {
	switch ( action.type ) {
		case 'loading':
			return {
				...state,
				status: 'loading',
				asked: Boolean( action.asked ),
				error: '',
				message: '',
				undo: [],
			};
		case 'loaded': {
			const page = normalizeInboxPage( action.page );
			const visible = new Set( page.items.map( ( item ) => item.id ) );
			return {
				...state,
				...page,
				status: 'ready',
				hasPage: true,
				selectedIds: state.selectedIds.filter( ( id ) =>
					visible.has( id )
				),
				error: '',
			};
		}
		case 'failed':
			return { ...state, status: 'error', error: action.message || '' };
		case 'selectionChanged':
			return { ...state, selectedIds: action.ids };
		case 'message':
			return {
				...state,
				message: action.message || '',
				undo: Array.isArray( action.undo ) ? action.undo : [],
				error: '',
			};
		case 'detailLoading':
			return {
				...state,
				drawer: {
					open: true,
					id: action.id,
					status: 'loading',
					record: null,
					error: '',
				},
			};
		case 'detailLoaded':
			return {
				...state,
				drawer: {
					...state.drawer,
					status: 'ready',
					record: action.record,
					error: '',
				},
			};
		case 'detailFailed':
			return {
				...state,
				drawer: {
					...state.drawer,
					status: 'error',
					error: action.message || '',
				},
			};
		// The inbox and its trash are two lists. What was read of one says nothing of the other.
		case 'viewChanged':
			return {
				...state,
				hasPage: false,
				items: [],
				total: 0,
				selectedIds: [],
				drawer: { ...initialInboxState.drawer },
			};
		case 'drawerClosed':
			return {
				...state,
				drawer: { ...initialInboxState.drawer },
			};
		default:
			return state;
	}
}

/**
 * The list's state as a loadable surface has one (spec 108): a placeholder until the view has
 * been answered, its rows kept and waiting while others are on their way, and an error in
 * their place only when there were never any. Rows read again behind a change to a submission
 * are not waiting: they are the same rows, and stay in reach. A request that fails after that is said above the
 * rows, which are still what the site last answered.
 *
 * @param {Object} state The inbox's state.
 * @return {string} `loading`, `refreshing`, `ready` or `error`.
 */
export function listStatus( state ) {
	if ( ! state.hasPage ) {
		return state.status === 'error' ? 'error' : 'loading';
	}

	return state.status === 'loading' && state.asked ? 'refreshing' : 'ready';
}

/**
 * The filters the inbox should open with, read from the address it was opened at.
 *
 * Forms & Flows links to "the submissions for this form", and a link that lands on an unfiltered
 * inbox is a control that does nothing. `corex_form` carries either a numeric flow id or
 * `slug:<slug>` for a form registered in code — the same two shapes the form filter itself sends,
 * so a deep link and a picked filter cannot mean different things.
 *
 * @param {string} url The current address.
 * @return {Object} Filter overrides to merge into the initial state.
 */
export function inboxFiltersFromUrl( url ) {
	let form = '';
	try {
		form =
			new URL( String( url ), 'http://localhost' ).searchParams.get(
				'corex_form'
			) || '';
	} catch {
		return {};
	}

	if ( ! /^(\d+|slug:[a-z0-9_-]+)$/i.test( form ) ) {
		return {};
	}

	return { flow: form };
}

/**
 * The one submission the inbox should open with its detail already showing, or 0.
 *
 * An assignment notification says "a submission needs your reply", and until this existed the only
 * link it could honestly offer was the unfiltered inbox — which hands somebody a list and asks them
 * to find the row the notification was already holding (spec 087, FR-014).
 *
 * Returns 0 rather than null for anything unparseable, so a caller tests one falsy value and a
 * hand-edited address opens the plain inbox instead of an error.
 *
 * @param {string} url The current address.
 * @return {number} The submission id to open, or 0.
 */
export function inboxSubmissionFromUrl( url ) {
	let id = '';
	try {
		id =
			new URL( String( url ), 'http://localhost' ).searchParams.get(
				'corex_submission'
			) || '';
	} catch {
		return 0;
	}

	return /^\d+$/.test( id ) ? Number( id ) : 0;
}

export function buildInboxUrl( base, filters ) {
	const params = new URLSearchParams();
	const values = [
		[ 'search', filters.search ],
		[ 'flow', filters.flow ],
		[ 'status', filters.status ],
		[ 'owner', filters.owner ],
		[ 'date_from', filters.dateFrom ],
		[ 'date_to', filters.dateTo ],
		[ 'include_test', filters.includeTest ? '1' : '' ],
		[ 'view', filters.view === VIEW_TRASH ? VIEW_TRASH : '' ],
		[ 'page', filters.page ],
		[ 'per_page', filters.perPage ],
	];
	values.forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			params.set( key, String( value ) );
		}
	} );

	return `${ base }?${ params.toString() }`;
}

export function normalizeInboxPage( payload = {} ) {
	const items = Array.isArray( payload.items )
		? payload.items
				.filter( ( item ) => Number( item?.id ) > 0 )
				.map( ( item ) => ( {
					...item,
					id: Number( item.id ),
					is_test: Boolean( item.is_test ),
					status: item.status || 'new',
					owner_type: item.owner_type || 'none',
					owner_key: item.owner_key || '',
				} ) )
		: [];

	return {
		items,
		total: Math.max( 0, Number( payload.total ) || 0 ),
		mayDelete: Boolean( payload.can_delete_permanently ),
		trashDays: Math.max( 0, Number( payload.trash_days ) || 0 ),
		page: Math.max( 1, Number( payload.page ) || 1 ),
		perPage: Math.max( 1, Number( payload.per_page ) || 25 ),
	};
}

export function toggleSubmission( selectedIds, id ) {
	const selected = new Set( selectedIds.map( Number ) );
	if ( selected.has( Number( id ) ) ) {
		selected.delete( Number( id ) );
	} else {
		selected.add( Number( id ) );
	}

	return [ ...selected ].sort( ( left, right ) => left - right );
}

export function buildExportPayload( options ) {
	return {
		scope: options.scope,
		selected_ids:
			options.scope === 'selected'
				? [ ...options.selectedIds ]
						.map( Number )
						.sort( ( a, b ) => a - b )
				: [],
		columns: [ ...options.columns ],
		query: options.scope === 'filtered' ? { ...options.filters } : {},
		include_test: Boolean( options.includeTest ),
		personal_data_acknowledged: Boolean( options.acknowledged ),
		format: options.format || 'csv',
		separator: options.separator || 'comma',
	};
}

/**
 * The bulk actions a view offers. In the trash a submission can only be restored: nothing else
 * may change it while it is there (spec 105, FR-006).
 *
 * @param {string}  view        `inbox` or `trash`.
 * @param {boolean} [mayDelete] Whether this person may delete a submission for good.
 * @return {Array<{value:string,label:string}>} The actions, in the order they are offered.
 */
export function bulkActionsFor( view, mayDelete = false ) {
	if ( view === VIEW_TRASH ) {
		const restore = { value: 'restore', label: __( 'Restore', 'corex' ) };

		return mayDelete
			? [
					restore,
					{
						value: 'delete',
						label: __( 'Delete permanently', 'corex' ),
					},
				]
			: [ restore ];
	}

	return [
		{ value: 'mark_read', label: __( 'Mark read', 'corex' ) },
		{ value: 'assign', label: __( 'Assign', 'corex' ) },
		{ value: 'mark_spam', label: __( 'Mark spam', 'corex' ) },
		{ value: 'archive', label: __( 'Archive', 'corex' ) },
		{ value: 'trash', label: __( 'Move to trash', 'corex' ) },
	];
}

/**
 * What a person is asked before submissions are moved to the trash or out of it: how many, and
 * what becomes of them (FR-002).
 *
 * @param {string} action `trash` or `restore`.
 * @param {number} count  How many submissions.
 * @return {{title:string,body:string,confirm:string}} The dialog's words.
 */
export function trashConfirmation( action, count ) {
	if ( action === 'restore' ) {
		return {
			title: __( 'Restore from the trash', 'corex' ),
			body: sprintf(
				/* translators: %d: number of submissions. */
				_n(
					'%d submission will go back to the inbox, as it was.',
					'%d submissions will go back to the inbox, as they were.',
					count,
					'corex'
				),
				count
			),
			confirm: __( 'Restore', 'corex' ),
		};
	}

	return {
		title: __( 'Move to the trash', 'corex' ),
		body: sprintf(
			/* translators: %d: number of submissions. */
			_n(
				'%d submission will leave the inbox. It can be restored from the trash.',
				'%d submissions will leave the inbox. They can be restored from the trash.',
				count,
				'corex'
			),
			count
		),
		confirm: __( 'Move to trash', 'corex' ),
	};
}

/**
 * What the inbox says once submissions have been moved to the trash or restored.
 *
 * @param {string} action `trash` or `restore`.
 * @param {number} count  How many submissions.
 * @return {string} The notice.
 */
export function trashNotice( action, count ) {
	return action === 'restore'
		? sprintf(
				/* translators: %d: number of submissions. */
				_n(
					'%d submission restored.',
					'%d submissions restored.',
					count,
					'corex'
				),
				count
			)
		: sprintf(
				/* translators: %d: number of submissions. */
				_n(
					'%d submission moved to the trash.',
					'%d submissions moved to the trash.',
					count,
					'corex'
				),
				count
			);
}

/**
 * How many submissions a view holds, in words, under the inbox's title.
 *
 * @param {string} view  `inbox` or `trash`.
 * @param {number} total How many the person may see there.
 * @return {string} The line.
 */
export function viewCount( view, total ) {
	return view === VIEW_TRASH
		? sprintf(
				/* translators: %d: number of submissions in the trash. */
				_n(
					'%d submission in the trash',
					'%d submissions in the trash',
					total,
					'corex'
				),
				total
			)
		: sprintf(
				/* translators: %d: number of submissions this user may see. */
				_n(
					'%d accessible submission',
					'%d accessible submissions',
					total,
					'corex'
				),
				total
			);
}

/**
 * What a person is told before submissions are deleted for good: how many, everything that goes
 * with them, that it cannot be undone, and where a copy may still be (spec 105, FR-012, FR-014).
 *
 * @param {number} count How many submissions.
 * @return {{title:string,body:string,removes:string[],exports:string,acknowledge:string,confirm:string}} The dialog's words.
 */
export function deleteConfirmation( count ) {
	return {
		title: __( 'Delete permanently', 'corex' ),
		body: sprintf(
			/* translators: %d: number of submissions. */
			_n(
				'%d submission will be deleted for good. This cannot be undone.',
				'%d submissions will be deleted for good. This cannot be undone.',
				count,
				'corex'
			),
			count
		),
		removes: [
			__(
				'The answers, with any hidden fields, campaign data and consent record',
				'corex'
			),
			__( 'Team notes and the history of what was done', 'corex' ),
			__( 'Files that were uploaded with it', 'corex' ),
			__(
				'The records and captured copies of emails sent about it',
				'corex'
			),
		],
		exports: __(
			'Export files made earlier may still hold what is deleted here. Each is kept 30 days from the day it was made, and can be deleted sooner under Export, Recent exports.',
			'corex'
		),
		acknowledge: __( 'I understand this cannot be undone', 'corex' ),
		confirm: sprintf(
			/* translators: %d: number of submissions. */
			_n(
				'Delete %d submission',
				'Delete %d submissions',
				count,
				'corex'
			),
			count
		),
	};
}

/**
 * What the inbox says once a permanent deletion has run. A submission is left in the trash when
 * a file uploaded with it could not be removed, or when it was no longer there (FR-015, FR-016).
 *
 * @param {number} deleted How many were deleted.
 * @param {number} failed  How many were not.
 * @return {string} The notice.
 */
export function deleteNotice( deleted, failed ) {
	const done = sprintf(
		/* translators: %d: number of submissions. */
		_n(
			'%d submission deleted for good.',
			'%d submissions deleted for good.',
			deleted,
			'corex'
		),
		deleted
	);
	if ( failed === 0 ) {
		return done;
	}

	return (
		done +
		' ' +
		sprintf(
			/* translators: %d: number of submissions that were not deleted. */
			_n(
				'%d was not deleted: a file uploaded with it could not be removed, or it is no longer in the trash.',
				'%d were not deleted: a file uploaded with them could not be removed, or they are no longer in the trash.',
				failed,
				'corex'
			),
			failed
		)
	);
}

/** Why somebody who manages the inbox is not offered a permanent delete (FR-010). */
export function cannotDeleteReason() {
	return __(
		'Deleting a submission for good needs a permission you do not have. An administrator can grant it under Access & Abilities, or delete it for you.',
		'corex'
	);
}

/**
 * How long the trash keeps a submission, in a line above the trash (spec 105, FR-004).
 *
 * @param {number} days The number of days; 0 for until somebody deletes it.
 * @return {string} The line.
 */
export function trashKeeps( days ) {
	return days > 0
		? sprintf(
				/* translators: %d: number of days. */
				_n(
					'A submission in the trash is deleted for good %d day after it was moved there.',
					'A submission in the trash is deleted for good %d days after it was moved there.',
					days,
					'corex'
				),
				days
			)
		: __(
				'A submission in the trash stays there until somebody restores it or deletes it for good.',
				'corex'
			);
}

/**
 * The day a trashed submission will be deleted for good: the trash's period counted from the
 * day it went in, which is how the server decides too.
 *
 * @param {string|null} trashedAt When it was moved to the trash.
 * @param {number}      days      How many days the trash keeps a submission; 0 for no limit.
 * @return {string} The moment, as an ISO date and time; '' when there is none to give.
 */
export function deletesAt( trashedAt, days ) {
	const from = trashedAt ? new Date( trashedAt ) : null;
	if ( ! from || Number.isNaN( from.getTime() ) || ! ( days > 0 ) ) {
		return '';
	}

	return new Date( from.getTime() + days * 86400000 ).toISOString();
}
