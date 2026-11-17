<?php

/**
 * The CoreX theme's presentation hooks. The theme is a skin (constitution, principle I): business
 * logic and the plugins' bootstrapping live in corex-core, never here.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/inc/favicon.php';

// Beside WordPress's own site icon links (`wp_site_icon`, also at 99), which stand alone when set.
add_action('wp_head', 'corex_theme_favicon_links', 99);
