<?php

/**
 * The default coming-soon page (spec 101, FR-008).
 *
 * Registered by {@see \Corex\Config\Operations\ComingSoonTemplate} as the block template
 * `coming-soon`, so it is what a site in Coming soon serves under any block theme that has no
 * template of that name. A theme replaces it by shipping `templates/coming-soon.html`; either can
 * be edited in the Site Editor.
 *
 * PHP rather than a `.html` file for one reason: an HTML template cannot be translated, and this
 * is the one page of the site every visitor sees.
 *
 * Core blocks only, so it does not depend on corex-blocks being active. No header or footer part:
 * every link in one would be redirected straight back here. No colour and no fixed size — the
 * active theme's own styles and spacing presets decide how it looks. No side padding either: the
 * inner group is a constrained one, so it takes the theme's own root padding, which is the same
 * on both sides in either writing direction.
 *
 * @package Corex\Config
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"tagName":"main","align":"full","style":{"dimensions":{"minHeight":"100vh"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center","verticalAlignment":"center"}} -->
<main class="wp-block-group alignfull" style="min-height:100vh">
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">
		<!-- wp:site-title {"level":0,"textAlign":"center","isLink":false} /-->

		<!-- wp:heading {"textAlign":"center","level":1} -->
		<h1 class="wp-block-heading has-text-align-center"><?php echo esc_html__('Coming soon', 'corex'); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"align":"center"} -->
		<p class="has-text-align-center"><?php echo esc_html__('We are getting things ready. Please check back soon.', 'corex'); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->
