<?php

/**
 * Title: Header — Transparent (over hero)
 * Slug: corex/header-transparent
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A transparent, sticky header that overlays a hero and resolves to a solid, readable background once scrolled (progressive enhancement; readable without JavaScript).
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--transparent","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<header class="wp-block-group corex-header corex-header--transparent" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-header__inner">
		<!-- wp:site-logo {"width":160} /-->

		<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group corex-header__actions">
			<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"}} /-->

			<!-- wp:buttons {"className":"corex-header__cta"} -->
			<div class="wp-block-buttons corex-header__cta"><!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__('Get started', 'corex'); ?></a></div>
			<!-- /wp:button --></div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
