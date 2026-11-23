<?php

/**
 * Title: Mega menu panel: Simple links
 * Slug: corex/megamenu-simple
 * Categories: corex
 * Block Types: core/template-part/mega-menu
 * Description: The content of a mega menu panel: a short list of links. To use it, turn on the CoreX UI add-on, add a Mega menu item to a Navigation block, and in the item's settings, under New panel, choose this pattern and select Create panel.
 *
 * A panel's content only (spec 137, FR-019): the panel is a template part of the
 * `mega-menu` area, styled by the corex-ui mega menu item where it opens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:group {"className":"corex-megamenu corex-megamenu--simple"} -->
<div class="wp-block-group corex-megamenu corex-megamenu--simple">
	<!-- wp:group {"className":"corex-megamenu__list"} -->
	<div class="wp-block-group corex-megamenu__list">
		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('about', __('About', 'corex')); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('careers', __('Careers', 'corex')); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('contact', __('Contact', 'corex')); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
