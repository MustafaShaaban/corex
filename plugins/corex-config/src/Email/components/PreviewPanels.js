import { useContext } from '@wordpress/element';
import {
	PendingControl,
	workingProps,
} from '../../admin/components/working.js';
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

/**
 * What a test send will meet, from what happens to mail on this site (spec 113).
 *
 * @param {Object|null} outcome What happens to mail on this site.
 * @return {string} One sentence.
 */
export function testSendNote( outcome ) {
	switch ( outcome?.state ) {
		case 'captured':
			return __(
				'This test will be captured: it is kept in the log and nobody receives it.',
				'corex'
			);
		case 'delivered':
			return __(
				'This test is sent for real, to the address you enter.',
				'corex'
			);
		case 'blocked':
			return __(
				'This test will be refused until a provider is set and live delivery is on.',
				'corex'
			);
		default:
			return __(
				'This test follows the configured provider and live-delivery gate.',
				'corex'
			);
	}
}

export function TestSendPanel( { outcome, draft, busy, lastResult, onSend } ) {
	const pending = useContext( PendingControl );
	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Test send', 'corex' ) }</h2>
			<p>{ testSendNote( outcome ) }</p>
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
					<button
						className="button button-primary"
						disabled={ busy }
						{ ...workingProps( pending === 'test' ) }
					>
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
