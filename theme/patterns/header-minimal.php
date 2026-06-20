<?php

/**
 * Title: Header — Minimal Landing
 * Slug: corex/header-minimal
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A minimal landing header — brand and a single call-to-action, with reduced navigation for focused campaign pages.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--minimal","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<header class="wp-block-group corex-header corex-header--minimal" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-header__inner">
		<!-- wp:site-logo {"width":150} /-->

		<!-- wp:buttons {"className":"corex-header__cta"} -->
		<div class="wp-block-buttons corex-header__cta"><!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__('Get the app', 'corex'); ?></a></div>
		<!-- /wp:button --></div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
