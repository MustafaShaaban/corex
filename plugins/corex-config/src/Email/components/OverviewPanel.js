import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../../admin/components/CorexTime.js';

function deliveryDescription( delivery ) {
	if (
		delivery.environment === 'development' ||
		delivery.environment === 'local'
	) {
		return __(
			'Outgoing CoreX mail is captured locally and cannot reach real recipients.',
			'corex'
		);
	}
	if ( delivery.provider_configured ) {
		return __(
			'A provider is configured. Live sends still follow the explicit delivery gate.',
			'corex'
		);
	}

	return __(
		'Live delivery is blocked until a provider is configured.',
		'corex'
	);
}

/**
 * How mail leaves the site, and where it is changed. Choosing the provider and turning live
 * delivery on or off stay with site administration (spec 117, US-EML-20), so anyone else is told
 * that instead of being sent to a field they cannot change.
 *
 * @param {Object}  props
 * @param {Object}  props.delivery             The delivery facts.
 * @param {string}  props.settingsUrl          Settings → Mail.
 * @param {boolean} props.canConfigureDelivery Whether this person may change them.
 * @return {Element} The hero.
 */
function DeliveryHero( { delivery, settingsUrl, canConfigureDelivery } ) {
	return (
		<section className="corex-email-app__hero corex-surface">
			<p className="corex-email-app__eyebrow">
				{ __( 'Delivery mode', 'corex' ) }
			</p>
			<h2>{ delivery.environment || __( 'Unknown', 'corex' ) }</h2>
			<p>{ deliveryDescription( delivery ) }</p>
			{ ! canConfigureDelivery && (
				<p className="corex-email-app__note">
					{ __(
						'Turning delivery on or off and choosing its provider change how the whole site sends mail, so a site administrator does them in Settings.',
						'corex'
					) }
				</p>
			) }
			{ canConfigureDelivery && ! delivery.provider_configured && (
				<p>
					<a className="button button-primary" href={ settingsUrl }>
						{ __( 'Configure delivery', 'corex' ) }
					</a>
				</p>
			) }
		</section>
	);
}

function DeliveryMetrics( { counts } ) {
	const metrics = [
		[ 'templates', __( 'Templates', 'corex' ) ],
		[ 'captures', __( 'Captured', 'corex' ) ],
		[ 'delivered', __( 'Delivered', 'corex' ) ],
		[ 'failed', __( 'Failed', 'corex' ) ],
	];

	return (
		<div className="corex-email-app__metrics">
			{ metrics.map( ( [ key, label ] ) => (
				<div
					key={ key }
					className="corex-email-app__metric corex-surface"
				>
					<span>{ label }</span>
					<strong>{ counts[ key ] || 0 }</strong>
				</div>
			) ) }
		</div>
	);
}

function RecentTestSends( { attempts } ) {
	return (
		<section className="corex-surface corex-email-app__table-card">
			<h2>{ __( 'Recent test sends', 'corex' ) }</h2>
			{ attempts.length === 0 ? (
				<p>{ __( 'No test sends yet.', 'corex' ) }</p>
			) : (
				<ul className="corex-email-app__captures">
					{ attempts.map( ( attempt ) => (
						<li key={ attempt.attempt_id }>
							<strong>{ attempt.subject }</strong>
							<span>{ attempt.state }</span>
							<CorexTime value={ attempt.occurred_at } />
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}

function HealthSummary( { checks } ) {
	return (
		<section className="corex-surface corex-email-app__table-card">
			<h2>{ __( 'Health checks', 'corex' ) }</h2>
			{ checks.length === 0 ? (
				<p>
					{ __(
						'Create a template to begin health checks.',
						'corex'
					) }
				</p>
			) : (
				<ul className="corex-email-app__health">
					{ checks.map( ( check ) => (
						<li key={ check.template_id }>
							<strong>
								{ sprintf(
									/* translators: %d: Email template numeric ID. */
									__( 'Template #%d', 'corex' ),
									check.template_id
								) }
							</strong>
							<span>{ check.status }</span>
						</li>
					) ) }
				</ul>
			) }
		</section>
	);
}

export function OverviewPanel( {
	data,
	settingsUrl,
	canConfigureDelivery = true,
} ) {
	return (
		<div className="corex-email-app__stack">
			<DeliveryHero
				delivery={ data.delivery || {} }
				settingsUrl={ settingsUrl }
				canConfigureDelivery={ canConfigureDelivery }
			/>
			<DeliveryMetrics counts={ data.counts || {} } />
			<RecentTestSends attempts={ data.recentTestSends } />
			<HealthSummary checks={ data.health } />
		</div>
	);
}
