<?php

/**
 * Title: Footer — Legal / Utility
 * Slug: corex/footer-legal
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A minimal legal/utility footer — copyright plus legal links in a single row.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--legal","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}}} -->
<footer class="wp-block-group corex-footer corex-footer--legal" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:group {"className":"corex-footer__legal","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-footer__legal">
		<!-- wp:corex/copyright /-->

		<!-- wp:list {"className":"corex-footer__legal-links"} -->
		<ul class="wp-block-list corex-footer__legal-links">
			<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Privacy', 'corex'); ?></a></li><!-- /wp:list-item -->
			<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Terms', 'corex'); ?></a></li><!-- /wp:list-item -->
			<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Cookies', 'corex'); ?></a></li><!-- /wp:list-item -->
		</ul>
		<!-- /wp:list -->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
