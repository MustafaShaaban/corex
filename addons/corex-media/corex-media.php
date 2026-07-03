<?php

/**
 * Plugin Name:       Corex Media
 * Description:       Optional media optimization for Corex — WebP on upload + an optimized <picture> helper.
 * Version:           0.33.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * Text Domain:       corex
 *
 * @package Corex\Media
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (! defined('COREX_MEDIA_FILE')) {
    define('COREX_MEDIA_FILE', __FILE__);
}
