<?php

/**
 * Title: Mega Menu — Simple Dropdown
 * Slug: corex/megamenu-simple
 * Categories: corex
 * Description: A single-column dropdown built on the native details/summary disclosure — keyboard-operable and usable with no JavaScript. Insert into a header's navigation area.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/pattern-links.php';
?>
<!-- wp:details {"summary":"<?php echo esc_attr__('Company', 'corex'); ?>","className":"corex-mega corex-mega--simple"} -->
<details class="wp-block-details corex-mega corex-mega--simple"><summary><?php echo esc_html__('Company', 'corex'); ?></summary>
	<!-- wp:group {"className":"corex-mega__panel","layout":{"type":"constrained"}} -->
	<div class="wp-block-group corex-mega__panel">
		<!-- wp:list -->
		<ul class="wp-block-list">
			<!-- wp:list-item --><li><?php echo corex_pattern_link('about', __('About', 'corex')); ?></li><!-- /wp:list-item -->
			<!-- wp:list-item --><li><?php echo corex_pattern_link('careers', __('Careers', 'corex')); ?></li><!-- /wp:list-item -->
			<!-- wp:list-item --><li><?php echo corex_pattern_link('contact', __('Contact', 'corex')); ?></li><!-- /wp:list-item -->
		</ul>
		<!-- /wp:list -->
	</div>
	<!-- /wp:group -->
</details>
<!-- /wp:details -->
