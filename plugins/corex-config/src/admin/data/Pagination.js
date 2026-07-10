import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

export default function Pagination( { explorer } ) {
	const { state } = explorer;
	const pages = Math.max( 1, Math.ceil( state.total / state.query.perPage ) );
	if ( pages === 1 ) return null;

	return <nav className="corex-data__pagination" aria-label={ __( 'Records pages', 'corex' ) }>
		<Button variant="secondary" disabled={ state.query.page <= 1 }
			onClick={ () => explorer.dispatch( { type: 'query', patch: { page: state.query.page - 1 } } ) }>{ __( 'Previous', 'corex' ) }</Button>
		{ /* translators: 1: current page, 2: total pages. */ }
		<span>{ sprintf( __( 'Page %1$d of %2$d', 'corex' ), state.query.page, pages ) }</span>
		<Button variant="secondary" disabled={ state.query.page >= pages }
			onClick={ () => explorer.dispatch( { type: 'query', patch: { page: state.query.page + 1 } } ) }>{ __( 'Next', 'corex' ) }</Button>
	</nav>;
}
