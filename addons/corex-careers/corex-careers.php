<?php
/**
 * Plugin Name:       Corex Careers
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The Corex careers module — job postings with department/location/type taxonomies, a corex/jobs listing block, and a secure application flow (CV upload validated, stored, HR + applicant notified) with a status pipeline. Built on Custom Tables, Uploads/Captcha, and Mail.
 * Version:           0.23.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban + team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 *
 * @package Corex\Careers
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_CAREERS_VERSION')) {
    define('COREX_CAREERS_VERSION', '0.23.0');
}

(static function (): void {
    $candidates = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__, 2) . '/vendor/autoload.php',
    ];
    foreach ($candidates as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            return;
        }
    }
})();

// Behavior is contributed by Corex\Careers\CareersServiceProvider via corex-core's Boot list.
