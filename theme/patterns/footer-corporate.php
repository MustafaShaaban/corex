<?php

/**
 * Title: Footer — Corporate
 * Slug: corex/footer-corporate
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A corporate footer — brand column plus Company, Resources, and Contact link columns, then a legal/utility row.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--corporate","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|50"}}} -->
<footer class="wp-block-group corex-footer corex-footer--corporate" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:columns {"className":"corex-footer__columns"} -->
	<div class="wp-block-columns corex-footer__columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:site-title {"level":2} /-->
			<!-- wp:paragraph -->
			<p><?php echo esc_html__('Professional WordPress, built spec-first.', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Company', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('About', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Careers', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Contact', 'corex'); ?></a></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Resources', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Documentation', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Support', 'corex'); ?></a></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Contact', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php echo esc_html__('hello@example.com', 'corex'); ?></p>
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
