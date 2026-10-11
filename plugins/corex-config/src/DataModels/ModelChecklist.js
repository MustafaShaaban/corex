/**
 * The models to export, as a list to tick (spec 109, slice 2; FR-001 to FR-004).
 *
 * The Export tab had one dropdown named "Model": every model was the same dialog again and a
 * file of its own. The list shows each model with how many records it holds, in two groups, and
 * ticks nothing for the person.
 */
import { useId } from '@wordpress/element';
import { _n, sprintf } from '@wordpress/i18n';
import CorexSkeleton, {
	SkeletonBar,
} from '../admin/components/CorexSkeleton.js';
import { groupedModels, withGroup } from './exportSetState.js';

/**
 * How many records a model holds, in words, or a placeholder while it is being counted.
 *
 * @param {Object}                     props
 * @param {Object<string,number>|null} props.counts Records by model key; null while unknown.
 * @param {string}                     props.model  The model's key.
 * @return {import('react').ReactElement} The count.
 */
function RecordCount( { counts, model } ) {
	if ( ! counts || ! ( model in counts ) ) {
		return (
			<CorexSkeleton as="span">
				<SkeletonBar width="short" />
			</CorexSkeleton>
		);
	}

	return sprintf(
		/* translators: %s: a number of records. */
		_n( '%s record', '%s records', counts[ model ], 'corex' ),
		Number( counts[ model ] ).toLocaleString()
	);
}

/**
 * @param {Object}                     props
 * @param {Array<Object>}              props.models     The models this person may export.
 * @param {string[]}                   props.ticked     The keys of the ticked models.
 * @param {(keys: string[]) => void}   props.setTicked  Receives the keys ticked afterwards.
 * @param {Object<string,number>|null} props.counts     Records by model key; null while unknown.
 * @param {boolean}                    [props.disabled] True while the list cannot be changed.
 * @return {import('react').ReactElement} The list.
 */
export default function ModelChecklist( {
	models,
	ticked,
	setTicked,
	counts,
	disabled = false,
} ) {
	const id = useId();
	const toggle = ( key ) =>
		setTicked(
			ticked.includes( key )
				? ticked.filter( ( item ) => item !== key )
				: [ ...ticked, key ]
		);

	return (
		<div className="corex-data-models__checklist">
			{ groupedModels( models ).map( ( group ) => {
				const everyTicked = group.models.every( ( source ) =>
					ticked.includes( source.key )
				);
				const allId = `${ id }-${ group.key }-all`;

				return (
					<fieldset
						key={ group.key }
						className="corex-data-models__checklist-group"
						disabled={ disabled }
					>
						<legend>{ group.label }</legend>
						<label
							className="corex-data-models__checklist-all"
							htmlFor={ allId }
						>
							<input
								id={ allId }
								type="checkbox"
								checked={ everyTicked }
								onChange={ () =>
									setTicked(
										withGroup(
											ticked,
											group,
											! everyTicked
										)
									)
								}
							/>
							<span>{ group.all }</span>
						</label>
						<ul>
							{ group.models.map( ( source ) => {
								const modelId = `${ id }-${ source.key }`;

								return (
									<li key={ source.key }>
										<label htmlFor={ modelId }>
											<input
												id={ modelId }
												type="checkbox"
												checked={ ticked.includes(
													source.key
												) }
												onChange={ () =>
													toggle( source.key )
												}
												aria-describedby={ `${ modelId }-count` }
											/>
											<span className="corex-data-models__checklist-name">
												{ source.label }
											</span>
										</label>
										<span
											id={ `${ modelId }-count` }
											className="corex-data-models__checklist-count"
										>
											<RecordCount
												counts={ counts }
												model={ source.key }
											/>
										</span>
									</li>
								);
							} ) }
						</ul>
					</fieldset>
				);
			} ) }
		</div>
	);
}
