import { __, sprintf } from '@wordpress/i18n';

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

function DeliveryHero( { delivery, settingsUrl } ) {
	return (
		<section className="corex-email-app__hero corex-surface">
			<p className="corex-email-app__eyebrow">
				{ __( 'Delivery mode', 'corex' ) }
			</p>
			<h2>{ delivery.environment || __( 'Unknown', 'corex' ) }</h2>
			<p>{ deliveryDescription( delivery ) }</p>
			{ ! delivery.provider_configured && (
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
							<time>
								{ new Date(
									attempt.occurred_at
								).toLocaleString() }
							</time>
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

export function OverviewPanel( { data, settingsUrl } ) {
	return (
		<div className="corex-email-app__stack">
			<DeliveryHero
				delivery={ data.delivery || {} }
				settingsUrl={ settingsUrl }
			/>
			<DeliveryMetrics counts={ data.counts || {} } />
			<RecentTestSends attempts={ data.recentTestSends } />
			<HealthSummary checks={ data.health } />
		</div>
	);
}
