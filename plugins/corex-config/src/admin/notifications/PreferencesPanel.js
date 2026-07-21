/**
 * PreferencesPanel — the notification preferences UI (spec 072 US5, FR-020).
 *
 * Lists every category with an in-app toggle, driven by the live REST boundary. Mandatory categories
 * (security / system / operations) render disabled with an explanation — the server enforces this too,
 * so the UI can never mute something the user must see. Each change posts the full map and re-reads
 * the authoritative result.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function PreferencesPanel() {
	const [ status, setStatus ] = useState( 'loading' );
	const [ rows, setRows ] = useState( [] );

	const load = useCallback( () => {
		setStatus( 'loading' );
		apiFetch( { path: '/corex/v1/notifications/preferences' } )
			.then( ( response ) => {
				setRows( response?.data?.preferences ?? [] );
				setStatus( 'ready' );
			} )
			.catch( () => setStatus( 'error' ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const toggle = useCallback(
		( category, enabled ) => {
			// Send the full map so the server has the whole picture; it re-enforces mandatory rules.
			const categories = {};
			rows.forEach( ( row ) => {
				categories[ row.category ] = row.category === category ? enabled : row.enabled;
			} );
			apiFetch( {
				path: '/corex/v1/notifications/preferences',
				method: 'POST',
				data: { categories },
			} )
				.then( ( response ) => setRows( response?.data?.preferences ?? [] ) )
				.catch( () => {} );
		},
		[ rows ]
	);

	if ( status === 'loading' ) {
		return (
			<p className="corex-notifications-screen__state">
				{ __( 'Loading preferences…', 'corex' ) }
			</p>
		);
	}
	if ( status === 'error' ) {
		return (
			<p className="corex-notifications-screen__state" role="alert">
				{ __( 'Preferences could not be loaded.', 'corex' ) }
			</p>
		);
	}

	return (
		<ul className="corex-notifications-prefs">
			{ rows.map( ( row ) => (
				<li key={ row.category } className="corex-notifications-prefs__row">
					<label className="corex-notifications-prefs__label">
						<input
							type="checkbox"
							checked={ row.enabled }
							disabled={ row.mandatory }
							onChange={ ( event ) =>
								toggle( row.category, event.target.checked )
							}
						/>
						<span className="corex-notifications-prefs__name">
							{ row.category }
						</span>
					</label>
					{ row.mandatory && (
						<span className="corex-notifications-prefs__note">
							{ __( 'Always on — required notifications.', 'corex' ) }
						</span>
					) }
				</li>
			) ) }
		</ul>
	);
}
