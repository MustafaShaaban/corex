<?php

/**
 * Title: Mega menu panel: Product
 * Slug: corex/megamenu-product
 * Categories: corex
 * Block Types: core/template-part/mega-menu
 * Inserter: no
 * Description: The content of a mega menu panel: product links beside a card for what is new. To use it, turn on the CoreX UI add-on, add a Mega menu item to a Navigation block, and in the item's settings, under New panel, choose this pattern and select Create panel.
 *
 * A panel's content only (spec 137, FR-019): the panel is a template part of the
 * `mega-menu` area, styled by the corex-ui mega menu item where it opens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
$corex_more_url = corex_pattern_posts_url();
?>
<!-- wp:group {"className":"corex-megamenu corex-megamenu--product"} -->
<div class="wp-block-group corex-megamenu corex-megamenu--product">
	<!-- wp:group {"className":"corex-megamenu__list"} -->
	<div class="wp-block-group corex-megamenu__list">
		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('features', __('Features', 'corex')); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('integrations', __('Integrations', 'corex')); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('security', __('Security', 'corex')); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('pricing', __('Pricing', 'corex')); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"corex-megamenu__feature"} -->
	<div class="wp-block-group corex-megamenu__feature">
		<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
		<p class="corex-megamenu__title"><?php echo esc_html__('What’s new', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"corex-megamenu__text"} -->
		<p class="corex-megamenu__text"><?php echo esc_html__('See the latest release highlights.', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<?php if ($corex_more_url !== '') : ?>
		<!-- wp:buttons -->
		<div class="wp-block-buttons"><?php echo corex_pattern_button($corex_more_url, __('Read more', 'corex'), ['fontSize' => 'sm']); ?></div>
		<!-- /wp:buttons -->
		<?php endif; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
