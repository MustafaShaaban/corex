/**
 * What this site has, what it can do, and what is half-configured (spec 074, FR-5).
 *
 * It sits under the Models catalog because that is where the question comes up: a model says it
 * cannot be imported into, and the next thing you want is the whole picture — which forms exist and
 * where they came from, which models support what, which add-ons are running, which notification
 * producers are live, and what is selected but unfinished.
 *
 * A summary, not a workspace. Every gap links to the screen that fixes it rather than trying to fix
 * it here, and nothing on it is a control that changes state.
 */
import { __, _n, sprintf } from '@wordpress/i18n';

const SOURCE_LABELS = {
	visual_flow: __( 'Visual flow', 'corex' ),
	code_form: __( 'Defined in code', 'corex' ),
	external: __( 'Provided by a module', 'corex' ),
};

const CAPABILITY_LABELS = {
	read: __( 'Read', 'corex' ),
	write: __( 'Write', 'corex' ),
	import: __( 'Import', 'corex' ),
	export: __( 'Export', 'corex' ),
	migrations: __( 'Migrations', 'corex' ),
	rollback: __( 'Rollback', 'corex' ),
};

const ADDON_STATES = {
	active: __( 'Active', 'corex' ),
	inactive: __( 'Installed, not active', 'corex' ),
	not_installed: __( 'Not installed', 'corex' ),
};

function Section( { title, description, children } ) {
	return (
		<section className="corex-capability__section">
			<h3>{ title }</h3>
			{ description ? <p className="corex-capability__lede">{ description }</p> : null }
			{ children }
		</section>
	);
}

export default function CapabilityPanel( { report } ) {
	if ( ! report || ! report.forms ) {
		return null;
	}

	const { forms, models, addons, producers, gaps, hasGaps } = report;

	return (
		<section className="corex-surface corex-capability">
			<header>
				<h2>{ __( 'What this site can do', 'corex' ) }</h2>
				<p className="corex-capability__lede">
					{ __(
						'Everything CoreX has registered, what each part supports, and anything that is selected but not finished.',
						'corex'
					) }
				</p>
			</header>

			{ /* Gaps first: they are the only thing here that asks anything of you. */ }
			<Section
				title={ __( 'Needs finishing', 'corex' ) }
				description={
					hasGaps
						? __( 'These are configured far enough to look done, and are not.', 'corex' )
						: undefined
				}
			>
				{ hasGaps ? (
					<ul className="corex-capability__gaps">
						{ gaps.map( ( gap ) => (
							<li key={ gap.key }>
								<p>{ gap.summary }</p>
								{ gap.action_url ? (
									<a className="button" href={ gap.action_url }>
										{ gap.action_label || __( 'Fix it', 'corex' ) }
									</a>
								) : null }
							</li>
						) ) }
					</ul>
				) : (
					<p className="corex-capability__ok">
						{ __( 'Nothing is half-configured.', 'corex' ) }
					</p>
				) }
			</Section>

			<Section
				title={ sprintf(
					/* translators: %d: number of registered forms. */
					_n( '%d form', '%d forms', forms.total, 'corex' ),
					forms.total
				) }
			>
				{ forms.total === 0 ? (
					<p className="corex-capability__ok">{ __( 'No forms are registered.', 'corex' ) }</p>
				) : (
					<ul className="corex-capability__list">
						{ forms.items.map( ( form ) => (
							<li key={ form.slug }>
								<strong>{ form.label }</strong>
								<code>{ form.slug }</code>
								<span>{ SOURCE_LABELS[ form.source ] || form.source }</span>
								<span>
									{ form.active
										? __( 'Accepting submissions', 'corex' )
										: __( 'Not published', 'corex' ) }
								</span>
							</li>
						) ) }
					</ul>
				) }
			</Section>

			<Section
				title={ sprintf(
					/* translators: %d: number of registered data models. */
					_n( '%d data model', '%d data models', models.total, 'corex' ),
					models.total
				) }
			>
				<ul className="corex-capability__list">
					{ models.items.map( ( model ) => (
						<li key={ model.key }>
							<strong>{ model.label }</strong>
							<code>{ model.key }</code>
							<span>
								{ model.can.length
									? model.can
											.map( ( key ) => CAPABILITY_LABELS[ key ] || key )
											.join( ' · ' )
									: __( 'Nothing supported', 'corex' ) }
							</span>
						</li>
					) ) }
				</ul>
				<p className="corex-capability__counts">
					{ sprintf(
						/* translators: 1: models supporting import, 2: models supporting migrations, 3: models supporting export. */
						__(
							'%1$d can be imported into · %2$d ship schema changes · %3$d can be exported',
							'corex'
						),
						models.supporting.import,
						models.supporting.migrations,
						models.supporting.export
					) }
				</p>
			</Section>

			<Section title={ __( 'Add-ons', 'corex' ) }>
				<p className="corex-capability__counts">
					{ sprintf(
						/* translators: 1: active add-ons, 2: installed but inactive, 3: not installed. */
						__( '%1$d active · %2$d installed but off · %3$d not installed', 'corex' ),
						addons.active,
						addons.inactive,
						addons.not_installed
					) }
				</p>
				<ul className="corex-capability__list">
					{ addons.items.map( ( addon ) => (
						<li key={ addon.slug }>
							<strong>{ addon.label }</strong>
							<span>{ ADDON_STATES[ addon.state ] || addon.state }</span>
						</li>
					) ) }
				</ul>
			</Section>

			<Section
				title={ __( 'Notification producers', 'corex' ) }
				description={ __(
					'The events CoreX is currently listening for. A producer whose module is absent is not wired at all.',
					'corex'
				) }
			>
				{ producers.total === 0 ? (
					<p className="corex-capability__ok">
						{ __( 'No producers are wired on this site.', 'corex' ) }
					</p>
				) : (
					<ul className="corex-capability__tags">
						{ producers.keys.map( ( key ) => (
							<li key={ key }>
								<code>{ key }</code>
							</li>
						) ) }
					</ul>
				) }
			</Section>
		</section>
	);
}
