<?php

/**
 * Title: Footer — Locations
 * Slug: corex/footer-locations
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A locations footer — one column per office/branch with address details, then a legal/utility row.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--locations","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|50"}}} -->
<footer class="wp-block-group corex-footer corex-footer--locations" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:columns {"className":"corex-footer__columns"} -->
	<div class="wp-block-columns corex-footer__columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Cairo', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php echo esc_html__('123 Example Street', 'corex'); ?><br><?php echo esc_html__('Cairo, Egypt', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Dubai', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php echo esc_html__('456 Example Road', 'corex'); ?><br><?php echo esc_html__('Dubai, UAE', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('London', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php echo esc_html__('789 Example Avenue', 'corex'); ?><br><?php echo esc_html__('London, UK', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:group {"className":"corex-footer__legal","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group corex-footer__legal">
		<!-- wp:corex/copyright /-->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
