import { __ } from '@wordpress/i18n';

export default function Schema( { source } ) {
	return <section className="corex-data__schema" aria-labelledby="corex-data-schema-title">
		<p id="corex-data-schema-title" className="corex-data__rail-kicker">{ __( 'Schema', 'corex' ) }</p>
		{ source?.fields?.length ? <ul className="corex-data__schema-list">
			{ source.fields.map( ( field ) => <li key={ field.key } className="corex-data__schema-field">
				<span className="corex-data__schema-name">{ field.label }</span>
				<code className="corex-data__schema-type">{ field.type }</code>
			</li> ) }
		</ul> : <p className="corex-data__rail-empty">{ __( 'No accessible schema fields.', 'corex' ) }</p> }
	</section>;
}
