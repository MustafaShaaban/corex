<?php

/**
 * Title: Footer — Simple
 * Slug: corex/footer-simple
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A simple footer — brand, a short tagline, and a legal/utility row. Built on core blocks and CoreX brand tokens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--simple","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}}} -->
<footer class="wp-block-group corex-footer corex-footer--simple" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:group {"className":"corex-footer__brand","layout":{"type":"flex","orientation":"vertical"}} -->
	<div class="wp-block-group corex-footer__brand">
		<!-- wp:site-title {"level":2} /-->
		<!-- wp:paragraph {"className":"corex-footer__tagline"} -->
		<p class="corex-footer__tagline"><?php echo esc_html__('Discipline, at every layer.', 'corex'); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"corex-footer__legal","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group corex-footer__legal">
		<!-- wp:corex/copyright /-->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
