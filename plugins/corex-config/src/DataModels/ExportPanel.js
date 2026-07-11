import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, CheckboxControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { dataEndpoint } from '../admin/dataClient.js';
import { dataModelsApi, downloadArtifact } from './dataModelsApi.js';
import { actionSources } from './modelClient.js';
import SourceSelect from './SourceSelect.js';

function exportRunLabel( run ) {
	const scopes = {
		all: __( 'All accessible', 'corex' ),
		filtered: __( 'Current filters', 'corex' ),
		selected: __( 'Selected rows', 'corex' ),
	};
	const states = {
		queued: __( 'Queued', 'corex' ),
		completed: __( 'Completed', 'corex' ),
	};
	/* translators: 1: export ID, 2: scope, 3: format, 4: state. */
	return sprintf(
		__( '#%1$d · %2$s · %3$s · %4$s', 'corex' ),
		run.id,
		scopes[ run.scope ] || run.scope,
		run.format.toUpperCase(),
		states[ run.state ] || run.state
	);
}

export default function ExportPanel( { config, sources } ) {
	const candidates = useMemo( () => actionSources( sources, 'export_csv' ), [ sources ] );
	const [ sourceKey, setSourceKey ] = useState( candidates[ 0 ]?.key || '' );
	const source = sources.find( ( item ) => item.key === sourceKey );
	const [ columns, setColumns ] = useState( source?.fields.map( ( field ) => field.key ) || [] );
	const [ format, setFormat ] = useState( 'csv' );
	const [ acknowledged, setAcknowledged ] = useState( false );
	const [ history, setHistory ] = useState( [] );
	const [ notice, setNotice ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( async () => {
		if ( ! sourceKey ) return;
		try {
			const payload = await dataModelsApi( config, 'get', dataEndpoint( config.restUrl, sourceKey, 'export' ) );
			setHistory( payload.exports || [] );
		} catch ( error ) { setNotice( error.message ); }
	}, [ config, sourceKey ] );

	useEffect( () => { load(); }, [ load ] );
	useEffect( () => {
		setColumns( source?.fields.map( ( field ) => field.key ) || [] );
		setFormat( 'csv' );
		setAcknowledged( false );
	}, [ source ] );

	const personal = source?.fields.some( ( field ) =>
		columns.includes( field.key ) && field.personal_data_class !== 'none'
	);
	const submit = async () => {
		setBusy( true );
		try {
			await dataModelsApi( config, 'post', dataEndpoint( config.restUrl, sourceKey, 'export' ), {
				scope: 'all', selected_ids: [], query: {}, columns, format,
				personal_data_acknowledged: acknowledged,
			} );
			setNotice( __( 'Export queued. Refresh history when the job completes.', 'corex' ) );
			await load();
		} catch ( error ) { setNotice( error.message ); }
		finally { setBusy( false ); }
	};
	const download = async ( run ) => {
		setBusy( true );
		try {
			const payload = await dataModelsApi( config, 'get', dataEndpoint( config.restUrl, sourceKey, 'export-download', run.id ) );
			const artifact = payload.artifact;
			if ( artifact.encoding === 'base64' ) downloadArtifact( artifact );
		} catch ( error ) { setNotice( error.message ); }
		finally { setBusy( false ); }
	};

	if ( ! candidates.length ) return <p className="corex-data-models__empty">{ __( 'No registered model provides an export adapter.', 'corex' ) }</p>;

	return <section className="corex-surface corex-data-models__workspace">
		<header><h2>{ __( 'Model exports', 'corex' ) }</h2><p>{ __( 'Choose only the fields you need. Export history remains scoped to your account.', 'corex' ) }</p></header>
		<SourceSelect sources={ candidates } value={ sourceKey } onChange={ setSourceKey } />
		<fieldset className="corex-data-models__columns"><legend>{ __( 'Columns', 'corex' ) }</legend>
			{ source?.fields.map( ( field ) => <CheckboxControl key={ field.key } label={ field.label }
				checked={ columns.includes( field.key ) } onChange={ ( checked ) => setColumns( ( current ) =>
					checked ? [ ...current, field.key ] : current.filter( ( key ) => key !== field.key )
				) } /> ) }
		</fieldset>
		<SelectControl label={ __( 'Format', 'corex' ) } value={ format } onChange={ setFormat } options={ [
			{ label: __( 'CSV', 'corex' ), value: 'csv' },
			{ label: __( 'XLSX', 'corex' ), value: 'xlsx', disabled: ! source?.actions.export_xlsx?.visible },
		] } />
		{ personal && <CheckboxControl label={ __( 'I acknowledge this export contains personal data.', 'corex' ) }
			checked={ acknowledged } onChange={ setAcknowledged } /> }
		<Button variant="primary" isBusy={ busy } onClick={ submit }
			disabled={ busy || ! columns.length || ( personal && ! acknowledged ) }>{ __( 'Queue export', 'corex' ) }</Button>
		{ notice && <p role="status">{ notice }</p> }
		<div className="corex-data-models__history-head"><h3>{ __( 'Export history', 'corex' ) }</h3>
			<Button variant="secondary" onClick={ load } disabled={ busy }>{ __( 'Refresh', 'corex' ) }</Button></div>
		{ history.length ? <ul className="corex-data-models__history">{ history.map( ( run ) => <li key={ run.id }>
			<span>{ exportRunLabel( run ) }</span>
			{ run.state === 'completed' && <Button variant="link" onClick={ () => download( run ) }>{ __( 'Download', 'corex' ) }</Button> }
		</li> ) }</ul> : <p>{ __( 'No exports yet.', 'corex' ) }</p> }
	</section>;
}
