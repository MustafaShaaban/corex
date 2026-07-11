<?php

/**
 * Title: Header — Sticky
 * Slug: corex/header-sticky
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A solid header that sticks to the top of the viewport and gains a subtle elevation once the page scrolls. Brand, navigation, a search overlay, and a primary call-to-action.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--sticky","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<header class="wp-block-group corex-header corex-header--sticky" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-header__inner">
		<!-- wp:site-logo {"width":150} /-->

		<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group corex-header__actions">
			<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"}} /-->

			<!-- wp:html -->
			<div class="corex-header__search">
				<button type="button" class="corex-header__search-toggle" data-corex-search-toggle aria-controls="corex-header-search" aria-expanded="false" hidden>
					<svg class="corex-header__search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
					<span class="screen-reader-text"><?php echo esc_html__('Search', 'corex'); ?></span>
				</button>
				<div id="corex-header-search" class="corex-header__search-panel" data-corex-search-panel>
					<form role="search" method="get" class="corex-header__search-form" action="<?php echo esc_url(home_url('/')); ?>">
						<label for="corex-header-search-field" class="screen-reader-text"><?php echo esc_html__('Search for:', 'corex'); ?></label>
						<input type="search" id="corex-header-search-field" class="corex-header__search-field" name="s" placeholder="<?php echo esc_attr__('Search…', 'corex'); ?>" />
						<button type="submit" class="corex-header__search-submit"><?php echo esc_html__('Search', 'corex'); ?></button>
					</form>
				</div>
			</div>
			<!-- /wp:html -->

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
