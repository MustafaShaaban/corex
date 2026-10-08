/**
 * What the detail pane says about one submission, as plain functions (spec 103, US5).
 *
 * The pane used to print what it was handed: answers under their field keys, an owner as a type
 * and a key, three sections saying "No data recorded." These turn the record into what a person
 * reads, in one place, tested without a browser.
 */
import { __, sprintf } from '@wordpress/i18n';
import { DELIVERY_META, STATUS_LABELS } from '../badges.js';

/** Question types whose answer is a way to reach the sender. */
const EMAIL_TYPES = [ 'email' ];
const PHONE_TYPES = [ 'tel', 'phone' ];

/** The value of the assignment control that means nobody. */
export const UNASSIGNED = 'none';

/**
 * The answers, in the form's order and under the form's wording (FR-041).
 *
 * An answer the form no longer asks for is kept, after the ones it does, under its stored key.
 * A question nobody answered is left out: the pane shows what was said.
 *
 * @param {Object} record One submission, as the detail route answers it.
 * @return {Array<{key:string,label:string,value:*}>} One entry per answer.
 */
export function answersOf( record ) {
	const values = record.values || {};
	const asked = record.questions || [];
	const known = new Set( asked.map( ( question ) => question.key ) );

	return [
		...asked
			.filter( ( question ) => question.key in values )
			.map( ( question ) => ( {
				key: question.key,
				label: question.label || question.key,
				value: values[ question.key ],
			} ) ),
		...Object.keys( values )
			.filter( ( key ) => ! known.has( key ) )
			.map( ( key ) => ( { key, label: key, value: values[ key ] } ) ),
	];
}

function answerOfType( record, types ) {
	const question = ( record.questions || [] ).find( ( item ) =>
		types.includes( item.type )
	);
	const value = question ? ( record.values || {} )[ question.key ] : '';

	return typeof value === 'string' ? value.trim() : '';
}

/**
 * How to reach the sender, when the form asked (FR-040).
 *
 * @param {Object} record One submission.
 * @return {{email:string,phone:string,phoneHref:string}} Empty strings for what the form did not ask.
 */
export function contactOf( record ) {
	const email =
		( record.submitter_email || '' ).trim() ||
		answerOfType( record, EMAIL_TYPES );
	const phone = answerOfType( record, PHONE_TYPES );

	return {
		email,
		phone,
		// A `tel:` link takes digits and a leading plus; what the visitor typed is what is shown.
		phoneHref: phone.replace( /[^\d+]/g, '' ),
	};
}

/**
 * The owner a submission has, as the one value of the assignment control.
 *
 * @param {Object} record One submission.
 * @return {string} `none`, or `<type>:<key>`.
 */
export function ownerValue( record ) {
	const type = record.owner_type || UNASSIGNED;

	return type === UNASSIGNED
		? UNASSIGNED
		: `${ type }:${ record.owner_key || '' }`;
}

function ownerLabel( record ) {
	if ( record.owner_name ) {
		return record.owner_name;
	}
	if ( record.owner_type === 'flow_owner' ) {
		return __( 'The form’s owner', 'corex' );
	}

	return record.owner_key
		? `${ record.owner_type }: ${ record.owner_key }`
		: String( record.owner_type );
}

/**
 * Who a submission can be assigned to: nobody, or one of the people who can manage submissions
 * (FR-042). An owner it already has that is not one of those people — a team, a role — stays in
 * the list, so opening the pane never makes an assignment look lost.
 *
 * @param {Object} record One submission.
 * @return {Array<{value:string,label:string}>} The options of the assignment control.
 */
export function ownerOptions( record ) {
	const people = ( record.owners || [] ).map( ( person ) => ( {
		value: `user:${ person.key }`,
		label: person.label,
	} ) );
	const current = ownerValue( record );
	const options = [
		{ value: UNASSIGNED, label: __( 'Unassigned', 'corex' ) },
		...people,
	];

	if ( ! options.some( ( option ) => option.value === current ) ) {
		options.push( { value: current, label: ownerLabel( record ) } );
	}

	return options;
}

/**
 * The assignment the workflow route takes, from a value of the assignment control.
 *
 * @param {string} value `none`, or `<type>:<key>`.
 * @return {{owner_type:string,owner_key:string}} What to send.
 */
export function assignmentOf( value ) {
	if ( value === UNASSIGNED ) {
		return { owner_type: UNASSIGNED, owner_key: '' };
	}
	const at = value.indexOf( ':' );

	return {
		owner_type: value.slice( 0, at ),
		owner_key: value.slice( at + 1 ),
	};
}

/**
 * The technical details that were recorded, each as a named group (FR-044). A group with nothing
 * in it is left out; when none is left, the pane says so on one line and draws no section.
 *
 * @param {Object} record One submission.
 * @return {Array<{id:string,title:string,entries:Array<[string,*]>}>} The groups that hold something.
 */
export function technicalGroups( record ) {
	return [
		{
			id: 'hidden_metadata',
			title: __( 'Hidden fields', 'corex' ),
			value: record.hidden_metadata,
		},
		{
			id: 'utm',
			title: __( 'Campaign data', 'corex' ),
			value: record.utm,
		},
		{
			id: 'consent_snapshot',
			title: __( 'Consent record', 'corex' ),
			value: record.consent_snapshot,
		},
	]
		.map( ( { id, title, value } ) => ( {
			id,
			title,
			entries: Object.entries( value || {} ),
		} ) )
		.filter( ( group ) => group.entries.length > 0 );
}

/**
 * Why no delivery is shown for a submission, in words (FR-043).
 *
 * "Delivery unavailable" was the answer for every submission of a form defined in code. It is
 * not a failure and nothing is wrong with the site: those forms send their notification without
 * recording it here.
 *
 * @param {Object} record One submission.
 * @return {string} The explanation, or '' when a delivery was recorded.
 */
export function untrackedReason( record ) {
	const status = record.delivery?.status || 'unavailable';

	if ( status !== 'unavailable' ) {
		return '';
	}

	return Number( record.flow_version_id ) > 0
		? __( 'No notification was recorded for this submission.', 'corex' )
		: __(
				'This form is defined in code. It sends its notification without recording it here, so there is nothing to show.',
				'corex'
			);
}

/**
 * The line under the sender's name: which form, and which submission.
 *
 * @param {Object} record One submission.
 * @return {string} For example "Lead form · #41".
 */
export function identityOf( record ) {
	return sprintf(
		/* translators: 1: a form's name. 2: a submission's number. */
		__( '%1$s · #%2$d', 'corex' ),
		record.flow || record.form || __( 'Unknown form', 'corex' ),
		record.id
	);
}

/** What the history says for the events that carry nothing but the fact that they happened. */
const HISTORY_LINES = {
	submitted: __( 'Submitted', 'corex' ),
	read: __( 'Marked read', 'corex' ),
	unread: __( 'Marked unread', 'corex' ),
	note: __( 'Note added', 'corex' ),
};

function assignmentLine( to, record ) {
	if ( to === UNASSIGNED ) {
		return __( 'Unassigned', 'corex' );
	}
	const person = ownerOptions( record ).find(
		( option ) => option.value === to
	);

	// The event stores a type and a key. Somebody who can no longer own submissions has no name
	// here, and "user:99" is not one.
	return person
		? sprintf(
				/* translators: %s: the person a submission was assigned to. */
				__( 'Assigned to %s', 'corex' ),
				person.label
			)
		: __( 'Assignment changed', 'corex' );
}

/**
 * One event of a submission's history as a sentence. The pane used to print the stored stage and
 * outcome side by side, which read "read success".
 *
 * @param {Object} event  One stored event: its stage, its outcome and what it recorded.
 * @param {Object} record The submission it belongs to.
 * @return {string} What happened.
 */
export function historyLine( event, record ) {
	const stage = event.stage || event.kind || '';
	const summary = event.summary || {};

	if ( stage === 'status' ) {
		return sprintf(
			/* translators: 1: the status a submission had. 2: the status it has now. */
			__( 'Status changed from %1$s to %2$s', 'corex' ),
			STATUS_LABELS[ summary.from ] || summary.from,
			STATUS_LABELS[ summary.to ] || summary.to
		);
	}
	if ( stage === 'assignment' ) {
		return assignmentLine( summary.to, record );
	}
	if (
		stage === 'notification' &&
		DELIVERY_META[ summary.delivery_status ]
	) {
		return DELIVERY_META[ summary.delivery_status ].label;
	}
	if ( stage === 'email' ) {
		return event.outcome === 'success'
			? __( 'Reply sent', 'corex' )
			: __( 'Reply could not be sent', 'corex' );
	}

	return (
		HISTORY_LINES[ stage ] ||
		[ stage, event.outcome || event.state ].filter( Boolean ).join( ': ' )
	);
}
