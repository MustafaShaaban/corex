<?php

/**
 * Title: Selected Work
 * Slug: corex/section-selected-work
 * Categories: corex
 * Description: A section header and a grid of recent portfolio projects (the dynamic corex/projects block — real projects, or an honest empty state), plus a link to the full projects archive.
 * Block Types: core/post-content
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-selected-work","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|50"}}} -->
<section class="wp-block-group corex-selected-work" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"className":"corex-selected-work__head","layout":{"type":"constrained","contentSize":"640px"}} -->
	<div class="wp-block-group corex-selected-work__head">
		<!-- wp:paragraph {"className":"corex-eyebrow","fontSize":"sm"} -->
		<p class="corex-eyebrow has-sm-font-size"><?php echo esc_html__('SELECTED WORK', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":2,"fontSize":"2xl"} -->
		<h2 class="wp-block-heading has-2-xl-font-size"><?php echo esc_html__('Recent projects', 'corex'); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"textColor":"ink-soft"} -->
		<p class="has-ink-soft-color has-text-color"><?php echo esc_html__('A selection of recent work. Publish projects to fill this grid — it shows real projects or an honest empty state.', 'corex'); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:corex/projects {"count":6} /-->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/projects/"><?php echo esc_html__('View all projects', 'corex'); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
