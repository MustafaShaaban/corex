<?php

/**
 * Title: Project Card
 * Slug: corex/project-card
 * Categories: corex
 * Description: A single portfolio project card — featured image, project type, title, and excerpt — for use inside a projects query loop.
 * Block Types: core/post-template
 *
 * @package Corex
 */

defined('ABSPATH') || exit;
?>
<!-- wp:group {"className":"corex-project-card","layout":{"type":"constrained"},"style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
<div class="wp-block-group corex-project-card">
	<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","style":{"border":{"radius":"10px"}}} /-->
	<!-- wp:post-terms {"term":"project_type","fontSize":"small"} /-->
	<!-- wp:post-title {"isLink":true,"level":3,"fontSize":"medium"} /-->
	<!-- wp:post-excerpt {"moreText":"View project","fontSize":"small"} /-->
</div>
<!-- /wp:group -->
