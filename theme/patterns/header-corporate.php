<?php

/**
 * Title: Header — Corporate (top bar)
 * Slug: corex/header-corporate
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A corporate header — a top utility bar (contact / language) above the main brand, navigation, and call-to-action row.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--corporate","layout":{"type":"default"}} -->
<header class="wp-block-group corex-header corex-header--corporate">
	<!-- wp:group {"className":"corex-header__topbar","layout":{"type":"constrained"}} -->
	<div class="wp-block-group corex-header__topbar">
		<!-- wp:group {"layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} -->
		<div class="wp-block-group">
			<!-- wp:paragraph {"fontSize":"xs"} -->
			<p class="has-xs-font-size"><?php echo esc_html__('hello@example.com', 'corex'); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"corex-header__lang","fontSize":"xs"} -->
			<p class="corex-header__lang has-xs-font-size"><a href="#" aria-label="<?php echo esc_attr__('Change language', 'corex'); ?>"><?php echo esc_html__('EN', 'corex'); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"corex-header__main","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
	<div class="wp-block-group corex-header__main" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
		<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group corex-header__inner">
			<!-- wp:site-logo {"width":160} /-->

			<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
			<div class="wp-block-group corex-header__actions">
				<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"}} /-->

				<!-- wp:buttons {"className":"corex-header__cta"} -->
				<div class="wp-block-buttons corex-header__cta"><!-- wp:button -->
				<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__('Contact us', 'corex'); ?></a></div>
				<!-- /wp:button --></div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
