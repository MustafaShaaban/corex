<?php

/**
 * Title: Mega menu panel: Services
 * Slug: corex/megamenu-services
 * Categories: corex
 * Block Types: core/template-part/mega-menu
 * Inserter: no
 * Description: The content of a mega menu panel: services, each a link with a line of text. To use it, turn on the CoreX UI add-on, add a Mega menu item to a Navigation block, and in the item's settings, under New panel, choose this pattern and select Create panel.
 *
 * A panel's content only (spec 137, FR-019): the panel is a template part of the
 * `mega-menu` area, styled by the corex-ui mega menu item where it opens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
$corex_training_url = corex_pattern_page_url('training');
?>
<!-- wp:group {"className":"corex-megamenu corex-megamenu--services"} -->
<div class="wp-block-group corex-megamenu corex-megamenu--services">
	<!-- wp:group {"className":"corex-megamenu__list"} -->
	<div class="wp-block-group corex-megamenu__list">
		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('consulting', __('Consulting', 'corex')); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"corex-megamenu__text"} -->
			<p class="corex-megamenu__text"><?php echo esc_html__('Strategy and architecture reviews.', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('implementation', __('Implementation', 'corex')); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"corex-megamenu__text"} -->
			<p class="corex-megamenu__text"><?php echo esc_html__('Build and ship production features.', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php echo corex_pattern_link('support', __('Support', 'corex')); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"corex-megamenu__text"} -->
			<p class="corex-megamenu__text"><?php echo esc_html__('Ongoing maintenance and care.', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"corex-megamenu__item"} -->
		<div class="wp-block-group corex-megamenu__item">
			<!-- wp:paragraph {"className":"corex-megamenu__title"} -->
			<p class="corex-megamenu__title"><?php if ($corex_training_url !== '') : ?><a href="<?php echo esc_url($corex_training_url); ?>"><?php endif; ?><?php echo esc_html__('Training', 'corex'); ?><?php if ($corex_training_url !== '') : ?></a><?php endif; ?> <span class="corex-badge corex-badge--accent"><?php echo esc_html__('New', 'corex'); ?></span></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph {"className":"corex-megamenu__text"} -->
			<p class="corex-megamenu__text"><?php echo esc_html__('Team workshops and onboarding.', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
