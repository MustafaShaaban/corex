import { __, sprintf } from '@wordpress/i18n';
import { Field, Notice } from './shared.js';

export function PreviewPanel( {
	document,
	direction,
	device,
	onDirection,
	onDevice,
} ) {
	return (
		<section className="corex-surface corex-email-app__preview-panel">
			<header>
				<div>
					<h2>{ __( 'Template preview', 'corex' ) }</h2>
					<p>
						{ __(
							'Sample context only—this is not a delivery or live metric.',
							'corex'
						) }
					</p>
				</div>
				<div className="corex-email-app__segmented">
					<button
						type="button"
						aria-pressed={ device === 'desktop' }
						className={ device === 'desktop' ? 'is-active' : '' }
						onClick={ () => onDevice( 'desktop' ) }
					>
						{ __( 'Desktop', 'corex' ) }
					</button>
					<button
						type="button"
						aria-pressed={ device === 'mobile' }
						className={ device === 'mobile' ? 'is-active' : '' }
						onClick={ () => onDevice( 'mobile' ) }
					>
						{ __( 'Mobile', 'corex' ) }
					</button>
					<button
						type="button"
						aria-pressed={ direction === 'rtl' }
						className={ direction === 'rtl' ? 'is-active' : '' }
						onClick={ () =>
							onDirection( direction === 'rtl' ? 'ltr' : 'rtl' )
						}
					>
						{ __( 'RTL', 'corex' ) }
					</button>
				</div>
			</header>
			<div className={ `corex-email-app__preview-frame is-${ device }` }>
				<iframe
					title={ __( 'Sandboxed email preview', 'corex' ) }
					sandbox=""
					srcDoc={ document }
				/>
			</div>
		</section>
	);
}

export function PlainTextPanel( { value, mode } ) {
	const description =
		mode === 'auto'
			? __( 'Generated from the current HTML draft.', 'corex' )
			: __( 'Manual fallback from the current draft.', 'corex' );
	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Plain-text output', 'corex' ) }</h2>
			<p>{ description }</p>
			<pre className="corex-email-app__plain">
				{ value || __( 'No plain-text output yet.', 'corex' ) }
			</pre>
		</section>
	);
}

function resultTone( state ) {
	return state === 'failed' || state === 'rejected' ? 'error' : 'success';
}

export function TestSendPanel( { delivery, draft, busy, lastResult, onSend } ) {
	const local =
		delivery.environment === 'development' ||
		delivery.environment === 'local';
	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Test send', 'corex' ) }</h2>
			<p>
				{ local
					? __( 'This test will be captured locally.', 'corex' )
					: __(
							'This test follows the configured provider and live-delivery gate.',
							'corex'
					  ) }
			</p>
			<form
				key={ `${ draft.subject }-${ draft.html_body }` }
				className="corex-email-app__form-grid"
				onSubmit={ onSend }
			>
				<Field
					label={ __( 'Recipient', 'corex' ) }
					name="to"
					type="email"
					required
				/>
				<Field
					label={ __( 'Subject', 'corex' ) }
					name="subject"
					defaultValue={ draft.subject }
					required
				/>
				<Field
					label={ __( 'HTML body', 'corex' ) }
					name="body"
					defaultValue={ draft.html_body }
					textarea
					wide
					required
				/>
				<div className="corex-email-app__actions is-wide">
					<button className="button button-primary" disabled={ busy }>
						{ __( 'Run test', 'corex' ) }
					</button>
				</div>
			</form>
			{ lastResult && (
				<Notice tone={ resultTone( lastResult.state ) }>
					{ sprintf(
						/* translators: 1: Delivery state. 2: Delivery provider. */
						__( 'Result: %1$s via %2$s', 'corex' ),
						lastResult.state,
						lastResult.provider
					) }
				</Notice>
			) }
		</section>
	);
}
