<?php

/**
 * Title: Process — Steps
 * Slug: corex/section-process-steps
 * Categories: corex
 * Description: A numbered process/steps section — a header and a responsive row of ordered steps, each with a number, title, and description. Built on core blocks and CoreX brand tokens; neutral placeholder content.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-process","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|50"}}} -->
<section class="wp-block-group corex-process" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"className":"corex-process__head","layout":{"type":"constrained","contentSize":"640px"}} -->
	<div class="wp-block-group corex-process__head">
		<!-- wp:paragraph {"className":"corex-eyebrow","fontSize":"sm"} -->
		<p class="corex-eyebrow has-sm-font-size"><?php echo esc_html__('HOW IT WORKS', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":2,"fontSize":"2xl"} -->
		<h2 class="wp-block-heading has-2-xl-font-size"><?php echo esc_html__('A clear, repeatable process', 'corex'); ?></h2>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:columns {"className":"corex-process__steps","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-columns corex-process__steps">
		<?php
		$steps = [
			[ '01', __('Discover', 'corex'), __('We learn your goals, audience, and constraints before anything is built.', 'corex') ],
			[ '02', __('Design', 'corex'), __('We shape accessible, on-brand solutions and validate them early.', 'corex') ],
			[ '03', __('Deliver', 'corex'), __('We build, test, and ship — then measure and iterate.', 'corex') ],
		];
		foreach ($steps as $step) :
			?>
		<!-- wp:column {"className":"corex-process__step"} -->
		<div class="wp-block-column corex-process__step">
			<!-- wp:paragraph {"className":"corex-process__num","textColor":"accent","fontSize":"xl"} -->
			<p class="corex-process__num has-accent-color has-text-color has-xl-font-size"><?php echo esc_html($step[0]); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:heading {"level":3,"fontSize":"lg"} -->
			<h3 class="wp-block-heading has-lg-font-size"><?php echo esc_html($step[1]); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph {"textColor":"ink-soft"} -->
			<p class="has-ink-soft-color has-text-color"><?php echo esc_html($step[2]); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
