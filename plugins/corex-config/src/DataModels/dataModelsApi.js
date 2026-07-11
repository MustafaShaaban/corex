import { __ } from '@wordpress/i18n';

export async function dataModelsApi( config, method, url, payload ) {
	const response = method === 'get'
		? await window.Corex.api.get( url, { nonce: config.nonce } )
		: await window.Corex.api[ method ]( url, payload, { nonce: config.nonce } );
	if ( ! response?.envelope?.ok ) {
		throw new Error( response?.envelope?.message || __( 'The request failed.', 'corex' ) );
	}

	return response.envelope.data;
}

export function downloadArtifact( artifact ) {
	const content = artifact.encoding === 'base64'
		? Uint8Array.from( window.atob( artifact.content ), ( value ) => value.charCodeAt( 0 ) )
		: artifact.content;
	const url = URL.createObjectURL( new Blob( [ content ], { type: artifact.mime } ) );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = artifact.filename;
	link.click();
	URL.revokeObjectURL( url );
}
