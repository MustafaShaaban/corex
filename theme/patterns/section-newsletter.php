<?php

/**
 * Title: Newsletter — Signup
 * Slug: corex/section-newsletter
 * Categories: corex
 * Description: A newsletter signup section — a short heading and supporting line above the CoreX newsletter signup block. Built on core blocks and CoreX brand tokens; neutral placeholder content.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-newsletter","align":"full","backgroundColor":"surface-alt","layout":{"type":"constrained","contentSize":"640px"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|30"}}} -->
<section class="wp-block-group alignfull corex-newsletter has-surface-alt-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:heading {"textAlign":"center","level":2,"fontSize":"2xl"} -->
	<h2 class="wp-block-heading has-text-align-center has-2-xl-font-size"><?php echo esc_html__('Stay in the loop', 'corex'); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","textColor":"ink-soft"} -->
	<p class="has-text-align-center has-ink-soft-color has-text-color"><?php echo esc_html__('Get occasional updates in your inbox. No spam, unsubscribe anytime.', 'corex'); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:corex/newsletter-signup /-->
</section>
<!-- /wp:group -->
