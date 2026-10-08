import { __ } from '@wordpress/i18n';
import { dataEndpoint } from '../admin/dataClient.js';

export async function dataModelsApi( config, method, url, payload ) {
	const response =
		method === 'get'
			? await window.Corex.api.get( url, { nonce: config.nonce } )
			: await window.Corex.api[ method ]( url, payload, {
					nonce: config.nonce,
				} );
	if ( ! response?.envelope?.ok ) {
		throw new Error(
			response?.envelope?.message || __( 'The request failed.', 'corex' )
		);
	}

	return response.envelope.data;
}

export function downloadArtifact( artifact ) {
	const content =
		artifact.encoding === 'base64'
			? Uint8Array.from( window.atob( artifact.content ), ( value ) =>
					value.charCodeAt( 0 )
				)
			: artifact.content;
	const url = URL.createObjectURL(
		new Blob( [ content ], { type: artifact.mime } )
	);
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = artifact.filename;
	link.click();
	URL.revokeObjectURL( url );
}

/**
 * The requests an export of one source is made of. Each resolves to the answer's data and throws
 * with the server's words when the answer is a refusal.
 *
 * @param {Object} config    The screen's `{restUrl, nonce}`.
 * @param {string} sourceKey The source to export.
 * @return {{preview:Function,create:Function,advance:Function,download:Function,history:Function}} The requests.
 */
export function dataExportRequests( config, sourceKey ) {
	const at = ( action, id = null ) =>
		dataEndpoint( config.restUrl, sourceKey, action, id );

	return {
		preview: ( payload ) =>
			dataModelsApi( config, 'post', at( 'export-preview' ), payload ),
		create: ( payload ) =>
			dataModelsApi( config, 'post', at( 'export' ), payload ),
		advance: ( id ) =>
			dataModelsApi( config, 'post', at( 'export-advance', id ), {} ),
		download: ( id ) =>
			dataModelsApi( config, 'get', at( 'export-download', id ) ),
		history: () => dataModelsApi( config, 'get', at( 'export' ) ),
	};
}
