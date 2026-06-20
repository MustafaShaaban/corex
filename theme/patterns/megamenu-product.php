<?php

/**
 * Title: Mega Menu — Product / Features
 * Slug: corex/megamenu-product
 * Categories: corex
 * Description: A product mega menu — feature links plus a featured card and a call-to-action. Native details/summary disclosure; usable with no JavaScript.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:details {"summary":"<?php echo esc_attr__('Product', 'corex'); ?>","className":"corex-mega corex-mega--product"} -->
<details class="wp-block-details corex-mega corex-mega--product"><summary><?php echo esc_html__('Product', 'corex'); ?></summary>
	<!-- wp:columns {"className":"corex-mega__panel"} -->
	<div class="wp-block-columns corex-mega__panel">
		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Features', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Integrations', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Security', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Pricing', 'corex'); ?></a></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"33.33%","className":"corex-mega__featured"} -->
		<div class="wp-block-column corex-mega__featured" style="flex-basis:33.33%">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('What’s new', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"fontSize":"xs"} -->
			<p class="has-xs-font-size"><?php echo esc_html__('See the latest release highlights.', 'corex'); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:buttons -->
			<div class="wp-block-buttons"><!-- wp:button {"fontSize":"sm"} -->
			<div class="wp-block-button has-custom-font-size has-sm-font-size"><a class="wp-block-button__link wp-element-button" href="#"><?php echo esc_html__('Read more', 'corex'); ?></a></div>
			<!-- /wp:button --></div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</details>
<!-- /wp:details -->
