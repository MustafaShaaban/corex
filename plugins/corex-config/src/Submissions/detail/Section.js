/**
 * One titled part of the detail pane, named for assistive technology by its own heading.
 */
import { useId } from '@wordpress/element';

export default function Section( { title, children, className = '' } ) {
	const headingId = useId();

	return (
		<section
			className={ [ 'corex-pane__section', className ]
				.filter( Boolean )
				.join( ' ' ) }
			aria-labelledby={ headingId }
		>
			<h3 id={ headingId }>{ title }</h3>
			{ children }
		</section>
	);
}
