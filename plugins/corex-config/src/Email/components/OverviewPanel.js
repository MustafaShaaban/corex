import { __, sprintf } from '@wordpress/i18n';
import CorexTime from '../../admin/components/CorexTime.js';

/**
 * The "Capture only CoreX mail" switch (spec 113, FR-007), and the way to the delivery
 * settings. What happens to mail is said above every tab; this is where it is changed.
 *
 * @param {Object}                  props
 * @param {Object}                  props.delivery             The delivery settings the Studio was sent.
 * @param {Object}                  props.outcome              What happens to mail on this site.
 * @param {string}                  props.settingsUrl          The Mail settings screen.
 * @param {boolean}                 props.busy                 Whether a change is being saved.
 * @param {(scope: string) => void} props.onScope              Saves the capture scope (`all` or `corex`).
 * @param {boolean}                 props.canConfigureDelivery Whether this person may change how
 *                                                             the site delivers mail: a site
 *                                                             administrator (spec 117, US-EML-20).
 * @return {Element} The delivery panel.
 */
function DeliveryHero( {
	delivery,
	outcome,
	settingsUrl,
	busy,
	onScope,
	canConfigureDelivery,
} ) {
	const capturing = outcome && outcome.environment !== 'production';
	const onlyCorex = outcome?.scope === 'corex';

	return (
		<section className="corex-email-app__hero corex-surface">
			<p className="corex-email-app__eyebrow">
				{ __( 'Delivery', 'corex' ) }
			</p>
			{ capturing && (
				<div className="corex-email-app__scope">
					<input
						id="corex-email-capture-scope"
						type="checkbox"
						aria-describedby={
							canConfigureDelivery
								? 'corex-email-capture-scope-help'
								: 'corex-email-capture-scope-help corex-email-delivery-admin'
						}
						checked={ onlyCorex }
						disabled={ busy || ! onScope || ! canConfigureDelivery }
						onChange={ ( event ) =>
							onScope?.( event.target.checked ? 'corex' : 'all' )
						}
					/>
					<div>
						<label htmlFor="corex-email-capture-scope">
							{ __( 'Capture only CoreX mail', 'corex' ) }
						</label>
						<p id="corex-email-capture-scope-help">
							{ onlyCorex
								? __(
										"WordPress's own emails and other plugins' mail are sent for real.",
										'corex'
									)
								: __(
										'All mail from this site is captured, WordPress and other plugins included.',
										'corex'
									) }
						</p>
					</div>
				</div>
			) }
			{ ! canConfigureDelivery && (
				<p
					id="corex-email-delivery-admin"
					className="corex-email-app__note"
				>
					{ __(
						'Turning delivery on or off, choosing its provider and choosing which mail is captured change how the whole site sends mail, so a site administrator does them.',
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
	const countWindow = counts.window || 100;
	const metrics = [
		[ 'templates', __( 'Templates', 'corex' ) ],
		[ 'captures', __( 'Captured', 'corex' ) ],
		[ 'delivered', __( 'Delivered', 'corex' ) ],
		[ 'failed', __( 'Failed', 'corex' ) ],
	];

	return (
		<section
			className="corex-email-app__metrics-block"
			aria-labelledby="corex-email-metrics-title"
		>
			<p
				id="corex-email-metrics-title"
				className="corex-email-app__metrics-window"
			>
				{ sprintf(
					/* translators: %d: how many of the newest emails are counted. */
					__(
						'Templates in total; the other counts are of the last %d emails.',
						'corex'
					),
					countWindow
				) }
			</p>
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
		</section>
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
	busy,
	onScope,
	canConfigureDelivery = true,
} ) {
	return (
		<div className="corex-email-app__stack">
			<DeliveryHero
				delivery={ data.delivery || {} }
				outcome={ data.outcome }
				settingsUrl={ settingsUrl }
				busy={ busy }
				onScope={ onScope }
				canConfigureDelivery={ canConfigureDelivery }
			/>
			<DeliveryMetrics counts={ data.counts || {} } />
			<RecentTestSends attempts={ data.recentTestSends } />
			<HealthSummary checks={ data.health } />
		</div>
	);
}
