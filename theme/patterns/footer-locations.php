<?php

/**
 * Title: Footer — Locations
 * Slug: corex/footer-locations
 * Categories: corex, footer
 * Block Types: core/template-part/footer
 * Description: A location footer — the address from the brand settings with its phone and email, then a legal/utility row. The location is left out when no address is set.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';

// One location, from the brand settings. With no address there is no location to show.
$corex_address = corex_pattern_brand('address');
$corex_contact = array_filter(['phone', 'email'], static fn (string $key): bool => corex_pattern_brand($key) !== '');
$corex_name    = corex_pattern_brand('company_name');
?>
<!-- wp:group {"tagName":"footer","className":"corex-footer corex-footer--locations","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|50"}}} -->
<footer class="wp-block-group corex-footer corex-footer--locations" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--50)">
	<?php if ($corex_address !== '') : ?>
	<!-- wp:columns {"className":"corex-footer__columns"} -->
	<div class="wp-block-columns corex-footer__columns">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:heading {"level":3,"fontSize":"sm"} -->
			<h3 class="wp-block-heading has-sm-font-size"><?php echo esc_html($corex_name !== '' ? $corex_name : __('Office', 'corex')); ?></h3>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><?php echo corex_pattern_contact('address'); ?></p>
			<!-- /wp:paragraph -->
			<?php foreach ($corex_contact as $corex_key) : ?>
			<!-- wp:paragraph -->
			<p><?php echo corex_pattern_contact($corex_key); ?></p>
			<!-- /wp:paragraph -->
			<?php endforeach; ?>
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
	<?php endif; ?>

	<!-- wp:group {"className":"corex-footer__legal","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
	<div class="wp-block-group corex-footer__legal">
		<!-- wp:corex/copyright /-->
	</div>
	<!-- /wp:group -->
</footer>
<!-- /wp:group -->
