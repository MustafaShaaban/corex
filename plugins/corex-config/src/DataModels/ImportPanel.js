import { useId, useMemo, useState } from '@wordpress/element';
import { Button, CheckboxControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { dataEndpoint } from '../admin/dataClient.js';
import { dataModelsApi, downloadArtifact } from './dataModelsApi.js';
import { actionSources, importSummary } from './modelClient.js';
import SourceSelect from './SourceSelect.js';
import CorexSelect from '../admin/components/CorexSelect.js';

export default function ImportPanel( { config, sources } ) {
	const candidates = useMemo(
		() => actionSources( sources, 'import_dry_run' ),
		[ sources ]
	);
	const [ sourceKey, setSourceKey ] = useState( candidates[ 0 ]?.key || '' );
	const [ file, setFile ] = useState( null );
	const [ run, setRun ] = useState( null );
	const [ mappings, setMappings ] = useState( {} );
	const [ acknowledged, setAcknowledged ] = useState( false );
	const [ status, setStatus ] = useState( '' );
	// The file input is named by its own `for`/`id` pair rather than by being wrapped: a
	// wrapping <label> is not announced by every assistive technology WordPress supports.
	const fileInputId = useId();
	const source = sources.find( ( item ) => item.key === sourceKey );
	const summary = importSummary( run );

	const upload = async () => {
		if ( ! file ) {
			return;
		}
		setStatus( 'loading' );
		try {
			const body = new FormData();
			body.append( 'file', file );
			body.append( 'unknown_policy', 'reject' );
			const response = await fetch(
				dataEndpoint( config.restUrl, sourceKey, 'import' ),
				{
					method: 'POST',
					headers: { 'X-WP-Nonce': config.nonce },
					body,
				}
			);
			const envelope = await response.json();
			if ( ! response.ok || ! envelope.ok ) {
				throw new Error(
					envelope.message ||
						__( 'The CSV could not be validated.', 'corex' )
				);
			}
			setRun( envelope.data.import );
			setMappings(
				Object.fromEntries(
					( envelope.data.import.unknown_columns || [] ).map(
						( key ) => [ key, '' ]
					)
				)
			);
			setAcknowledged( false );
			setStatus( '' );
		} catch ( error ) {
			setStatus(
				error.message ||
					__( 'The CSV could not be validated.', 'corex' )
			);
		}
	};

	const remap = async () => {
		setStatus( 'loading' );
		try {
			const payload = await dataModelsApi(
				config,
				'patch',
				dataEndpoint( config.restUrl, sourceKey, 'import', run.id ),
				{
					mapping: mappings,
					unknown_policy: 'ignore',
				}
			);
			setRun( payload.import );
			setAcknowledged( false );
			setStatus( '' );
		} catch ( error ) {
			setStatus( error.message );
		}
	};

	const commit = async () => {
		setStatus( 'loading' );
		try {
			const payload = await dataModelsApi(
				config,
				'post',
				dataEndpoint(
					config.restUrl,
					sourceKey,
					'import-commit',
					run.id
				),
				{
					input_hash: run.input_hash,
				}
			);
			setRun( payload.import );
			setStatus(
				__( 'The approved rows were queued for import.', 'corex' )
			);
		} catch ( error ) {
			setStatus( error.message );
		}
	};

	const report = async () => {
		try {
			const payload = await dataModelsApi(
				config,
				'get',
				dataEndpoint(
					config.restUrl,
					sourceKey,
					'import-report',
					run.id
				)
			);
			downloadArtifact( { ...payload, mime: 'text/csv' } );
		} catch ( error ) {
			setStatus( error.message );
		}
	};

	// A backstop, not the normal path: the tab is hidden entirely when nothing here is importable
	// (see allowedTabs), so this only shows if a model's capability changed while the page was
	// open. Said in words a person can act on rather than in the word "adapter".
	if ( ! candidates.length ) {
		return (
			<p className="corex-data-models__empty">
				{ __(
					'No model on this site accepts imported records right now. The Models tab lists what each model supports.',
					'corex'
				) }
			</p>
		);
	}

	return (
		<section className="corex-surface corex-data-models__workspace">
			<header>
				<h2>{ __( 'CSV import', 'corex' ) }</h2>
				<p>
					{ __(
						'Upload, map, dry-run, review rejections, then approve the exact accepted rows.',
						'corex'
					) }
				</p>
			</header>
			<SourceSelect
				sources={ candidates }
				value={ sourceKey }
				onChange={ ( key ) => {
					setSourceKey( key );
					setRun( null );
					setFile( null );
					setAcknowledged( false );
					setStatus( '' );
				} }
			/>
			<label className="corex-data-models__file" htmlFor={ fileInputId }>
				{ __( 'CSV file', 'corex' ) }
				<input
					id={ fileInputId }
					type="file"
					accept=".csv,text/csv"
					onChange={ ( event ) =>
						setFile( event.target.files?.[ 0 ] || null )
					}
				/>
			</label>
			<Button
				variant="primary"
				onClick={ upload }
				disabled={ ! file || status === 'loading' }
				isBusy={ status === 'loading' }
			>
				{ __( 'Run dry-run', 'corex' ) }
			</Button>
			{ status && status !== 'loading' && (
				<p role="status">{ status }</p>
			) }
			{ run && (
				<div className="corex-data-models__run">
					<h3>{ __( 'Dry-run evidence', 'corex' ) }</h3>
					<p>
						{ sprintf(
							/* translators: 1: accepted rows, 2: rejected rows, 3: total rows. */
							__(
								'%1$d accepted · %2$d rejected · %3$d total',
								'corex'
							),
							summary.accepted,
							summary.rejected,
							summary.total
						) }
					</p>
					{ summary.unknown.length > 0 && (
						<fieldset>
							<legend>
								{ __( 'Unknown columns', 'corex' ) }
							</legend>
							{ summary.unknown.map( ( column ) => (
								<CorexSelect
									key={ column }
									label={ column }
									value={ mappings[ column ] || '' }
									block
									onChange={ ( value ) =>
										setMappings( ( current ) => ( {
											...current,
											[ column ]: value,
										} ) )
									}
									options={ [
										{
											label: __( 'Ignore', 'corex' ),
											value: '',
										},
										...source.fields
											.filter(
												( field ) => ! field.read_only
											)
											.map( ( field ) => ( {
												label: field.label,
												value: field.key,
											} ) ),
									] }
								/>
							) ) }
							<Button
								variant="secondary"
								onClick={ remap }
								disabled={ status === 'loading' }
							>
								{ __( 'Re-run with mapping', 'corex' ) }
							</Button>
						</fieldset>
					) }
					{ run.rejected_rows?.length > 0 && (
						<>
							<ul>
								{ run.rejected_rows
									.slice( 0, 20 )
									.map( ( row ) => (
										<li key={ row.line }>
											{ sprintf(
												/* translators: 1: CSV line number, 2: rejection reason. */
												__(
													'Line %1$d: %2$s',
													'corex'
												),
												row.line,
												row.reason
											) }
										</li>
									) ) }
							</ul>
							<Button variant="secondary" onClick={ report }>
								{ __( 'Download rejection report', 'corex' ) }
							</Button>
						</>
					) }
					{ run.state === 'valid' &&
						source.actions.import_commit?.visible && (
							<div className="corex-data-models__commit">
								<CheckboxControl
									label={ __(
										'I confirm this exact dry run and understand personal-data fields may be written.',
										'corex'
									) }
									checked={ acknowledged }
									onChange={ setAcknowledged }
								/>
								<Button
									variant="primary"
									disabled={
										! acknowledged || status === 'loading'
									}
									onClick={ commit }
								>
									{ __( 'Commit approved rows', 'corex' ) }
								</Button>
							</div>
						) }
				</div>
			) }
		</section>
	);
}
