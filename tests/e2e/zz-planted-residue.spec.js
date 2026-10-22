/** Planted (spec 116, TC-NFR-09-07): a test that leaves an unmarked submission behind. */
const { test } = require( '@playwright/test' );
const { wpEval } = require( './helpers' );

test( 'a planted test leaves an unmarked submission', () => {
	wpEval(
		"$id = wp_insert_post( [ 'post_type' => 'corex_submission', 'post_status' => 'private', 'post_title' => 'Left by a planted test' ] ); update_post_meta( $id, 'corex_form_slug', 'contact' );"
	);
} );
