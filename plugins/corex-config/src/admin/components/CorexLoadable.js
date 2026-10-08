/**
 * CorexLoadable — what a surface that asks the server for its content is wrapped in (spec 108).
 *
 * A surface is in one of four states and a person has to be able to tell them apart:
 *
 * - `loading`    nothing has arrived: the surface's placeholder, never its empty state;
 * - `refreshing` what is here is about to be replaced: it stays, dimmed and out of reach;
 * - `ready`      this is the answer;
 * - `error`      there is no answer, and a way to ask again if the surface gave one.
 *
 * The state is on the element as `data-corex-state`, the one signal a test waits on, the same on
 * every surface.
 *
 * A screen reader is told about a load that has lasted, at its start and at its end, and nothing
 * about one that was over before it was worth saying, or about a refresh. The sentences are in a
 * region outside the part marked busy: assistive technology may hold back what a busy element
 * says until it stops being busy, which is the opposite of what the first sentence is for.
 */
import {
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CorexErrorState from './CorexErrorState.js';

/** How long a load lasts before it is worth interrupting a screen reader to say so. */
const ANNOUNCE_AFTER_MS = 1000;

/**
 * The sentence for a load that lasted, and the one for its end.
 *
 * @param {string} status       The surface's state.
 * @param {string} loadingLabel The surface's own loading sentence.
 * @return {string} What the live region says now.
 */
function useLoadAnnouncement( status, loadingLabel ) {
	const [ announcement, setAnnouncement ] = useState( '' );
	const saidLoading = useRef( false );

	useEffect( () => {
		if ( status === 'loading' ) {
			const timer = setTimeout( () => {
				saidLoading.current = true;
				setAnnouncement( loadingLabel );
			}, ANNOUNCE_AFTER_MS );
			return () => clearTimeout( timer );
		}
		if ( saidLoading.current ) {
			saidLoading.current = false;
			// A failure announces itself, through the error state.
			setAnnouncement(
				status === 'ready' ? __( 'Loaded.', 'corex' ) : ''
			);
		}
		return undefined;
	}, [ status, loadingLabel ] );

	return announcement;
}

/**
 * Content that turns inert drops the focus it held, and the browser's answer is the top of the
 * page. The surface takes it instead, so the next Tab goes back into the content.
 *
 * @param {string} status The surface's state.
 * @return {Object} The ref the surface is given.
 */
function useFocusKeptOnSurface( status ) {
	const surface = useRef( null );

	useLayoutEffect( () => {
		const element = surface.current;
		if (
			status === 'refreshing' &&
			element.contains( element.ownerDocument.activeElement )
		) {
			element.focus();
		}
	}, [ status ] );

	return surface;
}

/**
 * @param {Object}     props              Component props.
 * @param {string}     props.status       `loading`, `refreshing`, `ready` or `error`.
 * @param {Element}    props.skeleton     The surface's placeholder, a `CorexSkeleton`.
 * @param {string}     props.loadingLabel The surface's loading sentence, for a screen reader.
 * @param {string}     props.errorMessage What to say when there is no answer.
 * @param {() => void} [props.onRetry]    Asks again. Left out where asking again cannot help.
 * @param {Element}    props.children     The content, including its empty state.
 * @return {Element} The surface.
 */
export default function CorexLoadable( {
	status,
	skeleton,
	loadingLabel,
	errorMessage,
	onRetry,
	children,
} ) {
	const announcement = useLoadAnnouncement( status, loadingLabel );
	const surface = useFocusKeptOnSurface( status );
	const refreshing = status === 'refreshing';

	return (
		<div
			ref={ surface }
			className="corex-loadable"
			data-corex-state={ status }
			tabIndex={ -1 }
		>
			<p className="screen-reader-text" role="status">
				{ announcement }
			</p>
			{ status === 'error' ? (
				<CorexErrorState
					scale="panel"
					message={ errorMessage }
					onRetry={ onRetry }
				/>
			) : (
				<div
					className="corex-loadable__body"
					aria-busy={
						status === 'loading' || refreshing ? 'true' : undefined
					}
					// React 18 does not know `inert` as a boolean attribute: an empty string
					// is how it is switched on, and `undefined` how it is left off.
					inert={ refreshing ? '' : undefined }
				>
					{ status === 'loading' ? skeleton : children }
				</div>
			) }
		</div>
	);
}
