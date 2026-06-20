<?php

/**
 * Title: Header — Docs
 * Slug: corex/header-docs
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A documentation header — brand, navigation, and a search slot (the native search block). A neutral section/version slot can be added per site.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--docs","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<header class="wp-block-group corex-header corex-header--docs" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-header__inner">
		<!-- wp:site-logo {"width":140} /-->

		<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group corex-header__actions">
			<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"}} /-->

			<!-- wp:search {"label":"<?php echo esc_attr__('Search docs', 'corex'); ?>","showLabel":false,"placeholder":"<?php echo esc_attr__('Search docs…', 'corex'); ?>","buttonText":"<?php echo esc_attr__('Search', 'corex'); ?>","className":"corex-header__search"} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
