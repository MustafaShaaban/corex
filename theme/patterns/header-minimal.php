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

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--minimal","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<header class="wp-block-group corex-header corex-header--minimal" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-header__inner">
		<!-- wp:site-logo {"width":150} /-->

		<?php $corex_cta_url = corex_pattern_action_url(); ?>
		<?php if ($corex_cta_url !== '') : ?>
		<!-- wp:buttons {"className":"corex-header__cta"} -->
		<div class="wp-block-buttons corex-header__cta"><?php echo corex_pattern_button($corex_cta_url, __('Get the app', 'corex')); ?></div>
		<!-- /wp:buttons -->
		<?php endif; ?>
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
