import { useContext } from '@wordpress/element';
import {
	PendingControl,
	workingProps,
} from '../../admin/components/working.js';
import { __ } from '@wordpress/i18n';
import { Field, Notice } from './shared.js';

/**
 * The widths a mail client lays an email out at: the measure every email is built for, and a
 * phone's (spec 120, story 1). Pixels, because they are the email's, not the screen's.
 */
export const PREVIEW_WIDTHS = { desktop: 600, mobile: 375 };

/**
 * What a drawing that could not be made says, in place of the email.
 *
 * @param {Object}   props
 * @param {string[]} props.errors One sentence each, naming the variable or partial.
 * @return {Element} The notice.
 */
function DrawingErrors( { errors } ) {
	return (
		<Notice tone="error">
			<strong>
				{ __( 'This template cannot be drawn yet.', 'corex' ) }
			</strong>
			<ul className="corex-email-app__drawing-errors">
				{ errors.map( ( error ) => (
					<li key={ error }>{ error }</li>
				) ) }
			</ul>
		</Notice>
	);
}

function NoTemplate() {
	return (
		<p>
			{ __(
				'Select a template on the Templates tab to see it as it is sent.',
				'corex'
			) }
		</p>
	);
}

function SampleValues( { samples, onSample } ) {
	return (
		<aside
			className="corex-email-app__samples"
			aria-labelledby="corex-email-samples-title"
		>
			<h3 id="corex-email-samples-title">
				{ __( 'Sample values', 'corex' ) }
			</h3>
			<p>
				{ __(
					'Change a value to see the email with it. Nothing here is saved.',
					'corex'
				) }
			</p>
			{ samples.length === 0 ? (
				<p>{ __( 'This template uses no variables.', 'corex' ) }</p>
			) : (
				samples.map( ( sample ) =>
					sample.editable ? (
						<Field
							key={ sample.key }
							id={ `corex-email-sample-${ sample.key.replaceAll(
								'.',
								'-'
							) }` }
							name={ sample.key }
							label={ sample.label }
							value={ sample.value }
							onChange={ ( event ) =>
								onSample( sample.key, event.target.value )
							}
						/>
					) : (
						<p key={ sample.key }>
							<strong>{ sample.label }</strong>
							<br />
							{ sample.value }
						</p>
					)
				)
			) }
		</aside>
	);
}

function Segmented( { device, direction, onDevice, onDirection } ) {
	return (
		<div
			className="corex-email-app__segmented"
			role="group"
			aria-label={ __( 'How the email is shown', 'corex' ) }
		>
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
				{ __( 'Right to left', 'corex' ) }
			</button>
		</div>
	);
}

/**
 * The email the server draws, with the code that sends it, from the draft being edited.
 *
 * @param {Object}   props
 * @param {boolean}  props.selected    Whether a template is open in the editor.
 * @param {Object}   props.drawn       The drawing: its state, and the email or the errors.
 * @param {string}   props.direction   `ltr` or `rtl`.
 * @param {string}   props.device      `desktop` or `mobile`.
 * @param {Function} props.onDirection Turns right to left on or off.
 * @param {Function} props.onDevice    Chooses the width.
 * @param {Function} props.onSample    Changes one sample value, for this preview.
 * @return {Element} The panel.
 */
export function PreviewPanel( {
	selected,
	drawn,
	direction,
	device,
	onDirection,
	onDevice,
	onSample,
} ) {
	return (
		<section className="corex-surface corex-email-app__preview-panel">
			<header>
				<div>
					<h2>{ __( 'Template preview', 'corex' ) }</h2>
					<p>
						{ __(
							'Drawn by the server as it is sent, with sample values. Test send sends this email.',
							'corex'
						) }
					</p>
				</div>
				<Segmented
					device={ device }
					direction={ direction }
					onDevice={ onDevice }
					onDirection={ onDirection }
				/>
			</header>
			{ ! selected && <NoTemplate /> }
			{ selected && drawn.status === 'error' && (
				<DrawingErrors errors={ drawn.errors } />
			) }
			{ selected && drawn.status !== 'error' && (
				<div className="corex-email-app__preview-body">
					<div
						className="corex-email-app__preview-frame"
						aria-busy={ drawn.status === 'loading' }
						style={ { maxInlineSize: PREVIEW_WIDTHS[ device ] } }
					>
						{ drawn.html ? (
							<>
								<p className="corex-email-app__preview-subject">
									<span>{ __( 'Subject', 'corex' ) }</span>{ ' ' }
									<strong dir="auto">
										{ drawn.subject }
									</strong>
								</p>
								<iframe
									title={ __(
										'The email as it is sent',
										'corex'
									) }
									sandbox=""
									srcDoc={ drawn.html }
								/>
							</>
						) : (
							<p>{ __( 'Drawing the email…', 'corex' ) }</p>
						) }
					</div>
					<SampleValues
						samples={ drawn.samples || [] }
						onSample={ onSample }
					/>
				</div>
			) }
		</section>
	);
}

/**
 * The plain-text version, as the server makes it and the email carries it.
 *
 * @param {Object}  props
 * @param {boolean} props.selected Whether a template is open.
 * @param {Object}  props.drawn    The drawing.
 * @param {string}  props.mode     `auto` or `manual`.
 * @return {Element} The panel.
 */
export function PlainTextPanel( { selected, drawn, mode } ) {
	const description =
		mode === 'auto'
			? __(
					'Made from the HTML: paragraphs, line breaks, list items and every link’s address are kept.',
					'corex'
				)
			: __( 'Written by hand on the Templates tab.', 'corex' );
	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Sent as the plain-text version', 'corex' ) }</h2>
			<p>{ description }</p>
			{ ! selected && <NoTemplate /> }
			{ selected && drawn.status === 'error' && (
				<DrawingErrors errors={ drawn.errors } />
			) }
			{ selected && drawn.status !== 'error' && (
				<pre className="corex-email-app__plain" dir="auto">
					{ drawn.text || __( 'Drawing the email…', 'corex' ) }
				</pre>
			) }
		</section>
	);
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

function resultTone( state ) {
	if ( state === 'failed' || state === 'rejected' ) {
		return 'error';
	}
	return state === 'captured' ? 'info' : 'success';
}

function TestResult( { sent } ) {
	return (
		<Notice tone={ resultTone( sent.result?.state ) }>
			<strong>{ sent.summary }</strong>
			{ sent.reason && <span> { sent.reason }</span> }{ ' ' }
			<a href={ sent.log_url }>
				{ __( 'Open it in the delivery log', 'corex' ) }
			</a>
		</Notice>
	);
}

/**
 * Sends the previewed email, exactly, to one address (spec 120, story 1).
 *
 * @param {Object}   props
 * @param {Object}   props.outcome    What happens to mail on this site.
 * @param {boolean}  props.selected   Whether a template is open.
 * @param {Object}   props.drawn      The drawing the test sends.
 * @param {boolean}  props.busy       Whether a request is out.
 * @param {Object}   props.lastResult What the last test did.
 * @param {string}   props.error      Why the last test was refused.
 * @param {Function} props.onSend     Sends it.
 * @return {Element} The panel.
 */
export function TestSendPanel( {
	outcome,
	selected,
	drawn,
	busy,
	lastResult,
	error,
	onSend,
} ) {
	const pending = useContext( PendingControl );
	const drawable = selected && drawn.status !== 'error';
	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Test send', 'corex' ) }</h2>
			<p>{ testSendNote( outcome ) }</p>
			<p>
				{ __(
					'The test is the email the Preview tab shows, with its sample values and “[Test]” before its subject. No real submission is used.',
					'corex'
				) }
			</p>
			{ ! selected && <NoTemplate /> }
			{ selected && drawn.status === 'error' && (
				<DrawingErrors errors={ drawn.errors } />
			) }
			{ drawable && (
				<form
					className="corex-email-app__form-grid"
					onSubmit={ onSend }
					noValidate
				>
					<Field
						label={ __( 'Send the test to', 'corex' ) }
						name="to"
						type="email"
						autoComplete="email"
						error={ error }
					/>
					<div className="corex-email-app__actions is-wide">
						<button
							className="button button-primary"
							disabled={ busy }
							{ ...workingProps( pending === 'test' ) }
						>
							{ __( 'Send test', 'corex' ) }
						</button>
					</div>
				</form>
			) }
			{ lastResult && <TestResult sent={ lastResult } /> }
		</section>
	);
}
