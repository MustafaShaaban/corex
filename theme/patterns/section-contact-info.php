<?php

/**
 * Title: Contact — Info Cards
 * Slug: corex/section-contact-info
 * Categories: corex
 * Description: A contact section with a heading and a responsive row of info cards (address, email, phone, hours). Built on core blocks and CoreX brand tokens; neutral placeholder content.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-contact","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|50"}}} -->
<section class="wp-block-group corex-contact" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"className":"corex-contact__head","layout":{"type":"constrained","contentSize":"640px"}} -->
	<div class="wp-block-group corex-contact__head">
		<!-- wp:paragraph {"className":"corex-eyebrow","fontSize":"sm"} -->
		<p class="corex-eyebrow has-sm-font-size"><?php echo esc_html__('GET IN TOUCH', 'corex'); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"level":2,"fontSize":"2xl"} -->
		<h2 class="wp-block-heading has-2-xl-font-size"><?php echo esc_html__('Talk to our team', 'corex'); ?></h2>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:columns {"className":"corex-contact__cards","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-columns corex-contact__cards">
		<?php
		$cards = [
			[ __('Office', 'corex'), __('123 Example Street, Suite 100, Anytown', 'corex') ],
			[ __('Email', 'corex'), __('hello@example.com', 'corex') ],
			[ __('Phone', 'corex'), __('+1 (555) 010-0100', 'corex') ],
			[ __('Hours', 'corex'), __('Mon–Fri, 9:00–17:00', 'corex') ],
		];
		foreach ($cards as $card) :
			?>
		<!-- wp:column {"className":"corex-contact__card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"var:custom|radius|md","width":"var:custom|border|width|thin"}},"borderColor":"border","backgroundColor":"surface-alt"} -->
		<div class="wp-block-column corex-contact__card has-border-color has-border-border-color has-surface-alt-background-color has-background" style="border-width:var(--wp--custom--border--width--thin);border-radius:var(--wp--custom--radius--md);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
			<!-- wp:paragraph {"className":"corex-eyebrow","textColor":"ink-soft","fontSize":"sm"} -->
			<p class="corex-eyebrow has-ink-soft-color has-text-color has-sm-font-size"><?php echo esc_html($card[0]); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph -->
			<p><?php echo esc_html($card[1]); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->
</section>
<!-- /wp:group -->
