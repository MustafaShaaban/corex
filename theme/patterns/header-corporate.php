<?php

/**
 * Title: Header — Corporate (top bar)
 * Slug: corex/header-corporate
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A corporate header — a top utility bar (the brand settings' email and phone, left out when neither is set) above the main brand, navigation, and call-to-action row.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';

// The top bar shows the brand settings' email and phone; with neither set it is left out.
$corex_contact = array_filter(['email', 'phone'], static fn (string $key): bool => corex_pattern_brand($key) !== '');
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--corporate","layout":{"type":"default"}} -->
<header class="wp-block-group corex-header corex-header--corporate">
	<?php if ($corex_contact !== []) : ?>
	<!-- wp:group {"className":"corex-header__topbar","layout":{"type":"constrained"}} -->
	<div class="wp-block-group corex-header__topbar">
		<!-- wp:group {"layout":{"type":"flex","justifyContent":"right","flexWrap":"wrap"}} -->
		<div class="wp-block-group">
			<?php foreach ($corex_contact as $corex_key) : ?>
			<!-- wp:paragraph {"fontSize":"xs"} -->
			<p class="has-xs-font-size"><?php echo corex_pattern_contact($corex_key); ?></p>
			<!-- /wp:paragraph -->
			<?php endforeach; ?>
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
	<?php endif; ?>

	<!-- wp:group {"className":"corex-header__main","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
	<div class="wp-block-group corex-header__main" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
		<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group corex-header__inner">
			<!-- wp:site-logo {"width":160} /-->

			<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
			<div class="wp-block-group corex-header__actions">
				<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"}} /-->

				<?php $corex_cta_url = corex_pattern_page_url('contact'); ?>
				<?php if ($corex_cta_url !== '') : ?>
				<!-- wp:buttons {"className":"corex-header__cta"} -->
				<div class="wp-block-buttons corex-header__cta"><?php echo corex_pattern_button($corex_cta_url, __('Contact us', 'corex')); ?></div>
				<!-- /wp:buttons -->
				<?php endif; ?>
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
