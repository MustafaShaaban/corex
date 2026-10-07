/**
 * How a submission's status and its notification's delivery are named and drawn, in the inbox
 * table and in the detail pane alike.
 */
import { __ } from '@wordpress/i18n';

export const STATUSES = [
	'new',
	'in_progress',
	'replied',
	'closed',
	'spam',
	'archived',
];

export const STATUS_LABELS = {
	new: __( 'New', 'corex' ),
	in_progress: __( 'In progress', 'corex' ),
	replied: __( 'Replied', 'corex' ),
	closed: __( 'Closed', 'corex' ),
	spam: __( 'Spam', 'corex' ),
	archived: __( 'Archived', 'corex' ),
};

export const STATUS_OPTIONS = STATUSES.map( ( status ) => ( {
	value: status,
	label: STATUS_LABELS[ status ],
} ) );

export function StatusBadge( { status } ) {
	return (
		<span className={ `corex-inbox__status is-${ status }` }>
			{ STATUS_LABELS[ status ] || status }
		</span>
	);
}

// The notification-delivery states, each conveyed by text + icon + accessible name — never colour
// alone (WCAG 2.2 AA 1.4.1). "accepted" reads as accepted-for-delivery: a transport taking a message
// is not proof it reached an inbox (spec 071 FR-015).
//
// `unavailable` means no delivery was recorded, which is every submission of a form defined in
// code. It used to read "Delivery unavailable", which sounds like a fault on every row.
export const DELIVERY_META = {
	accepted: {
		label: __( 'Notification accepted', 'corex' ),
		tone: 'success',
		icon: 'yes-alt',
	},
	captured: {
		label: __( 'Notification captured', 'corex' ),
		tone: 'info',
		icon: 'download',
	},
	queued: {
		label: __( 'Notification queued', 'corex' ),
		tone: 'info',
		icon: 'clock',
	},
	sending: {
		label: __( 'Notification sending', 'corex' ),
		tone: 'info',
		icon: 'update',
	},
	sent: {
		label: __( 'Notification sent', 'corex' ),
		tone: 'success',
		icon: 'yes-alt',
	},
	opened: {
		label: __( 'Notification opened', 'corex' ),
		tone: 'success',
		icon: 'visibility',
	},
	failed: {
		label: __( 'Notification failed', 'corex' ),
		tone: 'warning',
		icon: 'warning',
	},
	rejected: {
		label: __( 'Notification rejected', 'corex' ),
		tone: 'warning',
		icon: 'dismiss',
	},
	bounced: {
		label: __( 'Notification bounced', 'corex' ),
		tone: 'warning',
		icon: 'undo',
	},
	not_attempted: {
		label: __( 'No notification', 'corex' ),
		tone: 'neutral',
		icon: 'minus',
	},
	unavailable: {
		label: __( 'Not tracked', 'corex' ),
		tone: 'neutral',
		icon: 'minus',
	},
};

export function DeliveryBadge( { delivery } ) {
	const meta = DELIVERY_META[ delivery?.status ] || DELIVERY_META.unavailable;
	return (
		<span
			className={ `corex-inbox__delivery is-${ meta.tone }` }
			title={ delivery?.safe_reason || meta.label }
		>
			<span
				className={ `dashicons dashicons-${ meta.icon }` }
				aria-hidden="true"
			/>
			<span>{ meta.label }</span>
		</span>
	);
}
