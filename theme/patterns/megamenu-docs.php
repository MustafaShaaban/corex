<?php

/**
 * Title: Mega Menu — Docs / Resources
 * Slug: corex/megamenu-docs
 * Categories: corex
 * Description: A docs/resources mega menu — grouped resource links across columns. Native details/summary disclosure; usable with no JavaScript.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:details {"summary":"<?php echo esc_attr__('Resources', 'corex'); ?>","className":"corex-mega corex-mega--docs"} -->
<details class="wp-block-details corex-mega corex-mega--docs"><summary><?php echo esc_html__('Resources', 'corex'); ?></summary>
	<!-- wp:columns {"className":"corex-mega__panel"} -->
	<div class="wp-block-columns corex-mega__panel">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"xs"} -->
			<h3 class="wp-block-heading has-xs-font-size"><?php echo esc_html__('Learn', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('docs', __('Documentation', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('guides', __('Guides', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"xs"} -->
			<h3 class="wp-block-heading has-xs-font-size"><?php echo esc_html__('Develop', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('api', __('API reference', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('cli', __('CLI', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"xs"} -->
			<h3 class="wp-block-heading has-xs-font-size"><?php echo esc_html__('Community', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('support', __('Support', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('changelog', __('Changelog', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</details>
<!-- /wp:details -->
