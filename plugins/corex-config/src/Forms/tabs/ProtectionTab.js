import { __ } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';

const CAPTCHA_MODES = [
	{ value: 'inherit', label: __( 'Inherit site default', 'corex' ) },
	{ value: 'on', label: __( 'Always protect this form', 'corex' ) },
	{ value: 'off', label: __( 'Never protect this form', 'corex' ) },
];

// The stored shape is sparse: a key is present only when it overrides the global default, so an
// all-inherit form stays checksum-neutral (spec 071). This tab reads/writes that shape directly.
export function ProtectionTab( { protection, onChange } ) {
	const value =
		protection && typeof protection === 'object' ? protection : {};
	const mode = value.captcha || 'inherit';

	const setKey = ( key, next ) => {
		const draft = { ...value };
		if ( next === '' || next === null || next === undefined ) {
			delete draft[ key ];
		} else {
			draft[ key ] = next;
		}
		onChange( draft );
	};

	return (
		<section className="corex-flow-editor__panel">
			<header>
				<div>
					<h2>{ __( 'Protection', 'corex' ) }</h2>
					<p>
						{ __(
							'reCAPTCHA v3 runs invisibly on this form when a provider is configured. Only protected CoreX forms are covered; the honeypot always guards.',
							'corex'
						) }
					</p>
				</div>
			</header>
			<div className="corex-flow-editor__protection">
				<CorexSelect
					label={ __( 'Spam protection', 'corex' ) }
					value={ mode }
					options={ CAPTCHA_MODES }
					onChange={ ( captcha ) =>
						setKey(
							'captcha',
							captcha === 'inherit' ? '' : captcha
						)
					}
				/>
				<div className="corex-field">
					<span>{ __( 'Action override', 'corex' ) }</span>
					<input
						aria-label={ __(
							'reCAPTCHA action override',
							'corex'
						) }
						value={ value.action || '' }
						placeholder={ __(
							'Derived from the form slug',
							'corex'
						) }
						onChange={ ( event ) =>
							setKey( 'action', event.target.value.trim() )
						}
					/>
					<small>
						{ __(
							'Optional. Leave blank to use corex_form_<slug>.',
							'corex'
						) }
					</small>
				</div>
				<div className="corex-field">
					<span>{ __( 'Score threshold override', 'corex' ) }</span>
					<input
						aria-label={ __(
							'reCAPTCHA score threshold override',
							'corex'
						) }
						type="number"
						min="0"
						max="1"
						step="0.05"
						value={
							value.threshold === undefined ? '' : value.threshold
						}
						placeholder={ __( 'Site default (0.3)', 'corex' ) }
						onChange={ ( event ) =>
							setKey(
								'threshold',
								event.target.value === ''
									? ''
									: Number( event.target.value )
							)
						}
					/>
					<small>
						{ __(
							'Optional, 0.0–1.0. Higher rejects more visitors.',
							'corex'
						) }
					</small>
				</div>
			</div>
		</section>
	);
}
