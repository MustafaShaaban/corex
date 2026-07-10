import { __ } from '@wordpress/i18n';

export default function SourceRail( { explorer } ) {
	return <nav className="corex-data__sources" aria-label={ __( 'Data sources', 'corex' ) }>
		<p className="corex-data__rail-kicker">{ __( 'Sources / models', 'corex' ) }</p>
		{ explorer.catalog.map( ( source ) => <button key={ source.key } type="button"
			className={ `corex-data__source-row${ source.key === explorer.state.sourceKey ? ' is-active' : '' }` }
			aria-current={ source.key === explorer.state.sourceKey ? 'page' : undefined }
			disabled={ source.access !== 'allowed' }
			onClick={ () => explorer.dispatch( { type: 'source', sourceKey: source.key } ) }>
			<span className="corex-data__source-dot" aria-hidden="true" />
			<span className="corex-data__source-label">{ source.label }</span>
			<span className="corex-data__source-access">{ source.access === 'allowed' ? __( 'Available', 'corex' ) : __( 'Denied', 'corex' ) }</span>
		</button> ) }
	</nav>;
}
