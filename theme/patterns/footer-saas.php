<?php

/**
 * Title: Footer — SaaS / Product
 * Slug: corex/footer-saas
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A product footer — Product, Company, and Resources link columns plus social links, then a legal/utility row.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--saas","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|50"}}} -->
<footer class="wp-block-group corex-footer corex-footer--saas" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:columns {"className":"corex-footer__columns"} -->
	<div class="wp-block-columns corex-footer__columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Product', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Features', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Pricing', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Changelog', 'corex'); ?></a></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
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
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Blog', 'corex'); ?></a></li><!-- /wp:list-item -->
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
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('API', 'corex'); ?></a></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

	<!-- wp:group {"className":"corex-footer__legal","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group corex-footer__legal">
		<!-- wp:corex/copyright /-->

		<!-- wp:social-links {"className":"corex-footer__social"} -->
		<ul class="wp-block-social-links corex-footer__social"><!-- wp:social-link {"url":"#","service":"github"} /-->
		<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
		<!-- /wp:social-links -->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
