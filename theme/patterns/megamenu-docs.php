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
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Documentation', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Guides', 'corex'); ?></a></li><!-- /wp:list-item -->
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
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('API reference', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('CLI', 'corex'); ?></a></li><!-- /wp:list-item -->
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
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Support', 'corex'); ?></a></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Changelog', 'corex'); ?></a></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</details>
<!-- /wp:details -->
