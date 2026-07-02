<?php

/**
 * Title: Services — Grid
 * Slug: corex/section-services-grid
 * Categories: corex
 * Description: A section header and a responsive three-column grid of service cards — each with a title, description, and a link. Built on core blocks and CoreX brand tokens; neutral placeholder content.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-services","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|50"}}} -->
<section class="wp-block-group corex-services" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"className":"corex-services__head","layout":{"type":"constrained","contentSize":"640px"}} -->
	<div class="wp-block-group corex-services__head">
		<!-- wp:paragraph {"className":"corex-eyebrow","fontSize":"sm"} -->
		<p class="corex-eyebrow has-sm-font-size"><?php echo esc_html__('WHAT WE DO', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":2,"fontSize":"2xl"} -->
		<h2 class="wp-block-heading has-2-xl-font-size"><?php echo esc_html__('Services built for how you work', 'corex'); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"textColor":"ink-soft"} -->
		<p class="has-ink-soft-color has-text-color"><?php echo esc_html__('A neutral starting point — replace this copy with the services your company offers.', 'corex'); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:columns {"className":"corex-services__grid","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-columns corex-services__grid">
		<?php
		$cards = [
			[ __('Strategy', 'corex'), __('Plan the work — positioning, roadmaps, and measurable goals.', 'corex') ],
			[ __('Design', 'corex'), __('Accessible, on-brand interfaces that work in light, dark, and RTL.', 'corex') ],
			[ __('Delivery', 'corex'), __('Build and ship with tested, maintainable, standards-based code.', 'corex') ],
		];
		foreach ($cards as $card) :
			?>
		<!-- wp:column {"className":"corex-service-card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"var:custom|radius|md","width":"var:custom|border|width|thin"}},"borderColor":"border","backgroundColor":"surface-alt"} -->
		<div class="wp-block-column corex-service-card has-border-color has-border-border-color has-surface-alt-background-color has-background" style="border-width:var(--wp--custom--border--width--thin);border-radius:var(--wp--custom--radius--md);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
			<!-- wp:heading {"level":3,"fontSize":"lg"} -->
			<h3 class="wp-block-heading has-lg-font-size"><?php echo esc_html($card[0]); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"textColor":"ink-soft"} -->
			<p class="has-ink-soft-color has-text-color"><?php echo esc_html($card[1]); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph -->
			<p><a href="#"><?php echo esc_html__('Learn more', 'corex'); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
