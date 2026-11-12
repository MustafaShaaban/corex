<?php

/**
 * Title: Header — Simple Company
 * Slug: corex/header-simple
 * Categories: corex, header
 * Block Types: core/template-part/header
 * Description: A simple company header — brand, primary navigation that collapses to an accessible mobile menu, and one call-to-action. Built on core blocks and CoreX brand tokens.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:group {"tagName":"header","className":"corex-header corex-header--simple","layout":{"type":"constrained"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} -->
<header class="wp-block-group corex-header corex-header--simple" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
	<!-- wp:group {"className":"corex-header__inner","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group corex-header__inner">
		<!-- wp:site-logo {"width":160,"shouldSyncIcon":false} /-->

		<!-- wp:site-title {"level":0,"className":"corex-header__title screen-reader-text"} /-->

		<!-- wp:group {"className":"corex-header__actions","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group corex-header__actions">
			<!-- wp:navigation {"overlayMenu":"mobile","className":"corex-header__nav","layout":{"type":"flex"},"ariaLabel":"<?php echo esc_attr_x('Primary', 'navigation landmark label', 'corex'); ?>"} /-->

			<?php $corex_cta_url = corex_pattern_action_url(); ?>
			<?php if ($corex_cta_url !== '') : ?>
			<!-- wp:buttons {"className":"corex-header__cta"} -->
			<div class="wp-block-buttons corex-header__cta"><?php echo corex_pattern_button($corex_cta_url, __('Get started', 'corex')); ?></div>
			<!-- /wp:buttons -->
			<?php endif; ?>
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</header>
<!-- /wp:group -->
