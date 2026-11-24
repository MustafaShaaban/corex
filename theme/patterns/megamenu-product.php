<?php

/**
 * Title: Mega menu panel: Product
 * Slug: corex/megamenu-product
 * Categories: corex
 * Block Types: core/template-part/mega-menu
 * Inserter: no
 * Description: The content of a mega menu panel: product links beside a card for a guide. To use it, turn on the CoreX UI add-on, add a Mega menu item to a Navigation block, and in the item's settings, under New panel, choose this pattern and select Create panel.
 *
 * A panel's content only (spec 137, FR-019): the panel is a template part of the
 * `mega-menu` area, styled by the corex-ui mega menu item where it opens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
$corex_guide_url = corex_pattern_page_url('guides');
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
		<!-- wp:paragraph {"className":"corex-eyebrow"} -->
		<p class="corex-eyebrow"><?php echo esc_html__('Guide', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
		<p class="corex-megamenu__title"><?php echo esc_html__('How to brief a web project', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"corex-megamenu__text"} -->
		<p class="corex-megamenu__text"><?php echo esc_html__('Six questions to answer before you ask for a quote.', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<?php if ($corex_guide_url !== '') : ?>
		<!-- wp:paragraph {"className":"corex-megamenu__more"} -->
		<p class="corex-megamenu__more"><a class="corex-link--standalone" href="<?php echo esc_url($corex_guide_url); ?>"><?php echo esc_html__('Read the guide', 'corex'); ?></a></p>
		<!-- /wp:paragraph -->
		<?php endif; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
