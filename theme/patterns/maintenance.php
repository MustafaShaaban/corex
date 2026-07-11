<?php

/**
 * Title: Maintenance notice
 * Slug: corex/maintenance
 * Categories: corex
 * Description: A centered maintenance/coming-soon notice — heading, message, and a contact line. Mirrors the live Operations maintenance surface (Corex\Admin\StandalonePage) for editor use. Built on core blocks and CoreX brand tokens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"section","className":"corex-maintenance","align":"full","layout":{"type":"constrained","contentSize":"560px"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|30"},"dimensions":{"minHeight":"70vh"}}} -->
<section class="wp-block-group alignfull corex-maintenance" style="min-height:70vh;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">
	<!-- wp:paragraph {"align":"center","className":"corex-eyebrow","textColor":"accent","fontSize":"sm"} -->
	<p class="has-text-align-center corex-eyebrow has-accent-color has-text-color has-sm-font-size"><?php echo esc_html__('SCHEDULED MAINTENANCE', 'corex'); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"3xl"} -->
	<h1 class="wp-block-heading has-text-align-center has-3-xl-font-size"><?php echo esc_html__('We’ll be right back', 'corex'); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","textColor":"ink-soft","fontSize":"lg"} -->
	<p class="has-text-align-center has-ink-soft-color has-text-color has-lg-font-size"><?php echo esc_html__('The site is briefly offline for planned maintenance. Please check back shortly — thank you for your patience.', 'corex'); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"align":"center","fontSize":"sm"} -->
	<p class="has-text-align-center has-sm-font-size"><a href="mailto:<?php echo esc_attr__('hello@example.com', 'corex'); ?>"><?php echo esc_html__('Contact us', 'corex'); ?></a></p>
	<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->
