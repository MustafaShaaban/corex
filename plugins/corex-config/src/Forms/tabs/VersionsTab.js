/**
 * Every published version of the flow, newest first, with its date and author; any of them can be
 * opened and copied into the draft. Copying never publishes (spec 122, US-FLW-01, FR-005).
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexTime from '../../admin/components/CorexTime.js';
import { workingProps } from '../../admin/components/working.js';

/**
 * @param {Object}   props
 * @param {Object}   props.draft   The draft on screen: `flow`, `version`, `versions`.
 * @param {boolean}  props.busy    Whether a request is out.
 * @param {string}   props.pending Which control's request is out.
 * @param {Function} props.onCopy  Given a version number, the click handler that copies it.
 * @return {import('react').ReactElement} The tab.
 */
export function VersionsTab( { draft, busy, pending, onCopy } ) {
	const [ open, setOpen ] = useState( 0 );
	const publications = draft.flow.publications || [];
	const versions = draft.versions || [];

	return (
		<section className="corex-flow-editor__panel corex-flow-versions">
			<header>
				<div>
					<h2>{ __( 'Versions', 'corex' ) }</h2>
					<p>
						{ __(
							'Each version that was published. Copy one into the draft to work from it; it goes live only when you publish.',
							'corex'
						) }
					</p>
				</div>
			</header>
			{ publications.length === 0 ? (
				<p>
					{ __( 'This flow has not been published yet.', 'corex' ) }
				</p>
			) : (
				<ol className="corex-flow-versions__list">
					{ publications.map( ( publication ) => {
						const version = versions.find(
							( candidate ) =>
								candidate.version_number === publication.version
						);
						const live =
							draft.flow.state === 'published' &&
							publication.version ===
								draft.flow.published_version;
						const panelId = `corex-flow-version-${ publication.version }-${ publication.at }`;

						return (
							<li key={ panelId }>
								<div className="corex-flow-versions__row">
									<strong>
										{ sprintf(
											/* translators: %d: a version number. */
											__( 'Version %d', 'corex' ),
											publication.version
										) }
									</strong>
									{ live ? (
										<span className="corex-flow-list__state is-published">
											{ __( 'Live', 'corex' ) }
										</span>
									) : null }
									<span>
										<CorexTime
											value={ publication.at }
											absent={ __(
												'Not recorded',
												'corex'
											) }
										/>
										{ ' · ' }
										{ publication.by_name ||
											__(
												'Author not recorded',
												'corex'
											) }
									</span>
									<div className="corex-flow-editor__actions">
										<button
											type="button"
											className="button"
											aria-expanded={
												open === publication.version
											}
											aria-controls={ panelId }
											disabled={ ! version }
											onClick={ () =>
												setOpen(
													open === publication.version
														? 0
														: publication.version
												)
											}
										>
											{ open === publication.version
												? __( 'Hide', 'corex' )
												: __( 'Open', 'corex' ) }
										</button>
										<button
											type="button"
											className="button"
											disabled={ busy || ! version }
											onClick={ onCopy(
												publication.version
											) }
											{ ...workingProps(
												pending ===
													`copy:${ publication.version }`
											) }
										>
											{ __( 'Copy into draft', 'corex' ) }
										</button>
									</div>
								</div>
								{ open === publication.version && version ? (
									<VersionSummary
										id={ panelId }
										version={ version }
									/>
								) : null }
							</li>
						);
					} ) }
				</ol>
			) }
		</section>
	);
}

/**
 * What a version asks, read only.
 *
 * @param {Object} props
 * @param {string} props.id      The panel's id.
 * @param {Object} props.version A saved version.
 * @return {import('react').ReactElement} Its questions.
 */
function VersionSummary( { id, version } ) {
	const fields = version.configuration?.schema || [];

	return (
		<div id={ id } className="corex-flow-versions__summary">
			<p>
				{ sprintf(
					/* translators: %d: how many questions a version of a form asks. */
					_n( '%d question', '%d questions', fields.length, 'corex' ),
					fields.length
				) }
			</p>
			<ul>
				{ fields.map( ( field ) => (
					<li key={ field.uuid || field.key }>
						{ field.label || field.key } <code>{ field.type }</code>
					</li>
				) ) }
			</ul>
		</div>
	);
}
