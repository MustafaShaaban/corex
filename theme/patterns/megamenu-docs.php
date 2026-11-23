<?php

/**
 * Title: Mega menu panel: Docs and resources
 * Slug: corex/megamenu-docs
 * Categories: corex
 * Block Types: core/template-part/mega-menu
 * Description: The content of a mega menu panel: resource links in three groups. To use it, turn on the CoreX UI add-on, add a Mega menu item to a Navigation block, and in the item's settings, under New panel, choose this pattern and select Create panel.
 *
 * A panel's content only (spec 137, FR-019): the panel is a template part of the
 * `mega-menu` area, styled by the corex-ui mega menu item where it opens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:group {"className":"corex-megamenu corex-megamenu--docs"} -->
<div class="wp-block-group corex-megamenu corex-megamenu--docs">
	<!-- wp:group {"className":"corex-megamenu__list"} -->
	<div class="wp-block-group corex-megamenu__list">
		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo esc_html__('Learn', 'corex'); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:list {"className":"corex-megamenu__links"} -->
			<ul class="wp-block-list corex-megamenu__links">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('docs', __('Documentation', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('guides', __('Guides', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo esc_html__('Develop', 'corex'); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:list {"className":"corex-megamenu__links"} -->
			<ul class="wp-block-list corex-megamenu__links">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('api', __('API reference', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('cli', __('CLI', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo esc_html__('Community', 'corex'); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:list {"className":"corex-megamenu__links"} -->
			<ul class="wp-block-list corex-megamenu__links">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('support', __('Support', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('changelog', __('Changelog', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
