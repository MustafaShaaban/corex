/**
 * One record, read (spec 068; spec 108 for how it opens).
 *
 * It opens on the press, with a placeholder where its fields will be, and fills in when the
 * record arrives. It used to open when the record did: "View" did nothing visible for as long as
 * the request took, and could be pressed again.
 *
 * It is a `CorexDialog`. It was WordPress's `Modal`, which is drawn outside the admin's wrapper,
 * so nothing here was styled and none of the admin's tokens reached it: a white box, on the
 * dark theme, with a browser's own definition list in it.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CorexDialog from '../components/CorexDialog.js';
import CorexLoadable from '../components/CorexLoadable.js';
import CorexSkeleton, { SkeletonBar } from '../components/CorexSkeleton.js';
import FieldValue from '../components/FieldValue.js';
import { usePending, workingProps } from '../components/working.js';
import recordRows from './recordRows.js';

/** How many fields the placeholder stands in for; the record decides the real number. */
const PLACEHOLDER_FIELDS = 5;

function FieldsSkeleton() {
	return (
		<CorexSkeleton>
			<dl className="corex-data__fields">
				{ Array.from( { length: PLACEHOLDER_FIELDS }, ( _, field ) => (
					<div key={ field } className="corex-data__field">
						<dt>
							<SkeletonBar width="medium" />
						</dt>
						<dd>
							<SkeletonBar width="long" />
						</dd>
					</div>
				) ) }
			</dl>
		</CorexSkeleton>
	);
}

/**
 * @param {Object}      props          Component props.
 * @param {Object}      props.explorer The Data hook's value.
 * @param {Object|null} props.record   The record, or null while it is on its way.
 * @param {() => void}  props.close    Closes the dialog.
 * @param {() => void}  props.edit     Opens the record for editing.
 * @return {Element} The dialog.
 */
export default function RecordDetail( { explorer, record, close, edit } ) {
	const rows = recordRows( record, explorer.source?.fields );
	const [ pending, during ] = usePending();
	// Nothing can be done to a record that has not arrived.
	const held = pending !== '' || ! record;

	return (
		<CorexDialog
			title={ __( 'Record detail', 'corex' ) }
			onClose={ close }
			busy={ pending !== '' }
			className="corex-data__record-detail"
			footer={
				<div className="corex-data__dialog-actions">
					<Button
						variant="tertiary"
						onClick={ close }
						disabled={ pending !== '' }
					>
						{ __( 'Close', 'corex' ) }
					</Button>
					{ explorer.can( 'update' ) && (
						<Button
							variant="secondary"
							onClick={ edit }
							disabled={ held }
						>
							{ __( 'Edit', 'corex' ) }
						</Button>
					) }
					{ explorer.can( 'delete' ) && (
						<Button
							isDestructive
							variant="secondary"
							disabled={ held }
							onClick={ () =>
								during( 'delete', async () => {
									await explorer.previewMutation( 'delete', [
										record.id,
									] );
									close();
								} )
							}
							{ ...workingProps( pending === 'delete' ) }
						>
							{ __( 'Delete', 'corex' ) }
						</Button>
					) }
				</div>
			}
		>
			<CorexLoadable
				status={ record ? 'ready' : 'loading' }
				skeleton={ <FieldsSkeleton /> }
				loadingLabel={ __( 'Loading the record…', 'corex' ) }
				// A record that cannot be read closes this dialog, and the notice on the
				// page behind it says why: there is no failure to say in here.
				errorMessage=""
			>
				{ rows.length === 0 ? (
					<p className="corex-data__empty">
						{ __( 'This record has no readable fields.', 'corex' ) }
					</p>
				) : (
					<dl className="corex-data__fields">
						{ rows.map( ( row ) => (
							<div key={ row.key } className="corex-data__field">
								<dt>{ row.label }</dt>
								<dd>
									<FieldValue value={ row.value } />
								</dd>
							</div>
						) ) }
					</dl>
				) }
			</CorexLoadable>
		</CorexDialog>
	);
}
