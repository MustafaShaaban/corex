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
?>
<!-- wp:details {"summary":"<?php echo esc_attr__('Company', 'corex'); ?>","className":"corex-mega corex-mega--simple"} -->
<details class="wp-block-details corex-mega corex-mega--simple"><summary><?php echo esc_html__('Company', 'corex'); ?></summary>
	<!-- wp:group {"className":"corex-mega__panel","layout":{"type":"constrained"}} -->
	<div class="wp-block-group corex-mega__panel">
		<!-- wp:list -->
		<ul class="wp-block-list">
			<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('About', 'corex'); ?></a></li><!-- /wp:list-item -->
			<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Careers', 'corex'); ?></a></li><!-- /wp:list-item -->
			<!-- wp:list-item --><li><a href="#"><?php echo esc_html__('Contact', 'corex'); ?></a></li><!-- /wp:list-item -->
		</ul>
		<!-- /wp:list -->
	</div>
	<!-- /wp:group -->
</details>
<!-- /wp:details -->
