import { __ } from '@wordpress/i18n';

const TONES = {
	delivered: 'success',
	captured: 'info',
	blocked: 'warning',
};

/**
 * What happens to mail on this site, on every Email Studio tab (spec 113, US4). The words are
 * the server's: they are read from the decisions the sends themselves take.
 *
 * @param {Object}      props
 * @param {Object|null} props.outcome The outcome the Studio was sent, or null before it is read.
 * @return {Element|null} The statement, or nothing while there is none.
 */
export function MailOutcomeBanner( { outcome } ) {
	if ( ! outcome || ! outcome.title ) {
		return null;
	}

	const tone = TONES[ outcome.state ] || 'info';
	const facts = Array.isArray( outcome.facts ) ? outcome.facts : [];

	return (
		<section
			className={ `corex-email-app__outcome corex-surface is-${ tone }` }
			aria-labelledby="corex-email-outcome-title"
		>
			<p className="corex-email-app__eyebrow">
				{ __( 'What happens to mail on this site', 'corex' ) }
			</p>
			<h2 id="corex-email-outcome-title">{ outcome.title }</h2>
			{ outcome.detail && <p>{ outcome.detail }</p> }
			{ facts.length > 0 && (
				<ul className="corex-email-app__outcome-facts">
					{ facts.map( ( fact ) => (
						<li key={ fact }>{ fact }</li>
					) ) }
				</ul>
			) }
		</section>
	);
}
