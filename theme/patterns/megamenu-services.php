<?php

/**
 * Title: Mega Menu — Services
 * Slug: corex/megamenu-services
 * Categories: corex
 * Description: A services mega menu — a multi-column panel of service items, each with a title, description, and link. Native details/summary disclosure; usable with no JavaScript.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:details {"summary":"<?php echo esc_attr__('Services', 'corex'); ?>","className":"corex-mega corex-mega--services"} -->
<details class="wp-block-details corex-mega corex-mega--services"><summary><?php echo esc_html__('Services', 'corex'); ?></summary>
	<!-- wp:columns {"className":"corex-mega__panel"} -->
	<div class="wp-block-columns corex-mega__panel">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"corex-mega__item"} -->
			<div class="wp-block-group corex-mega__item">
				<!-- wp:heading {"level":3,"fontSize":"sm"} -->
				<h3 class="wp-block-heading has-sm-font-size"><?php echo corex_pattern_link('consulting', __('Consulting', 'corex')); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"className":"corex-mega__desc","fontSize":"xs"} -->
				<p class="corex-mega__desc has-xs-font-size"><?php echo esc_html__('Strategy and architecture reviews.', 'corex'); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"corex-mega__item"} -->
			<div class="wp-block-group corex-mega__item">
				<!-- wp:heading {"level":3,"fontSize":"sm"} -->
				<h3 class="wp-block-heading has-sm-font-size"><?php echo corex_pattern_link('implementation', __('Implementation', 'corex')); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"className":"corex-mega__desc","fontSize":"xs"} -->
				<p class="corex-mega__desc has-xs-font-size"><?php echo esc_html__('Build and ship production features.', 'corex'); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"corex-mega__item"} -->
			<div class="wp-block-group corex-mega__item">
				<!-- wp:heading {"level":3,"fontSize":"sm"} -->
				<h3 class="wp-block-heading has-sm-font-size"><?php echo corex_pattern_link('support', __('Support', 'corex')); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"className":"corex-mega__desc","fontSize":"xs"} -->
				<p class="corex-mega__desc has-xs-font-size"><?php echo esc_html__('Ongoing maintenance and care.', 'corex'); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"className":"corex-mega__item"} -->
			<div class="wp-block-group corex-mega__item">
				<?php $corex_training_url = corex_pattern_page_url('training'); ?>
				<!-- wp:heading {"level":3,"fontSize":"sm"} -->
				<h3 class="wp-block-heading has-sm-font-size"><?php if ($corex_training_url !== '') : ?><a href="<?php echo esc_url($corex_training_url); ?>"><?php endif; ?><?php echo esc_html__('Training', 'corex'); ?> <span class="corex-mega__badge"><?php echo esc_html__('New', 'corex'); ?></span><?php if ($corex_training_url !== '') : ?></a><?php endif; ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph {"className":"corex-mega__desc","fontSize":"xs"} -->
				<p class="corex-mega__desc has-xs-font-size"><?php echo esc_html__('Team workshops and onboarding.', 'corex'); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</details>
<!-- /wp:details -->
