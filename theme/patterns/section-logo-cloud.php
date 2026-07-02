<?php

/**
 * Title: Logo Cloud — Trusted By
 * Slug: corex/section-logo-cloud
 * Categories: corex
 * Description: A restrained "trusted by" row of partner/customer logos with a short lead-in. Built on core blocks and CoreX brand tokens; neutral placeholder logos only.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-logo-cloud","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|40"}}} -->
<section class="wp-block-group corex-logo-cloud" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:paragraph {"align":"center","className":"corex-eyebrow","fontSize":"sm"} -->
	<p class="has-text-align-center corex-eyebrow has-sm-font-size"><?php echo esc_html__('TRUSTED BY TEAMS EVERYWHERE', 'corex'); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:columns {"className":"corex-logo-cloud__row","verticalAlignment":"center","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-columns are-vertically-aligned-center corex-logo-cloud__row">
		<?php for ($i = 1; $i <= 5; $i++) : ?>
		<!-- wp:column {"verticalAlignment":"center","className":"corex-logo-cloud__item"} -->
		<div class="wp-block-column is-vertically-aligned-center corex-logo-cloud__item">
			<!-- wp:paragraph {"align":"center","textColor":"ink-soft","fontSize":"lg"} -->
			<p class="has-text-align-center has-ink-soft-color has-text-color has-lg-font-size">
				<?php
				/* translators: %d: placeholder partner logo number */
				echo esc_html(sprintf(__('Partner %d', 'corex'), $i));
				?>
			</p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<?php endfor; ?>
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
