import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Modal } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { dataEndpoint } from '../admin/dataClient.js';
import { dataModelsApi } from './dataModelsApi.js';
import { actionSources, migrationState } from './modelClient.js';
import SourceSelect from './SourceSelect.js';

function historyLabel( run ) {
	const states = {
		pending: __( 'Pending', 'corex' ),
		queued: __( 'Queued', 'corex' ),
		applied: __( 'Applied', 'corex' ),
		failed: __( 'Failed', 'corex' ),
		'rolled-back': __( 'Rolled back', 'corex' ),
	};
	/* translators: 1: migration ID, 2: definition key, 3: state. */
	return sprintf(
		__( '#%1$d · %2$s · %3$s', 'corex' ),
		run.id,
		run.definition.key,
		states[ migrationState( run ) ] || migrationState( run )
	);
}

export default function MigrationsPanel( { config, sources } ) {
	const candidates = useMemo( () => actionSources( sources, 'migrations' ), [ sources ] );
	const [ sourceKey, setSourceKey ] = useState( candidates[ 0 ]?.key || '' );
	const [ plans, setPlans ] = useState( [] );
	const [ history, setHistory ] = useState( [] );
	const [ preview, setPreview ] = useState( null );
	const [ notice, setNotice ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( async () => {
		if ( ! sourceKey ) return;
		try {
			const url = `${ dataEndpoint( config.restUrl, '', 'migrations' ) }?source=${ encodeURIComponent( sourceKey ) }`;
			const payload = await dataModelsApi( config, 'get', url );
			setPlans( payload.migrations || [] );
			setHistory( payload.history || [] );
		} catch ( error ) { setNotice( error.message ); }
	}, [ config, sourceKey ] );

	useEffect( () => { load(); }, [ load ] );

	const previewApply = async ( definition ) => {
		setBusy( true );
		try {
			const payload = await dataModelsApi( config, 'post', dataEndpoint( config.restUrl, '', 'migration-preview' ), {
				source: sourceKey, definition, action: 'apply',
			} );
			setPreview( payload.preview );
		} catch ( error ) { setNotice( error.message ); }
		finally { setBusy( false ); }
	};
	const previewRollback = async ( run ) => {
		setBusy( true );
		try {
			const payload = await dataModelsApi( config, 'post', dataEndpoint( config.restUrl, '', 'migration-rollback', run.id ), {} );
			setPreview( payload.preview );
		} catch ( error ) { setNotice( error.message ); }
		finally { setBusy( false ); }
	};
	const confirm = async () => {
		setBusy( true );
		try {
			const endpoint = preview.action === 'rollback'
				? dataEndpoint( config.restUrl, '', 'migration-rollback', preview.run_id )
				: dataEndpoint( config.restUrl, '', 'migration-apply' );
			await dataModelsApi( config, 'post', endpoint, { token: preview.token } );
			setPreview( null );
			setNotice( preview.action === 'rollback' ? __( 'Rollback queued.', 'corex' ) : __( 'Migration queued after snapshot.', 'corex' ) );
			await load();
		} catch ( error ) { setNotice( error.message ); }
		finally { setBusy( false ); }
	};

	if ( ! candidates.length ) return <p className="corex-data-models__empty">{ __( 'No registered model provides a migration adapter.', 'corex' ) }</p>;

	return <section className="corex-surface corex-data-models__workspace">
		<header><h2>{ __( 'Migrations', 'corex' ) }</h2><p>{ __( 'Every apply snapshots first. Transaction and rollback support come from the model provider.', 'corex' ) }</p></header>
		<SourceSelect sources={ candidates } value={ sourceKey } onChange={ ( key ) => { setSourceKey( key ); setPreview( null ); } } />
		{ notice && <p role="status">{ notice }</p> }
		<div className="corex-data-models__migration-list">{ plans.map( ( plan ) => <article key={ plan.key }>
			<h3>{ plan.description }</h3><code>{ plan.key } · { plan.version }</code>
			<ol>{ plan.plan.map( ( step ) => <li key={ step }>{ step }</li> ) }</ol>
			<p>{ plan.transactional ? __( 'Transactional', 'corex' ) : __( 'Non-transactional', 'corex' ) } · { plan.rollback_supported ? __( 'Rollback supported', 'corex' ) : __( 'No rollback', 'corex' ) }</p>
			<Button variant="primary" disabled={ busy } onClick={ () => previewApply( plan.key ) }>{ __( 'Preview migration', 'corex' ) }</Button>
		</article> ) }</div>
		<div className="corex-data-models__history-head"><h3>{ __( 'Migration history', 'corex' ) }</h3>
			<Button variant="secondary" onClick={ load } disabled={ busy }>{ __( 'Refresh', 'corex' ) }</Button></div>
		{ history.length ? <ul className="corex-data-models__history">{ history.map( ( run ) => <li key={ run.id }>
			<span>{ historyLabel( run ) }</span>
			{ run.state === 'applied' && run.definition.rollback_supported && <Button variant="link" isDestructive
				onClick={ () => previewRollback( run ) }>{ __( 'Preview rollback', 'corex' ) }</Button> }
		</li> ) }</ul> : <p>{ __( 'No migration runs yet.', 'corex' ) }</p> }
		{ preview && <Modal title={ preview.action === 'rollback' ? __( 'Confirm rollback', 'corex' ) : __( 'Confirm migration', 'corex' ) }
			onRequestClose={ () => setPreview( null ) }>
			{ preview.production_warning && <p className="corex-data-models__warning">{ __( 'Production warning: this changes stored schema. Review the exact plan before queueing.', 'corex' ) }</p> }
			<ol>{ preview.definition.plan.map( ( step ) => <li key={ step }>{ step }</li> ) }</ol>
			<div className="corex-data__dialog-actions"><Button variant="tertiary" onClick={ () => setPreview( null ) }>{ __( 'Cancel', 'corex' ) }</Button>
				<Button variant="primary" isDestructive={ preview.action === 'rollback' } isBusy={ busy } disabled={ busy } onClick={ confirm }>
					{ preview.action === 'rollback' ? __( 'Queue rollback', 'corex' ) : __( 'Snapshot and queue', 'corex' ) }
				</Button></div>
		</Modal> }
	</section>;
}
