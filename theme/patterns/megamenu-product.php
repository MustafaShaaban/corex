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

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:details {"summary":"<?php echo esc_attr__('Product', 'corex'); ?>","className":"corex-mega corex-mega--product"} -->
<details class="wp-block-details corex-mega corex-mega--product"><summary><?php echo esc_html__('Product', 'corex'); ?></summary>
	<!-- wp:columns {"className":"corex-mega__panel"} -->
	<div class="wp-block-columns corex-mega__panel">
		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('features', __('Features', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('integrations', __('Integrations', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('security', __('Security', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('pricing', __('Pricing', 'corex')); ?></li><!-- /wp:list-item -->
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
			<?php $corex_more_url = corex_pattern_posts_url(); ?>
			<?php if ($corex_more_url !== '') : ?>
			<!-- wp:buttons -->
			<div class="wp-block-buttons"><?php echo corex_pattern_button($corex_more_url, __('Read more', 'corex'), ['fontSize' => 'sm']); ?></div>
			<!-- /wp:buttons -->
			<?php endif; ?>
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</details>
<!-- /wp:details -->
