<?php

/**
 * Title: Loading state
 * Slug: corex/loading
 * Categories: corex
 * Description: An accessible loading placeholder — a polite status message plus static skeleton bars (no animation, so it is reduced-motion safe by construction). Built on core blocks and CoreX brand tokens; muted placeholder blocks only.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-loading","align":"full","layout":{"type":"constrained","contentSize":"640px"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|30"}}} -->
<section class="wp-block-group alignfull corex-loading" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:paragraph {"className":"screen-reader-text"} -->
	<p class="screen-reader-text" role="status" aria-live="polite"><?php echo esc_html__('Loading…', 'corex'); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"className":"corex-skeleton","backgroundColor":"surface-alt","style":{"dimensions":{"minHeight":"1.25rem"},"layout":{"selfStretch":"fixed","flexSize":"40%"}}} -->
	<div class="wp-block-group corex-skeleton has-surface-alt-background-color has-background" style="min-height:1.25rem;max-inline-size:40%"></div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"corex-skeleton","backgroundColor":"surface-alt","style":{"dimensions":{"minHeight":"1.25rem"}}} -->
	<div class="wp-block-group corex-skeleton has-surface-alt-background-color has-background" style="min-height:1.25rem"></div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"corex-skeleton","backgroundColor":"surface-alt","style":{"dimensions":{"minHeight":"1.25rem"}}} -->
	<div class="wp-block-group corex-skeleton has-surface-alt-background-color has-background" style="min-height:1.25rem"></div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"corex-skeleton","backgroundColor":"surface-alt","style":{"dimensions":{"minHeight":"1.25rem"}}} -->
	<div class="wp-block-group corex-skeleton has-surface-alt-background-color has-background" style="min-height:1.25rem;max-inline-size:80%"></div>
	<!-- /wp:group -->
</section>
<!-- /wp:group -->
