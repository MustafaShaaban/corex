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
import CorexErrorState from '../components/CorexErrorState.js';
import CorexLoadable from '../components/CorexLoadable.js';
import { workingProps } from '../components/working.js';
import CorexSkeleton, {
	SkeletonBar,
	SkeletonBox,
} from '../components/CorexSkeleton.js';

/** How many rows the placeholder stands in for; the server decides the real number. */
const PLACEHOLDER_ROWS = 6;

function PreferencesSkeleton() {
	return (
		<CorexSkeleton>
			<ul className="corex-notifications-prefs">
				{ Array.from( { length: PLACEHOLDER_ROWS }, ( _, index ) => (
					<li
						key={ index }
						className="corex-notifications-prefs__row"
					>
						<span className="corex-notifications-prefs__label">
							<SkeletonBox />
							<SkeletonBar width="short" />
						</span>
					</li>
				) ) }
			</ul>
		</CorexSkeleton>
	);
}

export default function PreferencesPanel() {
	const [ status, setStatus ] = useState( 'loading' );
	const [ rows, setRows ] = useState( [] );
	// The category whose change is on its way, and what was said if the last one failed.
	const [ saving, setSaving ] = useState( '' );
	const [ failure, setFailure ] = useState( '' );

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
				categories[ row.category ] =
					row.category === category ? enabled : row.enabled;
			} );
			setFailure( '' );
			setSaving( category );
			apiFetch( {
				path: '/corex/v1/notifications/preferences',
				method: 'POST',
				data: { categories },
			} )
				.then( ( response ) =>
					setRows( response?.data?.preferences ?? [] )
				)
				// It used to fail in silence: the box went back to what it was, unexplained.
				.catch( ( reason ) =>
					setFailure(
						reason?.message ||
							__( 'That preference could not be saved.', 'corex' )
					)
				)
				.finally( () => setSaving( '' ) );
		},
		[ rows ]
	);

	return (
		<CorexLoadable
			status={ status }
			skeleton={ <PreferencesSkeleton /> }
			loadingLabel={ __( 'Loading preferences…', 'corex' ) }
			errorMessage={ __( 'Preferences could not be loaded.', 'corex' ) }
			onRetry={ load }
		>
			{ failure && (
				<CorexErrorState scale="action" message={ failure } />
			) }
			<PreferenceRows
				rows={ rows }
				saving={ saving }
				onToggle={ toggle }
			/>
		</CorexLoadable>
	);
}

function PreferenceRows( { rows, saving, onToggle } ) {
	return (
		<ul className="corex-notifications-prefs">
			{ rows.map( ( row ) => (
				<li
					key={ row.category }
					className="corex-notifications-prefs__row"
				>
					{ /* The category names the control, so the label points at it by `for`
					     rather than wrapping it. */ }
					<label
						className="corex-notifications-prefs__label"
						htmlFor={ `corex-notification-pref-${ row.category }` }
					>
						<input
							id={ `corex-notification-pref-${ row.category }` }
							type="checkbox"
							checked={ row.enabled }
							// Each change sends every category, read from what is on screen, so
							// a second sent before the first has answered would undo it.
							disabled={ row.mandatory || Boolean( saving ) }
							{ ...workingProps( saving === row.category ) }
							onChange={ ( event ) =>
								onToggle( row.category, event.target.checked )
							}
						/>
						<span className="corex-notifications-prefs__name">
							{ row.category }
						</span>
					</label>
					{ row.mandatory && (
						<span className="corex-notifications-prefs__note">
							{ __(
								'Always on — required notifications.',
								'corex'
							) }
						</span>
					) }
				</li>
			) ) }
		</ul>
	);
}
