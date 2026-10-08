/**
 * A function that waits for a pause before it is called (spec 108, FR-012).
 *
 * For a search box: what is typed is shown at once, and the server is asked once typing has
 * stopped for `delay` milliseconds, with what the box holds then.
 */
import { useCallback, useEffect, useRef } from '@wordpress/element';

/**
 * @param {(...args: any[]) => void} callback What to call after the pause. The newest one given is the one called.
 * @param {number}                   delay    The pause, in milliseconds.
 * @return {(...args: any[]) => void} Call it on every change; it calls `callback` after the last one.
 */
export function useDebounced( callback, delay ) {
	const timer = useRef( null );
	const latest = useRef( callback );
	latest.current = callback;

	// A request that would be sent after the screen has gone is not sent.
	useEffect( () => () => clearTimeout( timer.current ), [] );

	return useCallback(
		( ...args ) => {
			clearTimeout( timer.current );
			timer.current = setTimeout(
				() => latest.current( ...args ),
				delay
			);
		},
		[ delay ]
	);
}
