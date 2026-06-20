<?php

/**
 * Title: Header — SaaS / Product
 * Slug: corex/header-saas
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A product header — brand, primary navigation with a features mega menu, and a secondary link plus a primary call-to-action.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--saas","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<header class="wp-block-group corex-header corex-header--saas" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-header__inner">
		<!-- wp:site-logo {"width":150} /-->

		<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group corex-header__actions">
			<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"}} /-->

			<!-- wp:details {"summary":"<?php echo esc_attr__('Product', 'corex'); ?>","className":"corex-mega corex-mega--product"} -->
			<details class="wp-block-details corex-mega corex-mega--product"><summary><?php echo esc_html__('Product', 'corex'); ?></summary>
				<!-- wp:group {"className":"corex-mega__panel"} -->
				<div class="wp-block-group corex-mega__panel">
					<!-- wp:list -->
					<ul class="wp-block-list">
						<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Features', 'corex'); ?></a></li><!-- /wp:list-item -->
						<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Integrations', 'corex'); ?></a></li><!-- /wp:list-item -->
						<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Pricing', 'corex'); ?></a></li><!-- /wp:list-item -->
					</ul>
					<!-- /wp:list -->
				</div>
				<!-- /wp:group -->
			</details>
			<!-- /wp:details -->

			<!-- wp:buttons {"className":"corex-header__cta","layout":{"type":"flex","verticalAlignment":"center"}} -->
			<div class="wp-block-buttons corex-header__cta"><!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__('Sign in', 'corex'); ?></a></div>
			<!-- /wp:button -->

			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__('Start free', 'corex'); ?></a></div>
			<!-- /wp:button --></div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
