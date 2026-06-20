<?php

/**
 * Title: Footer — Newsletter
 * Slug: corex/footer-newsletter
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A newsletter footer — a sign-up prompt and call-to-action, then a legal/utility row. The form action is a placeholder; wire it to your provider or the CoreX forms add-on.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--newsletter","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|40"}}} -->
<footer class="wp-block-group corex-footer corex-footer--newsletter" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:group {"className":"corex-footer__newsletter","layout":{"type":"constrained","contentSize":"480px"}} -->
	<div class="wp-block-group corex-footer__newsletter">
		<!-- wp:heading {"level":2,"textAlign":"center","fontSize":"lg"} -->
		<h2 class="wp-block-heading has-text-align-center has-lg-font-size"><?php echo esc_html__('Stay in the loop', 'corex'); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center"><?php echo esc_html__('Occasional updates on releases and guides. No spam.', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
		<div class="wp-block-buttons"><!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__('Subscribe', 'corex'); ?></a></div>
		<!-- /wp:button --></div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"corex-footer__legal","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group corex-footer__legal">
		<!-- wp:corex/copyright /-->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
