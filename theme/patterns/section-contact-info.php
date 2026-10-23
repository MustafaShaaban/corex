<?php

/**
 * Title: Contact — Info Cards
 * Slug: corex/section-contact-info
 * Categories: corex
 * Description: A contact section with a heading and a responsive row of info cards: the address, email and phone from the brand settings, each left out when it is not set. Built on core blocks and CoreX brand tokens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';

// One card per contact detail the brand settings hold; a detail with no setting has no card.
$corex_cards = array_filter(
    [
        [__('Office', 'corex'), 'address'],
        [__('Email', 'corex'), 'email'],
        [__('Phone', 'corex'), 'phone'],
    ],
    static fn (array $card): bool => corex_pattern_brand($card[1]) !== ''
);
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

	<?php if ($corex_cards !== []) : ?>
	<!-- wp:columns {"className":"corex-contact__cards","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"}}}} -->
	<div class="wp-block-columns corex-contact__cards">
		<?php foreach ($corex_cards as [$corex_label, $corex_key]) : ?>
		<!-- wp:column {"className":"corex-contact__card","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}},"border":{"radius":"var:custom|radius|md","width":"var:custom|border|width|thin"}},"borderColor":"border","backgroundColor":"surface-alt"} -->
		<div class="wp-block-column corex-contact__card has-border-color has-border-border-color has-surface-alt-background-color has-background" style="border-width:var(--wp--custom--border--width--thin);border-radius:var(--wp--custom--radius--md);padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
			<!-- wp:paragraph {"className":"corex-eyebrow","textColor":"ink-soft","fontSize":"sm"} -->
			<p class="corex-eyebrow has-ink-soft-color has-text-color has-sm-font-size"><?php echo esc_html($corex_label); ?></p>
			<!-- /wp:paragraph -->
			<!-- wp:paragraph -->
			<p><?php echo corex_pattern_contact($corex_key); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->
	<?php endif; ?>
</section>
<!-- /wp:group -->
