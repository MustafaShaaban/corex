<?php

/**
 * Title: Footer — Corporate
 * Slug: corex/footer-corporate
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A corporate footer — brand column plus Company, Resources, and Contact link columns, then a legal/utility row.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';

// The Contact column shows the details the brand settings hold; with none set it is left out.
$corex_contact = array_filter(['email', 'phone', 'address'], static fn (string $key): bool => corex_pattern_brand($key) !== '');
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--corporate","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|50"}}} -->
<footer class="wp-block-group corex-footer corex-footer--corporate" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:columns {"className":"corex-footer__columns"} -->
	<div class="wp-block-columns corex-footer__columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:site-title {"level":2} /-->
			<!-- wp:paragraph -->
			<p><?php echo esc_html__('Professional WordPress, built spec-first.', 'corex'); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Company', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('about', __('About', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('careers', __('Careers', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('contact', __('Contact', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Resources', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item --><li><?php echo corex_pattern_link('docs', __('Documentation', 'corex')); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php echo corex_pattern_link('support', __('Support', 'corex')); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:column -->

		<?php if ($corex_contact !== []) : ?>
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html__('Contact', 'corex'); ?></h3>
			<!-- /wp:heading -->
			<?php foreach ($corex_contact as $corex_key) : ?>
			<!-- wp:paragraph -->
			<p><?php echo corex_pattern_contact($corex_key); ?></p>
			<!-- /wp:paragraph -->
			<?php endforeach; ?>
		</div>
		<!-- /wp:column -->
		<?php endif; ?>
	</div>
	<!-- /wp:columns -->

	<!-- wp:group {"className":"corex-footer__legal","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group corex-footer__legal">
		<!-- wp:corex/copyright /-->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
